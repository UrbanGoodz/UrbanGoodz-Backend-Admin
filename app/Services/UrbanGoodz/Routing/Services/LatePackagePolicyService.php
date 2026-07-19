<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzBatchPackageAudit;
use App\Models\UrbanGoodzIntakeBatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LatePackagePolicyService
{
    const POLICIES = [
        'add_to_unlocked_route',
        'create_overflow_route',
        'hold_for_next_wave',
        'dispatcher_review',
        'reoptimize_affected_routes',
        'reoptimize_full_batch',
    ];

    private DuplicateDetectionService $duplicates;

    public function __construct(?DuplicateDetectionService $duplicates = null)
    {
        $this->duplicates = $duplicates ?? new DuplicateDetectionService();
    }

    private function validateScope(UrbanGoodzIntakeBatch $batch, ?int $scopedBusinessId): void
    {
        if ($scopedBusinessId !== null && (int)$batch->business_client_id !== (int)$scopedBusinessId) {
            throw new AccessDeniedHttpException("Access denied to batch of business {$batch->business_client_id}");
        }
    }

    private function invalidateProgressCache(int $batchId): void
    {
        Cache::forget("batch_progress_{$batchId}");
    }

    public function handleLatePackage(
        int $batchId,
        array $packageData,
        string $policy,
        int $userId,
        ?string $deviceId = null,
        ?int $scopedBusinessId = null
    ): array {
        if (!in_array($policy, self::POLICIES)) {
            throw new \InvalidArgumentException("Unknown late package policy: {$policy}");
        }

        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        if (!$batch->canAcceptLatePackage()) {
            throw new \RuntimeException("Batch cannot accept late packages. Status: {$batch->status}");
        }

        // Bypassing normal addPackage check to allow inserting package when batch is locked
        $package = new UrbanGoodzBatchPackage(array_merge($packageData, [
            'intake_batch_id' => $batchId,
            'business_client_id' => $batch->business_client_id,
            'created_by_user_id' => $userId,
            'device_session_id' => $deviceId,
            'source_type' => 'late_arrival',
            'route_assignment_status' => 'late',
        ]));

        $duplicateCheck = $this->duplicates->checkBeforeInsert($package, $batchId, $userId);

        if ($duplicateCheck['result'] !== DuplicateDetectionService::RESULT_NEW_PACKAGE) {
            $this->invalidateProgressCache($batchId);
            return [
                'success' => false,
                'duplicate_result' => $duplicateCheck['result'],
                'matches' => $duplicateCheck['matches'],
                'package' => null,
            ];
        }

        $package->save();
        $package->runValidation();

        $affectedRoutes = $this->getAffectedRoutes($batchId, $package);

        UrbanGoodzBatchPackageAudit::log(
            $package->id,
            $batchId,
            'late_package_added',
            $userId,
            null,
            [
                'policy' => $policy,
                'affected_route_ids' => array_map(fn($r) => $r->id, $affectedRoutes),
            ],
            null,
            null,
            $deviceId,
            "Late package policy: {$policy}"
        );

        $policyResult = match ($policy) {
            'add_to_unlocked_route' => $this->addToUnlockedRoute($batch, $package, $affectedRoutes),
            'create_overflow_route' => $this->createOverflowRoute($batch, $package, $userId),
            'hold_for_next_wave' => $this->holdForNextWave($package),
            'dispatcher_review' => $this->dispatcherReview($batch, $package, $userId),
            'reoptimize_affected_routes' => $this->reoptimizeAffectedRoutes($batch, $package, $affectedRoutes, $userId),
            'reoptimize_full_batch' => $this->reoptimizeFullBatch($batch, $package, $userId),
        };

        UrbanGoodzBatchPackageAudit::log(
            $package->id,
            $batchId,
            'late_policy_applied',
            $userId,
            null,
            ['policy' => $policy, 'result' => $policyResult],
            null,
            null,
            $deviceId,
            "Policy {$policy} applied"
        );

        $this->invalidateProgressCache($batchId);

        return [
            'success' => true,
            'package_id' => $package->id,
            'policy' => $policy,
            'affected_routes' => array_map(fn($r) => ['id' => $r->id, 'route_name' => $r->route_name], $affectedRoutes),
            'policy_result' => $policyResult,
        ];
    }

    private function getAffectedRoutes(int $batchId, UrbanGoodzBatchPackage $package): array
    {
        // Return array of Eloquent models, NOT array of arrays
        return \App\Models\UrbanGoodzDedicatedRoute::where('intake_batch_id', $batchId)
            ->where('status', '!=', 'completed')
            ->get()
            ->filter(function ($route) use ($package) {
                if (!$package->hasValidCoordinates()) return false;
                return true;
            })
            ->all();
    }

    private function addToUnlockedRoute(UrbanGoodzIntakeBatch $batch, UrbanGoodzBatchPackage $package, array $affectedRoutes): array
    {
        $unlockedRoutes = array_filter($affectedRoutes, fn($r) => $r->status !== 'in_progress');

        if (empty($unlockedRoutes)) {
            return ['action' => 'no_unlocked_routes_available', 'fallback' => 'dispatcher_review'];
        }

        $bestRoute = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach ($unlockedRoutes as $route) {
            $lastPackage = \App\Models\UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)
                ->orderByDesc('stop_order')
                ->first();

            if ($lastPackage && $package->hasValidCoordinates()) {
                $dist = $this->haversine(
                    (float)$lastPackage->dropoff_lat, (float)$lastPackage->dropoff_lng,
                    (float)$package->dropoff_lat, (float)$package->dropoff_lng
                );
            } else {
                $dist = 0;
            }

            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestRoute = $route;
            }
        }

        if ($bestRoute) {
            $package->update([
                'route_assignment_status' => 'assigned',
                'dedicated_route_id' => $bestRoute->id,
                'stop_order' => ($bestRoute->packages()->max('stop_order') ?? 0) + 1,
            ]);
            $bestRoute->increment('total_packages');

            return ['action' => 'added_to_route', 'route_id' => $bestRoute->id, 'route_name' => $bestRoute->route_name, 'estimated_extra_miles' => round($bestDist, 2)];
        }

        return ['action' => 'no_route_found'];
    }

    private function createOverflowRoute(UrbanGoodzIntakeBatch $batch, UrbanGoodzBatchPackage $package, int $userId): array
    {
        $route = \App\Models\UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $batch->business_client_id,
            'intake_batch_id' => $batch->id,
            'route_name' => "Overflow Route " . now()->format('Hi'),
            'route_label' => 'OVERFLOW',
            'total_packages' => 1,
            'scheduled_date' => $batch->service_date,
            'route_type' => 'late_overflow',
            'status' => 'planned',
            'created_by' => $userId,
        ]);

        $package->update([
            'route_assignment_status' => 'assigned',
            'dedicated_route_id' => $route->id,
            'stop_order' => 1,
        ]);

        return ['action' => 'overflow_route_created', 'route_id' => $route->id, 'route_name' => $route->route_name];
    }

    private function holdForNextWave(UrbanGoodzBatchPackage $package): array
    {
        $package->update(['route_assignment_status' => 'unassigned']);

        return ['action' => 'held_for_next_wave', 'package_id' => $package->id];
    }

    private function dispatcherReview(UrbanGoodzIntakeBatch $batch, UrbanGoodzBatchPackage $package, int $userId): array
    {
        $package->update(['validation_status' => 'needs_review']);

        UrbanGoodzBatchPackageAudit::log(
            $package->id,
            $batch->id,
            'review_assigned',
            $userId,
            null,
            ['assigned_to' => 'dispatcher', 'reason' => 'late_package'],
            null,
            null,
            null,
            'Late package requires dispatcher review'
        );

        return ['action' => 'queued_for_dispatcher', 'package_id' => $package->id];
    }

    private function reoptimizeAffectedRoutes(UrbanGoodzIntakeBatch $batch, UrbanGoodzBatchPackage $package, array $affectedRoutes, int $userId): array
    {
        $results = [];

        foreach ($affectedRoutes as $route) {
            $packages = \App\Models\UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)->get();
            if ($packages->isNotEmpty()) {
                $planning = new RoutePlanningService();
                $result = $planning->planFromPool($packages->toArray(), ['requested_route_count' => 1]);
                $results[] = ['route_id' => $route->id, 'new_miles' => $result->clusters[0]->estimatedMiles ?? 0];
            }
        }

        return ['action' => 'reoptimized', 'routes' => $results];
    }

    private function reoptimizeFullBatch(UrbanGoodzIntakeBatch $batch, UrbanGoodzBatchPackage $package, int $userId): array
    {
        $allPackages = $batch->activePackages()->get();

        $planning = new RoutePlanningService();
        $result = $planning->planFromPool($allPackages->toArray(), [
            'business_client_id' => $batch->business_client_id,
        ]);

        return [
            'action' => 'full_batch_reoptimized',
            'route_count' => $result->routeCountGenerated,
            'metrics' => $result->metrics->toArray(),
        ];
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMiles = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * asin(sqrt($a));
        return $earthRadiusMiles * $c;
    }
}
