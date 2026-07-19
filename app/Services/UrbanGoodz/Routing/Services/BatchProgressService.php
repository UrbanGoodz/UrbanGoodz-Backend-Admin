<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackageAudit;
use App\Models\UrbanGoodzBatchParticipant;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzBatchPackage;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BatchProgressService
{
    const CACHE_TTL_SECONDS = 30;

    private function validateScope(UrbanGoodzIntakeBatch $batch, ?int $scopedBusinessId): void
    {
        if ($scopedBusinessId !== null && (int)$batch->business_client_id !== (int)$scopedBusinessId) {
            throw new AccessDeniedHttpException("Access denied to batch of business {$batch->business_client_id}");
        }
    }

    public function getProgress(int $batchId, ?int $scopedBusinessId = null): array
    {
        $cacheKey = "batch_progress_{$batchId}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // Re-verify scope even for cached content
            $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
            $this->validateScope($batch, $scopedBusinessId);
            return $cached;
        }

        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        $progress = $batch->getProgress();

        $progress['participants'] = $this->getParticipants($batchId, $scopedBusinessId);
        $progress['validation_queue'] = $this->getValidationQueue($batchId, $scopedBusinessId);
        $progress['intake_sources'] = $this->getIntakeSourceBreakdown($batchId, $scopedBusinessId);
        $progress['duplicates_summary'] = $this->getDuplicatesSummary($batchId, $scopedBusinessId);

        Cache::put($cacheKey, $progress, self::CACHE_TTL_SECONDS);

        return $progress;
    }

    public function invalidateCache(int $batchId): void
    {
        Cache::forget("batch_progress_{$batchId}");
    }

    public function getParticipants(int $batchId, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        return UrbanGoodzBatchParticipant::where('intake_batch_id', $batchId)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($p) => [
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? 'Unknown',
                'email' => $p->user?->email,
                'role' => $p->role,
                'source_portal' => $p->source_portal,
                'is_active' => $p->is_active,
                'joined_at' => $p->joined_at?->toIso8601String(),
                'last_active_at' => $p->last_active_at?->toIso8601String(),
                'packages_created' => $p->packages_created,
                'packages_edited' => $p->packages_edited,
                'validation_actions' => $p->validation_actions,
                'approval_actions' => $p->approval_actions,
            ])
            ->toArray();
    }

    public function getValidationQueue(int $batchId, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        return [
            'needs_review_count' => $batch->packagesNeedingReview()->count(),
            'pending_validation_count' => $batch->activePackages()->where('validation_status', 'pending')->count(),
            'invalid_count' => $batch->activePackages()->where('validation_status', 'invalid')->count(),
            'items' => $batch->packagesNeedingReview()
                ->with('createdBy:id,name')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'tracking_id' => $p->tracking_id,
                    'barcode' => $p->barcode,
                    'dropoff_address' => $p->dropoff_address,
                    'dropoff_city' => $p->dropoff_city,
                    'validation_errors' => $p->validation_errors,
                    'created_by' => $p->createdBy?->name ?? 'Unknown',
                    'created_at' => $p->created_at?->toIso8601String(),
                    'source_type' => $p->source_type,
                ])
                ->toArray(),
        ];
    }

    public function getIntakeSourceBreakdown(int $batchId, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        return UrbanGoodzBatchPackage::where('intake_batch_id', $batchId)
            ->where('is_active', true)
            ->selectRaw('source_type, COUNT(*) as count')
            ->groupBy('source_type')
            ->get()
            ->pluck('count', 'source_type')
            ->toArray();
    }

    public function getDuplicatesSummary(int $batchId, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        $packages = UrbanGoodzBatchPackage::where('intake_batch_id', $batchId)
            ->where('is_active', true)
            ->get();

        $possible = $packages->where('duplicate_status', 'possible_duplicate')->count();
        $confirmed = $packages->where('duplicate_status', 'confirmed_duplicate')->count();
        $otherBatch = $packages->where('duplicate_status', 'active_in_other_batch')->count();

        return [
            'possible_duplicates' => $possible,
            'confirmed_duplicates' => $confirmed,
            'active_in_other_batch' => $otherBatch,
            'total_flagged' => $possible + $confirmed + $otherBatch,
        ];
    }

    public function getWorkerActivity(int $batchId, int $minutes = 60, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        $since = now()->subMinutes($minutes);

        return UrbanGoodzBatchPackageAudit::where('intake_batch_id', $batchId)
            ->where('created_at', '>=', $since)
            ->with('user:id,name')
            ->selectRaw('user_id, action, COUNT(*) as count')
            ->groupBy('user_id', 'action')
            ->get()
            ->groupBy('user_id')
            ->map(function ($actions, $userId) {
                return [
                    'user_id' => $userId,
                    'user_name' => $actions->first()->user?->name ?? 'Unknown',
                    'actions' => $actions->mapWithKeys(fn($a) => [$a['action'] => $a['count']])->toArray(),
                    'total_actions' => $actions->sum('count'),
                ];
            })
            ->values()
            ->toArray();
    }
}
