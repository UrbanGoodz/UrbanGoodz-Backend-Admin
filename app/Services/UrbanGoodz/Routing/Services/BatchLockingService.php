<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzIntakeBatchAudit;
use App\Models\UrbanGoodzIntakeBatch;
use App\Services\UrbanGoodz\Routing\DTOs\ClusteringConstraints;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BatchLockingService
{
    private RoutePlanningService $planning;

    public function __construct(?RoutePlanningService $planning = null)
    {
        $this->planning = $planning ?? new RoutePlanningService();
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

    public function lockForRouting(int $batchId, int $userId, array $planningParams = [], ?int $scopedBusinessId = null): array
    {
        return DB::transaction(function () use ($batchId, $userId, $planningParams, $scopedBusinessId) {
            // Use lockForUpdate to ensure pessimistic concurrency at database level
            $batch = UrbanGoodzIntakeBatch::lockForUpdate()->findOrFail($batchId);
            $this->validateScope($batch, $scopedBusinessId);

            if (!$batch->canLockForRouting($userId)) {
                throw new \RuntimeException("Cannot lock batch. Status: {$batch->status}, locked: {$batch->is_locked}");
            }

            $activePackages = $batch->activePackages()->where('validation_status', '!=', 'invalid')->get();

            if ($activePackages->isEmpty()) {
                throw new \RuntimeException("No valid packages to route");
            }

            $oldStatus = $batch->status;
            $batch->lockForRouting($userId);

            UrbanGoodzIntakeBatchAudit::log(
                $batchId,
                'batch_locked',
                $userId,
                ['status' => $oldStatus],
                ['status' => UrbanGoodzIntakeBatch::STATUS_LOCKED_FOR_ROUTING, 'package_count' => $activePackages->count()],
                null,
                "Locked by user {$userId} with " . $activePackages->count() . " valid packages"
            );

            Log::info('BatchLockingService: batch locked', [
                'batch_id' => $batchId,
                'user_id' => $userId,
                'package_count' => $activePackages->count(),
            ]);

            $this->invalidateProgressCache($batchId);

            $routingResult = $this->generateRoutes($batch, $activePackages, $userId, $planningParams);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'locked_at' => now()->toIso8601String(),
                'package_count' => $activePackages->count(),
                'routing' => $routingResult,
            ];
        });
    }

    public function unlockBatch(int $batchId, int $userId, ?string $reason = null, ?int $scopedBusinessId = null): UrbanGoodzIntakeBatch
    {
        return DB::transaction(function () use ($batchId, $userId, $reason, $scopedBusinessId) {
            $batch = UrbanGoodzIntakeBatch::lockForUpdate()->findOrFail($batchId);
            $this->validateScope($batch, $scopedBusinessId);

            if (!$batch->is_locked) {
                throw new \RuntimeException("Batch is not locked");
            }

            $oldStatus = $batch->status;
            $batch->unlock();

            UrbanGoodzIntakeBatchAudit::log(
                $batchId,
                'batch_unlocked',
                $userId,
                ['is_locked' => true, 'status' => $oldStatus],
                ['is_locked' => false, 'status' => UrbanGoodzIntakeBatch::STATUS_OPEN_FOR_INTAKE],
                null,
                $reason
            );

            $this->invalidateProgressCache($batchId);

            Log::info('BatchLockingService: batch unlocked', ['batch_id' => $batchId, 'user_id' => $userId, 'reason' => $reason]);

            return $batch->fresh();
        });
    }

    private function generateRoutes(UrbanGoodzIntakeBatch $batch, $packages, int $userId, array $params): array
    {
        $packageData = $packages->map(fn($p) => (array)$p->toArray())->toArray();

        // 1. Run the planning pipeline with persist flag
        $result = $this->planning->planFromPool($packageData, array_merge($params, [
            'business_client_id' => $batch->business_client_id,
            'batch_id' => $batch->id,
            'persist' => true,
        ]));

        $batch->markRoutesGenerated();

        // 2. Create actual routes and link package assignments
        $routes = [];
        $sameAddressGroups = $result->sameAddressGroups;

        $firstPkg = $packages->first();
        foreach ($result->clusters as $cluster) {
            $route = \App\Models\UrbanGoodzDedicatedRoute::create([
                'business_client_id' => $batch->business_client_id,
                'intake_batch_id' => $batch->id,
                'route_name' => "Route {$cluster->label}",
                'route_label' => $cluster->label,
                'total_packages' => $cluster->packageCount,
                'estimated_miles' => $cluster->estimatedMiles,
                'estimated_duration' => (int)$cluster->estimatedDurationMinutes,
                'scheduled_date' => $batch->service_date,
                'route_type' => $params['route_type'] ?? 'bulk_delivery',
                'status' => 'planned',
                'created_by' => $userId,
                'pickup_lat' => $firstPkg ? $firstPkg->pickup_lat : null,
                'pickup_lng' => $firstPkg ? $firstPkg->pickup_lng : null,
                'pickup_location' => $firstPkg ? $firstPkg->pickup_address : null,
                'end_lat' => $firstPkg ? $firstPkg->pickup_lat : null,
                'end_lng' => $firstPkg ? $firstPkg->pickup_lng : null,
                'end_location' => $firstPkg ? $firstPkg->pickup_address : null,
            ]);

            $stopOrder = 1;
            foreach ($cluster->stops as $stop) {
                // Determine which packages to assign (consolidated vs single)
                $packageIds = [];
                if ($stop->sameAddressGroupId !== null) {
                    // Find group in sameAddressGroups
                    foreach ($sameAddressGroups as $group) {
                        if ($group['group_id'] === $stop->sameAddressGroupId) {
                            $packageIds = $group['package_ids'];
                            break;
                        }
                    }
                } else {
                    $packageIds = [$stop->packageId];
                }

                // Update packages
                foreach ($packageIds as $pId) {
                    $batchPkg = \App\Models\UrbanGoodzBatchPackage::find($pId);
                    
                    // Create Route Package copy for driver API compatibility
                    $routePkg = \App\Models\UrbanGoodzRoutePackage::create([
                        'dedicated_route_id' => $route->id,
                        'business_client_id' => $batch->business_client_id,
                        'tracking_id' => $batchPkg->tracking_id,
                        'barcode' => $batchPkg->barcode,
                        'dropoff_name' => $batchPkg->recipient_name,
                        'dropoff_address' => $batchPkg->dropoff_address ?? '',
                        'dropoff_city' => $batchPkg->dropoff_city,
                        'dropoff_state' => $batchPkg->dropoff_state,
                        'dropoff_zip' => $batchPkg->dropoff_zip,
                        'dropoff_phone' => $batchPkg->recipient_phone,
                        'dropoff_lat' => $batchPkg->dropoff_lat,
                        'dropoff_lng' => $batchPkg->dropoff_lng,
                        'delivery_window_start' => $batchPkg->delivery_window_start,
                        'delivery_window_end' => $batchPkg->delivery_window_end,
                        'package_type' => $batchPkg->package_type,
                        'weight' => $batchPkg->weight_lbs,
                        'priority' => $batchPkg->priority,
                        'requires_signature' => $batchPkg->requires_signature ?? false,
                        'requires_photo' => $batchPkg->requires_photo ?? false,
                        'requires_custody' => $batchPkg->requires_custody ?? false,
                        'age_restricted' => $batchPkg->age_restricted ?? false,
                        'delivery_completion_locked_until_verified' => $batchPkg->delivery_completion_locked_until_verified ?? false,
                        'status' => 'pending',
                        'stop_order' => $stopOrder,
                    ]);

                    $batchPkg->update([
                        'route_assignment_status' => 'assigned',
                        'dedicated_route_id' => $route->id,
                        'stop_order' => $stopOrder,
                    ]);

                    // Create Optimization Stop record
                    \App\Models\UrbanGoodzRouteOptimizationStop::create([
                        'dedicated_route_id' => $route->id,
                        'package_id' => $routePkg->id,
                        'stop_order' => $stopOrder,
                        'estimated_distance_from_prev' => 0.0,
                        'estimated_duration_from_prev' => 0,
                        'status' => 'pending',
                    ]);

                    $stopOrder++;
                }
            }

            $routes[] = [
                'id' => $route->id,
                'label' => $cluster->label,
                'package_count' => $cluster->packageCount,
                'estimated_miles' => $cluster->estimatedMiles,
            ];
        }

        // Handle unrouteable packages
        foreach ($result->unrouteable as $unr) {
            \App\Models\UrbanGoodzBatchPackage::where('id', $unr['package_id'])->update([
                'route_assignment_status' => 'unassigned',
                'validation_status' => 'invalid',
            ]);
        }

        return [
            'route_count' => $result->routeCountGenerated,
            'routed_packages' => $result->routedPackages,
            'unrouteable_count' => $result->unrouteableCount,
            'overall_distance_mode' => $result->overallDistanceMode,
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $result->clusters),
            'metrics' => $result->metrics->toArray(),
            'audit_id' => $result->auditId,
            'routes' => $routes,
        ];
    }
}
