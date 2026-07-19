<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzRouteClusteringAudit;
use App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints;
use App\Services\UrbanGoodz\Routing\DTOs\DistanceResult;
use App\Services\UrbanGoodz\Routing\DTOs\PlanningMetrics;
use App\Services\UrbanGoodz\Routing\DTOs\PlanningResult;
use App\Services\UrbanGoodz\Routing\DTOs\RouteCluster;
use App\Services\UrbanGoodz\Routing\DTOs\RouteStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoutePlanningService
{
    private DistanceMatrixService $distanceMatrix;
    private RouteClusteringService $clustering;
    private RouteSequencingService $sequencing;
    private string $algorithmVersion;

    public function __construct(
        ?DistanceMatrixService $distanceMatrix = null,
        ?RouteClusteringService $clustering = null,
        ?RouteSequencingService $sequencing = null,
    ) {
        $this->distanceMatrix = $distanceMatrix ?? new DistanceMatrixService();
        $this->clustering = $clustering ?? new RouteClusteringService($this->distanceMatrix);
        $this->sequencing = $sequencing ?? new RouteSequencingService($this->distanceMatrix);
        $this->algorithmVersion = config('urban_goodz.clustering.algorithm_version', '1.0.0');
    }

    public function planFromManifest(int $manifestId, array $request = []): PlanningResult
    {
        $startTime = microtime(true);
        $this->distanceMatrix->resetStats();

        $constraints = ClusteringConstraints::fromConfigAndRequest(
            config('urban_goodz', []),
            $request
        );

        $packages = $this->loadManifestPackages($manifestId);
        $stops = $this->convertToStops($packages);

        $result = $this->executePlanningPipeline($stops, $constraints, $manifestId);

        $totalMs = (microtime(true) - $startTime) * 1000;
        $result = $this->enrichMetrics($result, $totalMs);

        if (($request['persist'] ?? true)) {
            $auditId = $this->persistPlanningResult($result, $manifestId, $constraints, $request);
            $result = new PlanningResult(
                totalPackages: $result->totalPackages,
                routedPackages: $result->routedPackages,
                unrouteableCount: $result->unrouteableCount,
                routeCountRequested: $result->routeCountRequested,
                routeCountGenerated: $result->routeCountGenerated,
                uniqueStopCount: $result->uniqueStopCount,
                clusters: $result->clusters,
                unrouteable: $result->unrouteable,
                sameAddressGroups: $result->sameAddressGroups,
                metrics: $result->metrics,
                constraints: $result->constraints,
                algorithmVersion: $result->algorithmVersion,
                overallDistanceMode: $result->overallDistanceMode,
                overallViolations: $result->overallViolations,
                warnings: $result->warnings,
                auditId: $auditId,
            );
        }

        return $result;
    }

    public function planFromPool(array $packages, array $request = []): PlanningResult
    {
        $startTime = microtime(true);
        $this->distanceMatrix->resetStats();

        $constraints = ClusteringConstraints::fromConfigAndRequest(
            config('urban_goodz', []),
            $request
        );

        $stops = $this->convertToStops($packages);
        $result = $this->executePlanningPipeline($stops, $constraints, null);

        $totalMs = (microtime(true) - $startTime) * 1000;
        return $this->enrichMetrics($result, $totalMs);
    }

    public function planFromStops(array $stops, array $request = []): PlanningResult
    {
        $startTime = microtime(true);
        $this->distanceMatrix->resetStats();

        $constraints = ClusteringConstraints::fromConfigAndRequest(
            config('urban_goodz', []),
            $request
        );

        $result = $this->executePlanningPipeline($stops, $constraints, null);

        $totalMs = (microtime(true) - $startTime) * 1000;
        return $this->enrichMetrics($result, $totalMs);
    }

    private function executePlanningPipeline(array $stops, ClusteringConstraints $constraints, ?int $manifestId): PlanningResult
    {
        $clusterStartMs = microtime(true);

        $lockedStops = array_values(array_filter($stops, fn($s) => $s->isLocked));
        $validStops = array_values(array_filter($stops, fn($s) => $s->hasValidCoordinates()));
        $invalidStops = array_values(array_filter($stops, fn($s) => !$s->hasValidCoordinates()));

        $clusterResult = $this->clustering->cluster($validStops, $constraints);
        $clusteredStops = $clusterResult['clusters'];

        $clusterTimeMs = (microtime(true) - $clusterStartMs) * 1000;

        $sequenceStartMs = microtime(true);
        $sequencedResult = $this->sequencing->sequenceAllRoutes($clusteredStops, $constraints);
        $sequenceTimeMs = (microtime(true) - $sequenceStartMs) * 1000;

        $clusters = $this->buildRouteClusters($sequencedResult, $constraints);

        $overallDistanceMode = $this->computeOverallDistanceMode($clusters);
        $violations = $this->collectViolations($clusters);
        $warnings = $this->collectWarnings($clusters, $invalidStops);

        $unrouteable = $this->buildUnrouteableList($invalidStops, $constraints);

        $routeCount = count($clusters);
        $requestedCount = $constraints->requestedRouteCount ?? $routeCount;
        $totalPackages = array_sum(array_map(fn($c) => $c->packageCount, $clusters)) + count($unrouteable);

        return new PlanningResult(
            totalPackages: $totalPackages,
            routedPackages: $totalPackages - count($unrouteable),
            unrouteableCount: count($unrouteable),
            routeCountRequested: $requestedCount,
            routeCountGenerated: $routeCount,
            uniqueStopCount: array_sum(array_map(fn($c) => $c->uniqueStopCount, $clusters)),
            clusters: $clusters,
            unrouteable: $unrouteable,
            sameAddressGroups: $clusterResult['same_address_groups'],
            metrics: PlanningMetrics::empty(),
            constraints: $constraints,
            algorithmVersion: $this->algorithmVersion,
            overallDistanceMode: $overallDistanceMode,
            overallViolations: $violations,
            warnings: $warnings,
        );
    }

    private function loadManifestPackages(int $manifestId): array
    {
        return DB::table('urban_goodz_manifest_packages')
            ->where('manifest_id', $manifestId)
            ->whereNotIn('package_status', ['cancelled', 'returned'])
            ->get()
            ->toArray();
    }

    private function convertToStops(array $packages): array
    {
        $stops = [];
        foreach ($packages as $pkg) {
            $lat = (float)($pkg->dropoff_lat ?? 0);
            $lng = (float)($pkg->dropoff_lng ?? 0);

            if ($lat == 0 && $lng == 0) {
                $lat = (float)($pkg->pickup_lat ?? 0);
                $lng = (float)($pkg->pickup_lng ?? 0);
            }

            $stops[] = RouteStop::fromPackageModel((object)[
                'id' => $pkg->id ?? 0,
                'tracking_id' => $pkg->tracking_id ?? '',
                'dropoff_lat' => $lat,
                'dropoff_lng' => $lng,
                'dropoff_address' => $pkg->dropoff_address ?? '',
                'dropoff_city' => $pkg->dropoff_city ?? '',
                'dropoff_state' => $pkg->dropoff_state ?? '',
                'dropoff_zip' => $pkg->dropoff_zip ?? '',
                'pickup_lat' => $pkg->pickup_lat ?? null,
                'pickup_lng' => $pkg->pickup_lng ?? null,
                'priority' => $pkg->priority ?? 'normal',
                'delivery_window_start' => isset($pkg->delivery_window_start) ? (string)$pkg->delivery_window_start : null,
                'delivery_window_end' => isset($pkg->delivery_window_end) ? (string)$pkg->delivery_window_end : null,
                'delivery_completion_locked_until_verified' => $pkg->delivery_completion_locked_until_verified ?? false,
                'age_restricted' => $pkg->age_restricted ?? false,
                'requires_custody' => $pkg->requires_custody ?? false,
                'requires_signature' => $pkg->requires_signature ?? false,
                'requires_photo' => $pkg->requires_photo ?? false,
                'weight' => $pkg->weight ?? null,
                'package_type' => $pkg->package_type ?? 'parcel',
                'manifest_id' => $pkg->manifest_id ?? null,
            ]);
        }
        return $stops;
    }

    private function buildRouteClusters(array $sequencedResult, ClusteringConstraints $constraints): array
    {
        $clusters = [];
        $labelIndex = 0;

        foreach ($sequencedResult as $idx => $result) {
            $label = $this->generateRouteLabel($labelIndex);
            $labelIndex++;

            $stops = $result['ordered_stops'];
            $totalWeight = array_sum(array_map(fn($s) => $s->weightLbs ?? 0, $stops));

            $validStops = array_values(array_filter($stops, fn($s) => $s->hasValidCoordinates()));
            $centerLat = !empty($validStops) ? array_sum(array_map(fn($s) => $s->lat, $validStops)) / count($validStops) : null;
            $centerLng = !empty($validStops) ? array_sum(array_map(fn($s) => $s->lng, $validStops)) / count($validStops) : null;

            $distsFromCenter = [];
            if ($centerLat !== null && $centerLng !== null) {
                foreach ($validStops as $stop) {
                    $distsFromCenter[] = $this->distanceMatrix->haversine($stop->lat, $stop->lng, $centerLat, $centerLng);
                }
            }

            $workloadScore = $this->calculateWorkloadScore($result);

            $clusters[] = new RouteCluster(
                clusterIndex: $idx,
                label: $label,
                stops: $stops,
                estimatedMiles: $result['total_miles'],
                estimatedDurationMinutes: $result['estimated_duration_minutes'],
                packageCount: $result['total_packages'],
                uniqueStopCount: $result['unique_stop_count'],
                totalWeightLbs: $totalWeight,
                clusterCenterLat: $centerLat,
                clusterCenterLng: $centerLng,
                maxStopDistanceFromCenter: !empty($distsFromCenter) ? max($distsFromCenter) : null,
                averageStopDistance: !empty($distsFromCenter) ? array_sum($distsFromCenter) / count($distsFromCenter) : 0,
                workloadScore: $workloadScore,
                distanceMode: $result['distance_mode'],
                hasTimeWindows: !empty(array_filter($stops, fn($s) => $s->deliveryWindowStart !== null)),
                hasAgeRestricted: !empty(array_filter($stops, fn($s) => $s->isAgeRestricted)),
                hasMedical: !empty(array_filter($stops, fn($s) => $s->priority === 'medical')),
                constraintViolations: $result['violations'] ?? [],
                warnings: $result['warnings'] ?? [],
            );
        }

        return $clusters;
    }

    private function calculateWorkloadScore(array $result): float
    {
        $milesScore = min(1.0, $result['total_miles'] / 100) * 30;
        $durationScore = min(1.0, $result['estimated_duration_minutes'] / 480) * 30;
        $packageScore = min(1.0, $result['total_packages'] / 20) * 20;
        $stopScore = min(1.0, $result['unique_stop_count'] / 15) * 20;

        return round($milesScore + $durationScore + $packageScore + $stopScore, 2);
    }

    private function generateRouteLabel(int $index): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $label = '';
        $idx = $index;

        do {
            $label = $letters[$idx % 26] . $label;
            $idx = intdiv($idx, 26) - 1;
        } while ($idx >= 0);

        return $label;
    }

    private function computeOverallDistanceMode(array $clusters): string
    {
        $roadCount = 0;
        $haversineCount = 0;

        foreach ($clusters as $cluster) {
            if (str_contains($cluster->distanceMode, 'ROAD')) {
                $roadCount++;
            } else {
                $haversineCount++;
            }
        }

        if ($roadCount > 0 && $haversineCount === 0) return 'ROAD_MATRIX';
        if ($roadCount > 0 && $haversineCount > 0) return 'MIXED_ROAD_HAVERSINE';
        return 'HAVERSINE_FALLBACK';
    }

    private function collectViolations(array $clusters): array
    {
        $violations = [];
        foreach ($clusters as $cluster) {
            foreach ($cluster->constraintViolations as $v) {
                $violations[] = array_merge($v, ['route_label' => $cluster->label]);
            }
        }
        return $violations;
    }

    private function collectWarnings(array $clusters, array $invalidStops): array
    {
        $warnings = [];

        if (!empty($invalidStops)) {
            $warnings[] = count($invalidStops) . ' packages excluded: invalid coordinates';
        }

        foreach ($clusters as $cluster) {
            foreach ($cluster->warnings as $w) {
                $warnings[] = "[{$cluster->label}] {$w}";
            }
        }

        return $warnings;
    }

    private function buildUnrouteableList(array $invalidStops, ClusteringConstraints $constraints): array
    {
        return array_map(fn($stop) => [
            'package_id' => $stop->packageId,
            'tracking_id' => $stop->trackingId,
            'address' => $stop->address,
            'reason' => 'invalid_coordinates',
            'lat' => $stop->lat,
            'lng' => $stop->lng,
        ], $invalidStops);
    }

    private function enrichMetrics(PlanningResult $result, float $totalMs): PlanningResult
    {
        $stats = $this->distanceMatrix->getStats();

        $metrics = new PlanningMetrics(
            totalPlanningTimeMs: $totalMs,
            matrixFetchTimeMs: $stats['matrix_fetch_time_ms'],
            clusteringTimeMs: 0,
            sequencingTimeMs: 0,
            persistenceTimeMs: 0,
            matrixRequestCount: $stats['request_count'],
            cacheHitCount: $stats['cache_hit_count'],
            cacheMissCount: $stats['cache_miss_count'],
            memoryPeakMb: round(memory_get_peak_usage(true) / 1048576, 2),
            cacheHitRate: $stats['cache_hit_rate'],
        );

        return new PlanningResult(
            totalPackages: $result->totalPackages,
            routedPackages: $result->routedPackages,
            unrouteableCount: $result->unrouteableCount,
            routeCountRequested: $result->routeCountRequested,
            routeCountGenerated: $result->routeCountGenerated,
            uniqueStopCount: $result->uniqueStopCount,
            clusters: $result->clusters,
            unrouteable: $result->unrouteable,
            sameAddressGroups: $result->sameAddressGroups,
            metrics: $metrics,
            constraints: $result->constraints,
            algorithmVersion: $result->algorithmVersion,
            overallDistanceMode: $result->overallDistanceMode,
            overallViolations: $result->overallViolations,
            warnings: $result->warnings,
            auditId: $result->auditId,
        );
    }

    private function persistPlanningResult(PlanningResult $result, ?int $manifestId, ClusteringConstraints $constraints, array $request): int
    {
        $startTime = microtime(true);

        $clusterData = array_map(fn($c) => $c->toSummaryArray(), $result->clusters);

        $audit = UrbanGoodzRouteClusteringAudit::create([
            'business_client_id' => $request['business_client_id'] ?? null,
            'manifest_id' => $manifestId,
            'planning_uuid' => $request['planning_uuid'] ?? (string)\Illuminate\Support\Str::uuid(),
            'clustering_params' => json_encode([
                'algorithm_version' => $this->algorithmVersion,
                'requested_route_count' => $constraints->requestedRouteCount,
                'target_packages_per_route' => $constraints->targetPackagesPerRoute,
                'maximum_packages_per_route' => $constraints->maximumPackagesPerRoute,
                'maximum_cluster_radius_miles' => $constraints->maximumClusterRadiusMiles,
                'maximum_route_miles' => $constraints->maximumRouteMiles,
                'return_to_origin' => $constraints->returnToOrigin,
            ]),
            'original_plan' => json_encode(['total_packages' => $result->totalPackages]),
            'optimized_plan' => json_encode([
                'clusters' => $clusterData,
                'unrouteable' => $result->unrouteable,
                'same_address_groups' => $result->sameAddressGroups,
                'distance_mode' => $result->overallDistanceMode,
            ]),
            'unrouteable_packages' => json_encode($result->unrouteable),
            'algorithm' => 'urban-goodz-v1',
            'distance_mode' => $result->overallDistanceMode,
            'status' => 'pending_review',
            'metrics' => json_encode($result->metrics->toArray()),
        ]);

        $elapsedMs = (microtime(true) - $startTime) * 1000;
        Log::info('RoutePlanningService: persisted audit', [
            'audit_id' => $audit->id,
            'elapsed_ms' => round($elapsedMs, 2),
        ]);

        return $audit->id;
    }
}
