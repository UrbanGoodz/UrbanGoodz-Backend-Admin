<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteBatch;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzRouteClusteringAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class UrbanGoodzRouteClusteringService
{
    private ?array $distanceMatrixCache = null;
    private array $roadDistanceCache = [];

    public const DEFAULT_PARAMS = [
        'requested_route_count' => null,
        'target_packages_per_route' => null,
        'maximum_packages_per_route' => 50,
        'preferred_cluster_radius_miles' => 25,
        'maximum_route_miles' => 150,
        'maximum_route_duration_minutes' => 480,
        'start_location' => null,
        'end_location' => null,
        'vehicle_type' => 'cargo_van',
        'max_weight_lbs' => null,
        'respect_time_windows' => true,
        'preserve_locked_stops' => true,
        'preserve_priority_stops' => true,
    ];

    public function clusterPackages(
        Collection $packages,
        array $params = [],
        ?int $manifestId = null,
        ?int $clientId = null,
    ): array {
        $params = array_merge(self::DEFAULT_PARAMS, $params);

        $eligible = $this->filterEligiblePackages($packages, $params);
        $locked = $this->extractLockedPackages($eligible, $params);
        $routable = $eligible->reject(fn($p) => $locked->contains('id', $p->id));

        $clusters = $this->sweepCluster($routable, $params);

        $this->assignLockedPackages($clusters, $locked, $params);

        $this->enforceMaxCapacity($clusters, $params);

        $unrouteable = $this->identifyUnrouteable($eligible, $clusters, $params);

        $optimizedClusters = [];
        foreach ($clusters as $index => $cluster) {
            $optimized = $this->optimizeClusterOrder($cluster, $params);
            $routeStats = $this->calculateRouteStats($optimized, $params);
            $optimizedClusters[] = [
                'packages' => $optimized,
                'stats' => $routeStats,
                'cluster_index' => $index + 1,
            ];
        }

        $audit = $this->createAuditSnapshot($optimizedClusters, $unrouteable, $params, $manifestId, $clientId);

        return [
            'clusters' => $optimizedClusters,
            'unrouteable' => $unrouteable,
            'total_packages' => $eligible->count(),
            'routed_packages' => $eligible->count() - $unrouteable->count(),
            'route_count' => count($optimizedClusters),
            'audit_id' => $audit->id,
            'params' => $params,
        ];
    }

    public function createRoutesFromClusters(array $clusterResult, array $routeParams = []): array
    {
        $routes = [];

        DB::beginTransaction();
        try {
            foreach ($clusterResult['clusters'] as $cluster) {
                $route = $this->createSingleRoute($cluster, $routeParams, $clusterResult['params']);
                $routes[] = $route;
            }

            if (!empty($clusterResult['unrouteable'])) {
                $this->flagUnrouteablePackages($clusterResult['unrouteable']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RouteClusteringService: Failed to create routes from clusters', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $routes;
    }

    public function recalculateAfterManualChange(int $routeId): array
    {
        $route = UrbanGoodzDedicatedRoute::with('packages')->findOrFail($routeId);

        $allRoutePackages = UrbanGoodzRoutePackage::where('business_client_id', $route->business_client_id)
            ->whereIn('status', ['pending', 'pending_review', 'ready_for_route', 'assigned'])
            ->where(function ($q) use ($routeId) {
                $q->where('dedicated_route_id', $routeId)
                    ->orWhereNull('dedicated_route_id');
            })
            ->get();

        $params = [
            'start_location' => [
                'lat' => $route->pickup_lat,
                'lng' => $route->pickup_lng,
            ],
            'end_location' => $route->end_lat && $route->end_lng
                ? ['lat' => $route->end_lat, 'lng' => $route->end_lng]
                : null,
            'maximum_packages_per_route' => $route->max_packages_per_batch ?: 50,
            'vehicle_type' => $route->vehicle_type_required ?? 'cargo_van',
        ];

        $result = $this->clusterPackages($allRoutePackages, $params, null, $route->business_client_id);

        $this->clearRouteAssignments($routeId);
        $this->applyClusterToRoute($route, $result);

        return $result;
    }

    public function identifyTimeWindowConflicts(array $orderedPackages, array $params): array
    {
        $conflicts = [];
        $currentTime = $params['start_time'] ?? now();
        $serviceTimeMinutes = $params['service_time_per_stop'] ?? 10;

        foreach ($orderedPackages as $index => $pkg) {
            if (!$params['respect_time_windows']) {
                continue;
            }

            $windowStart = $pkg->delivery_window_start;
            $windowEnd = $pkg->delivery_window_end;

            if (!$windowStart || !$windowEnd) {
                $currentTime = $currentTime->copy()->addMinutes($serviceTimeMinutes);
                continue;
            }

            if ($currentTime->greaterThan($windowEnd)) {
                $conflicts[] = [
                    'package_id' => $pkg->id,
                    'tracking_id' => $pkg->tracking_id,
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'estimated_arrival' => $currentTime->toIso8601String(),
                    'conflict_type' => 'missed_window',
                    'delay_minutes' => $currentTime->diffInMinutes($windowEnd),
                ];
            } elseif ($currentTime->lessThan($windowStart)) {
                $currentTime = $windowStart->copy();
            }

            $travelMinutes = $this->estimateTravelMinutes(
                $index > 0 ? $orderedPackages[$index - 1] : null,
                $pkg,
                $params
            );
            $currentTime = $currentTime->copy()->addMinutes($travelMinutes + $serviceTimeMinutes);
        }

        return $conflicts;
    }

    private function filterEligiblePackages(Collection $packages, array $params): Collection
    {
        return $packages->filter(function ($pkg) {
            $ineligibleStatuses = [
                'delivered', 'completed', 'failed', 'returning_to_pickup',
                'returning_to_hub', 'returned_to_pickup', 'returned_to_hub',
                'returned_to_business', 'payout_excluded', 'canceled',
            ];
            return !in_array($pkg->status, $ineligibleStatuses);
        })->values();
    }

    private function extractLockedPackages(Collection $packages, array $params): Collection
    {
        return $packages->filter(function ($pkg) use ($params) {
            if ($params['preserve_locked_stops'] && $pkg->delivery_completion_locked_until_verified) {
                return true;
            }
            if ($params['preserve_priority_stops'] && in_array($pkg->priority, ['urgent', 'medical'])) {
                return true;
            }
            return false;
        })->values();
    }

    private function sweepCluster(Collection $packages, array $params): array
    {
        if ($packages->isEmpty()) {
            return [];
        }

        $startLat = $params['start_location']['lat'] ?? $packages->first()->dropoff_lat;
        $startLng = $params['start_location']['lng'] ?? $packages->first()->dropoff_lng;

        $withAngles = $packages->map(function ($pkg) use ($startLat, $startLng) {
            $bearing = $this->calculateBearing(
                $startLat, $startLng,
                $pkg->dropoff_lat ?? $startLat, $pkg->dropoff_lng ?? $startLng
            );
            return [
                'package' => $pkg,
                'bearing' => $bearing,
                'distance' => $this->haversineDistance(
                    $startLat, $startLng,
                    $pkg->dropoff_lat ?? $startLat, $pkg->dropoff_lng ?? $startLng
                ),
            ];
        })->sortBy('bearing')->values();

        $targetRouteCount = $params['requested_route_count'];
        $maxPerRoute = $params['maximum_packages_per_route'] ?? 50;
        $targetPerRoute = $params['target_packages_per_route'];
        $radiusMiles = $params['preferred_cluster_radius_miles'] ?? 25;
        $maxRouteMiles = $params['maximum_route_miles'] ?? 150;
        $maxRouteDuration = $params['maximum_route_duration_minutes'] ?? 480;

        if ($targetRouteCount && $targetRouteCount > 0) {
            return $this->splitByCount($withAngles, $targetRouteCount, $params);
        }

        if ($targetPerRoute && $targetPerRoute > 0) {
            return $this->splitByFixedSize($withAngles, $targetPerRoute, $maxPerRoute, $params);
        }

        return $this->splitByRadius($withAngles, $radiusMiles, $maxPerRoute, $maxRouteMiles, $maxRouteDuration, $params);
    }

    private function splitByCount(Collection $withAngles, int $routeCount, array $params): array
    {
        $total = $withAngles->count();
        $chunkSize = (int) ceil($total / $routeCount);
        $clusters = $withAngles->chunk($chunkSize)->toArray();

        return array_map(fn($chunk) => array_column($chunk, 'package'), $clusters);
    }

    private function splitByFixedSize(Collection $withAngles, int $targetSize, int $maxSize, array $params): array
    {
        $clusters = [];
        $current = [];

        foreach ($withAngles as $item) {
            $current[] = $item['package'];

            if (count($current) >= $targetSize) {
                $clusters[] = $current;
                $current = [];
            }
        }

        if (!empty($current)) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    private function splitByRadius(
        Collection $withAngles,
        float $radiusMiles,
        int $maxPerRoute,
        float $maxRouteMiles,
        float $maxRouteDuration,
        array $params
    ): array {
        $clusters = [];
        $currentCluster = [];
        $currentMiles = 0.0;
        $currentDuration = 0.0;
        $lastPackage = null;

        foreach ($withAngles as $item) {
            $pkg = $item['package'];

            $wouldExceedCount = count($currentCluster) >= $maxPerRoute;

            $wouldExceedMiles = false;
            $wouldExceedDuration = false;
            if ($lastPackage && $currentCluster) {
                $legMiles = $this->estimateLegDistance($lastPackage, $pkg);
                $legMinutes = $this->estimateTravelMinutes($lastPackage, $pkg, $params);
                $wouldExceedMiles = ($currentMiles + $legMiles) > $maxRouteMiles;
                $wouldExceedDuration = ($currentDuration + $legMinutes) > $maxRouteDuration;
            }

            $wouldExceedRadius = false;
            if ($currentCluster) {
                $clusterCenter = $this->calculateClusterCenter($currentCluster);
                $distToCenter = $this->haversineDistance(
                    $clusterCenter['lat'], $clusterCenter['lng'],
                    $pkg->dropoff_lat ?? 0, $pkg->dropoff_lng ?? 0
                );
                $wouldExceedRadius = $distToCenter > $radiusMiles;
            }

            if ($currentCluster && ($wouldExceedCount || $wouldExceedMiles || $wouldExceedDuration || $wouldExceedRadius)) {
                $clusters[] = $currentCluster;
                $currentCluster = [];
                $currentMiles = 0.0;
                $currentDuration = 0.0;
                $lastPackage = null;
            }

            if ($lastPackage) {
                $currentMiles += $this->estimateLegDistance($lastPackage, $pkg);
                $currentDuration += $this->estimateTravelMinutes($lastPackage, $pkg, $params);
            }

            $currentCluster[] = $pkg;
            $lastPackage = $pkg;
        }

        if (!empty($currentCluster)) {
            $clusters[] = $currentCluster;
        }

        return $clusters;
    }

    private function assignLockedPackages(array &$clusters, Collection $locked, array $params): void
    {
        if ($locked->isEmpty()) {
            return;
        }

        foreach ($locked as $pkg) {
            $bestCluster = 0;
            $bestDistance = PHP_FLOAT_MAX;

            $startLat = $params['start_location']['lat'] ?? $pkg->dropoff_lat;
            $startLng = $params['start_location']['lng'] ?? $pkg->dropoff_lng;

            foreach ($clusters as $index => $cluster) {
                if (empty($cluster)) {
                    $bestCluster = $index;
                    break;
                }

                $center = $this->calculateClusterCenter($cluster);
                $dist = $this->haversineDistance(
                    $startLat, $startLng,
                    $center['lat'], $center['lng']
                );

                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $bestCluster = $index;
                }
            }

            $clusters[$bestCluster][] = $pkg;
        }
    }

    private function enforceMaxCapacity(array &$clusters, array $params): void
    {
        $maxPerRoute = $params['maximum_packages_per_route'] ?? 50;
        $overflow = [];

        foreach ($clusters as $index => &$cluster) {
            if (count($cluster) > $maxPerRoute) {
                $overflow = array_merge($overflow, array_slice($cluster, $maxPerRoute));
                $cluster = array_slice($cluster, 0, $maxPerRoute);
            }
        }
        unset($cluster);

        if (!empty($overflow)) {
            $clusters[] = $overflow;
        }
    }

    private function identifyUnrouteable(Collection $allPackages, array $clusters, array $params): Collection
    {
        $routedIds = [];
        foreach ($clusters as $cluster) {
            foreach ($cluster as $pkg) {
                $routedIds[] = $pkg->id;
            }
        }

        $unrouteable = $allPackages->reject(function ($pkg) use ($routedIds) {
            return in_array($pkg->id, $routedIds);
        });

        $maxMiles = $params['maximum_route_miles'] ?? 150;
        $startLat = $params['start_location']['lat'] ?? null;
        $startLng = $params['start_location']['lng'] ?? null;

        if ($startLat && $startLng) {
            $unrouteable = $unrouteable->merge(
                $allPackages->filter(function ($pkg) use ($startLat, $startLng, $maxMiles) {
                    if (!$pkg->dropoff_lat || !$pkg->dropoff_lng) {
                        return true;
                    }
                    $dist = $this->haversineDistance($startLat, $startLng, $pkg->dropoff_lat, $pkg->dropoff_lng);
                    return ($dist * 1.3) > $maxMiles;
                })
            );
        }

        $noCoords = $allPackages->filter(fn($pkg) => !$pkg->dropoff_lat || !$pkg->dropoff_lng);
        $unrouteable = $unrouteable->merge($noCoords)->unique('id');

        return $unrouteable->map(function ($pkg) {
            $reasons = [];
            if (!$pkg->dropoff_lat || !$pkg->dropoff_lng) {
                $reasons[] = 'missing_coordinates';
            }
            if ($pkg->status === 'admin_review') {
                $reasons[] = 'admin_review_required';
            }
            return [
                'package_id' => $pkg->id,
                'tracking_id' => $pkg->tracking_id,
                'dropoff_address' => $pkg->dropoff_address,
                'reasons' => $reasons,
            ];
        })->values();
    }

    private function optimizeClusterOrder(array $cluster, array $params): array
    {
        if (count($cluster) <= 1) {
            return $cluster;
        }

        $startLat = $params['start_location']['lat'] ?? $cluster[0]->dropoff_lat;
        $startLng = $params['start_location']['lng'] ?? $cluster[0]->dropoff_lng;

        $timeWindowPkgs = collect($cluster)->filter(fn($p) => $p->delivery_window_start && $p->delivery_window_end);
        $noWindowPkgs = collect($cluster)->reject(fn($p) => $p->delivery_window_start && $p->delivery_window_end);

        $ordered = [];

        if ($timeWindowPkgs->isNotEmpty()) {
            $sorted = $timeWindowPkgs->sortBy('delivery_window_start')->values();
            $ordered = $sorted->toArray();

            $remaining = $noWindowPkgs->toArray();
            if (!empty($remaining)) {
                $inserted = $this->insertByNearestNeighbor($ordered, $remaining, $startLat, $startLng);
                $ordered = $inserted;
            }
        } else {
            $ordered = $this->nearestNeighborSort($cluster, $startLat, $startLng);
        }

        $urgentFirst = array_filter($ordered, fn($p) => in_array($p->priority, ['urgent', 'medical']));
        $rest = array_filter($ordered, fn($p) => !in_array($p->priority, ['urgent', 'medical']));

        usort($urgentFirst, fn($a, $b) => $this->priorityWeight($b) <=> $this->priorityWeight($a));

        $finalOrdered = [];
        $urgentCount = count($urgentFirst);
        $insertPoint = min($urgentCount, count($ordered));

        $before = array_slice($ordered, 0, $insertPoint);
        $after = array_slice($ordered, $insertPoint);

        $finalOrdered = array_merge($before, array_values($urgentFirst), $after);

        return array_values($finalOrdered);
    }

    private function nearestNeighborSort(array $packages, float $startLat, float $startLng): array
    {
        $remaining = $packages;
        $sorted = [];
        $currentLat = $startLat;
        $currentLng = $startLng;

        while (!empty($remaining)) {
            $nearestIndex = 0;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $index => $pkg) {
                $lat = $pkg->dropoff_lat ?? $startLat;
                $lng = $pkg->dropoff_lng ?? $startLng;
                $dist = $this->haversineDistance($currentLat, $currentLng, $lat, $lng);
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIndex = $index;
                }
            }

            $sorted[] = $remaining[$nearestIndex];
            $currentLat = $remaining[$nearestIndex]->dropoff_lat ?? $startLat;
            $currentLng = $remaining[$nearestIndex]->dropoff_lng ?? $startLng;
            array_splice($remaining, $nearestIndex, 1);
        }

        return $sorted;
    }

    private function insertByNearestNeighbor(array $ordered, array $remaining, float $startLat, float $startLng): array
    {
        $result = $ordered;

        foreach ($remaining as $pkg) {
            $bestPos = count($result);
            $bestDist = PHP_FLOAT_MAX;

            for ($i = 0; $i <= count($result); $i++) {
                $lat = $i > 0 ? ($result[$i - 1]->dropoff_lat ?? $startLat) : $startLat;
                $lng = $i > 0 ? ($result[$i - 1]->dropoff_lng ?? $startLng) : $startLng;
                $dist = $this->haversineDistance($lat, $lng, $pkg->dropoff_lat ?? $startLat, $pkg->dropoff_lng ?? $startLng);

                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestPos = $i;
                }
            }

            array_splice($result, $bestPos, 0, [$pkg]);
        }

        return $result;
    }

    private function calculateRouteStats(array $orderedPackages, array $params): array
    {
        $startLat = $params['start_location']['lat'] ?? ($orderedPackages[0]->dropoff_lat ?? 0);
        $startLng = $params['start_location']['lng'] ?? ($orderedPackages[0]->dropoff_lng ?? 0);

        $totalMiles = 0.0;
        $totalDuration = 0.0;
        $prevLat = $startLat;
        $prevLng = $startLng;

        foreach ($orderedPackages as $pkg) {
            $lat = $pkg->dropoff_lat ?? 0;
            $lng = $pkg->dropoff_lng ?? 0;
            $legMiles = $this->haversineDistance($prevLat, $prevLng, $lat, $lng);
            $totalMiles += $legMiles;
            $totalDuration += ($legMiles / 30) * 60 + 10;
            $prevLat = $lat;
            $prevLng = $lng;
        }

        return [
            'package_count' => count($orderedPackages),
            'estimated_miles' => round($totalMiles, 2),
            'estimated_duration_minutes' => (int) round($totalDuration),
            'estimated_duration_hours' => round($totalDuration / 60, 1),
            'has_time_windows' => collect($orderedPackages)->contains(fn($p) => $p->delivery_window_start),
            'has_age_restricted' => collect($orderedPackages)->contains(fn($p) => $p->age_restricted),
            'has_medical' => collect($orderedPackages)->contains(fn($p) => $p->priority === 'medical'),
            'total_weight_lbs' => round(collect($orderedPackages)->sum('weight'), 2),
        ];
    }

    private function createAuditSnapshot(array $clusters, Collection $unrouteable, array $params, ?int $manifestId, ?int $clientId): UrbanGoodzRouteClusteringAudit
    {
        $originalPlan = [];
        foreach ($clusters as $cluster) {
            $originalPlan[] = array_map(fn($p) => [
                'package_id' => $p->id,
                'tracking_id' => $p->tracking_id,
                'dropoff_lat' => $p->dropoff_lat,
                'dropoff_lng' => $p->dropoff_lng,
                'priority' => $p->priority,
            ], $cluster['packages']);
        }

        return UrbanGoodzRouteClusteringAudit::create([
            'business_client_id' => $clientId,
            'manifest_id' => $manifestId,
            'clustering_params' => $params,
            'original_plan' => $originalPlan,
            'optimized_plan' => $originalPlan,
            'unrouteable_packages' => $unrouteable->toArray(),
            'total_packages' => array_sum(array_map(fn($c) => count($c['packages']), $clusters)),
            'routed_packages' => array_sum(array_map(fn($c) => count($c['packages']), $clusters)),
            'unrouteable_count' => $unrouteable->count(),
            'routes_generated' => count($clusters),
            'algorithm' => 'sweep_nearest_neighbor',
            'status' => 'generated',
        ]);
    }

    private function createSingleRoute(array $cluster, array $routeParams, array $clusteringParams): UrbanGoodzDedicatedRoute
    {
        $stats = $cluster['stats'];
        $firstPkg = $cluster['packages'][0];

        $route = UrbanGoodzDedicatedRoute::create(array_merge([
            'business_client_id' => $routeParams['business_client_id'] ?? $firstPkg->business_client_id,
            'route_name' => 'Route ' . ($cluster['cluster_index']) . ' - ' . now()->format('Y-m-d H:i'),
            'route_type' => $routeParams['route_type'] ?? 'bulk_delivery',
            'pickup_location' => $routeParams['pickup_location'] ?? '',
            'end_location' => $clusteringParams['end_location']['address'] ?? null,
            'pickup_lat' => $clusteringParams['start_location']['lat'] ?? $firstPkg->pickup_lat,
            'pickup_lng' => $clusteringParams['start_location']['lng'] ?? $firstPkg->pickup_lng,
            'end_lat' => $clusteringParams['end_location']['lat'] ?? null,
            'end_lng' => $clusteringParams['end_location']['lng'] ?? null,
            'scheduled_date' => $routeParams['scheduled_date'] ?? now()->toDateString(),
            'max_packages_per_batch' => $clusteringParams['maximum_packages_per_route'] ?? 50,
            'status' => 'draft',
            'vehicle_type_required' => $clusteringParams['vehicle_type'] ?? 'cargo_van',
            'total_packages' => $stats['package_count'],
            'completed_packages' => 0,
            'failed_packages' => 0,
            'estimated_miles' => $stats['estimated_miles'],
            'estimated_duration' => $stats['estimated_duration_minutes'],
            'contains_age_restricted_items' => $stats['has_age_restricted'],
            'driver_pay_per_package' => $routeParams['driver_pay_per_package'] ?? 5.00,
            'business_charge_per_package' => $routeParams['business_charge_per_package'] ?? 8.00,
        ], array_filter([
            'created_by' => $routeParams['created_by'] ?? null,
            'admin_notes' => $routeParams['admin_notes'] ?? null,
        ], fn($v) => $v !== null)));

        $stopOrder = 1;
        foreach ($cluster['packages'] as $pkg) {
            $prevPkg = $stopOrder > 1 ? $cluster['packages'][$stopOrder - 2] : null;
            $distFromPrev = $prevPkg
                ? $this->haversineDistance($prevPkg->dropoff_lat, $prevPkg->dropoff_lng, $pkg->dropoff_lat, $pkg->dropoff_lng)
                : 0;
            $durFromPrev = $this->estimateTravelMinutes($prevPkg, $pkg, $clusteringParams);

            UrbanGoodzRouteOptimizationStop::create([
                'dedicated_route_id' => $route->id,
                'package_id' => $pkg->id,
                'stop_order' => $stopOrder,
                'estimated_distance_from_prev' => round($distFromPrev, 2),
                'estimated_duration_from_prev' => (int) round($durFromPrev),
                'status' => 'pending',
            ]);

            $pkg->update([
                'dedicated_route_id' => $route->id,
                'stop_order' => $stopOrder,
                'status' => 'assigned',
            ]);

            $stopOrder++;
        }

        return $route;
    }

    private function clearRouteAssignments(int $routeId): void
    {
        UrbanGoodzRouteOptimizationStop::where('dedicated_route_id', $routeId)->delete();
        UrbanGoodzRouteBatch::where('dedicated_route_id', $routeId)->delete();

        UrbanGoodzRoutePackage::where('dedicated_route_id', $routeId)
            ->update([
                'dedicated_route_id' => null,
                'route_batch_id' => null,
                'stop_order' => null,
                'status' => 'pending',
            ]);
    }

    private function applyClusterToRoute(UrbanGoodzDedicatedRoute $route, array $result): void
    {
        if (!empty($result['clusters'])) {
            $firstCluster = $result['clusters'][0];
            $route->update([
                'total_packages' => $firstCluster['stats']['package_count'],
                'estimated_miles' => $firstCluster['stats']['estimated_miles'],
                'estimated_duration' => $firstCluster['stats']['estimated_duration_minutes'],
            ]);

            $stopOrder = 1;
            foreach ($firstCluster['packages'] as $pkg) {
                UrbanGoodzRouteOptimizationStop::create([
                    'dedicated_route_id' => $route->id,
                    'package_id' => $pkg->id,
                    'stop_order' => $stopOrder,
                    'estimated_distance_from_prev' => 0,
                    'estimated_duration_from_prev' => 0,
                    'status' => 'pending',
                ]);

                $pkg->update([
                    'dedicated_route_id' => $route->id,
                    'stop_order' => $stopOrder,
                ]);

                $stopOrder++;
            }
        }
    }

    private function flagUnrouteablePackages(Collection $unrouteable): void
    {
        foreach ($unrouteable as $item) {
            UrbanGoodzRoutePackage::where('id', $item['package_id'])->update([
                'status' => 'admin_review',
                'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' [unrouteable: " . implode(',', $item['reasons']) . "]')"),
            ]);
        }
    }

    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3959;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusMiles * $c;
    }

    private function calculateBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLng = deg2rad($lng2 - $lng1);
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $y = sin($dLng) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLng);
        $bearing = rad2deg(atan2($y, $x));
        return ($bearing + 360) % 360;
    }

    private function calculateClusterCenter(array $packages): array
    {
        $latSum = 0;
        $lngSum = 0;
        $count = 0;

        foreach ($packages as $pkg) {
            if ($pkg->dropoff_lat && $pkg->dropoff_lng) {
                $latSum += $pkg->dropoff_lat;
                $lngSum += $pkg->dropoff_lng;
                $count++;
            }
        }

        if ($count === 0) {
            return ['lat' => 0, 'lng' => 0];
        }

        return [
            'lat' => $latSum / $count,
            'lng' => $lngSum / $count,
        ];
    }

    private function estimateLegDistance(?UrbanGoodzRoutePackage $from, UrbanGoodzRoutePackage $to): float
    {
        if (!$from || !$to->dropoff_lat || !$to->dropoff_lng) {
            return 0;
        }
        $fromLat = $from->dropoff_lat ?? 0;
        $fromLng = $from->dropoff_lng ?? 0;
        return $this->haversineDistance($fromLat, $fromLng, $to->dropoff_lat, $to->dropoff_lng);
    }

    private function estimateTravelMinutes(?UrbanGoodzRoutePackage $from, UrbanGoodzRoutePackage $to, array $params): float
    {
        if (!$from) {
            return 0;
        }
        $miles = $this->estimateLegDistance($from, $to);
        $speedMph = $params['average_speed_mph'] ?? 30;
        return ($miles / $speedMph) * 60;
    }

    private function priorityWeight(UrbanGoodzRoutePackage $pkg): int
    {
        return match($pkg->priority) {
            'medical' => 4,
            'urgent' => 3,
            'high' => 2,
            default => 1,
        };
    }
}
