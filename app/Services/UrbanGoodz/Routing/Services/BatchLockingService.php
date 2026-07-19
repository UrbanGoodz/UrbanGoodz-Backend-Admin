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

        $result = $this->planning->planFromPool($packageData, array_merge($params, [
            'business_client_id' => $batch->business_client_id,
            'batch_id' => $batch->id,
        ]));

        $batch->markRoutesGenerated();

        return [
            'route_count' => $result->routeCountGenerated,
            'routed_packages' => $result->routedPackages,
            'unrouteable_count' => $result->unrouteableCount,
            'overall_distance_mode' => $result->overallDistanceMode,
            'clusters' => array_map(fn($c) => $c->toSummaryArray(), $result->clusters),
            'metrics' => $result->metrics->toArray(),
            'audit_id' => $result->auditId,
        ];
    }
}
