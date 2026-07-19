<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzBatchPackageAudit;
use App\Models\UrbanGoodzIntakeBatchAudit;
use App\Models\UrbanGoodzBatchParticipant;
use App\Models\UrbanGoodzIntakeBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BatchIntakeService
{
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

    // --- Batch lifecycle ---

    public function createBatch(array $data, int $userId, ?int $scopedBusinessId = null): UrbanGoodzIntakeBatch
    {
        if ($scopedBusinessId !== null && (int)$data['business_client_id'] !== (int)$scopedBusinessId) {
            throw new AccessDeniedHttpException("Cannot create batch for another business client.");
        }

        return DB::transaction(function () use ($data, $userId) {
            $batch = UrbanGoodzIntakeBatch::create([
                'business_client_id' => $data['business_client_id'],
                'business_location_id' => $data['business_location_id'] ?? null,
                'batch_name' => $data['batch_name'] ?? null,
                'service_date' => $data['service_date'] ?? now()->toDateString(),
                'intake_start_time' => $data['intake_start_time'] ?? null,
                'intake_cutoff_time' => $data['intake_cutoff_time'] ?? null,
                'expected_package_count' => $data['expected_package_count'] ?? 0,
                'routing_policy' => $data['routing_policy'] ?? 'standard',
                'created_by_user_id' => $userId,
                'supervisor_user_id' => $data['supervisor_user_id'] ?? null,
                'dispatcher_user_id' => $data['dispatcher_user_id'] ?? null,
                'status' => UrbanGoodzIntakeBatch::STATUS_DRAFT,
                'is_locked' => false,
            ]);

            UrbanGoodzBatchParticipant::create([
                'intake_batch_id' => $batch->id,
                'user_id' => $userId,
                'role' => 'intake_supervisor',
                'source_portal' => $data['source_portal'] ?? 'admin',
            ]);

            UrbanGoodzIntakeBatchAudit::log($batch->id, 'created', $userId, null, $batch->toArray());

            Log::info('BatchIntakeService: batch created', ['batch_id' => $batch->id, 'user_id' => $userId]);

            return $batch;
        });
    }

    public function openBatch(int $batchId, int $userId, ?int $scopedBusinessId = null): UrbanGoodzIntakeBatch
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        if ($batch->status !== UrbanGoodzIntakeBatch::STATUS_DRAFT) {
            throw new \RuntimeException("Cannot open batch in status: {$batch->status}");
        }

        $oldStatus = $batch->status;
        $batch->openForIntake();

        UrbanGoodzIntakeBatchAudit::log(
            $batchId,
            'opened',
            $userId,
            ['status' => $oldStatus],
            ['status' => UrbanGoodzIntakeBatch::STATUS_OPEN_FOR_INTAKE]
        );

        $this->invalidateProgressCache($batchId);

        Log::info('BatchIntakeService: batch opened', ['batch_id' => $batchId]);

        return $batch;
    }

    // --- Participant management ---

    public function joinBatch(
        int $batchId,
        int $userId,
        string $role,
        ?string $deviceId = null,
        ?string $portal = null,
        ?int $scopedBusinessId = null
    ): UrbanGoodzBatchParticipant {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        if (!$batch->isIntakeOpen() && $role !== 'admin') {
            throw new \RuntimeException("Batch is not open for intake");
        }

        $participant = UrbanGoodzBatchParticipant::firstOrCreate(
            [
                'intake_batch_id' => $batchId,
                'user_id' => $userId,
                'device_session_id' => $deviceId,
            ],
            [
                'role' => $role,
                'source_portal' => $portal,
                'joined_at' => now(),
                'is_active' => true,
            ]
        );

        $participant->touchActive();
        $this->invalidateProgressCache($batchId);

        return $participant;
    }

    // --- Package CRUD ---

    public function addPackage(int $batchId, array $data, int $userId, ?string $deviceId = null, ?int $scopedBusinessId = null): array
    {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        if (!$batch->canAddPackages()) {
            throw new \RuntimeException("Batch does not accept new packages. Status: {$batch->status}");
        }

        $candidate = new UrbanGoodzBatchPackage(array_merge($data, [
            'intake_batch_id' => $batchId,
            'business_client_id' => $batch->business_client_id,
            'created_by_user_id' => $userId,
            'scanned_by_user_id' => ($data['source_type'] ?? 'manual_entry') === 'barcode_scan' ? $userId : null,
            'device_session_id' => $deviceId,
        ]));

        $duplicateCheck = $this->duplicates->checkBeforeInsert($candidate, $batchId, $userId);

        if ($duplicateCheck['result'] !== DuplicateDetectionService::RESULT_NEW_PACKAGE) {
            $this->invalidateProgressCache($batchId);
            return [
                'success' => false,
                'duplicate_result' => $duplicateCheck['result'],
                'matches' => $duplicateCheck['matches'],
                'package' => null,
            ];
        }

        $candidate->save();
        $candidate->runValidation();

        UrbanGoodzBatchPackageAudit::log(
            $candidate->id,
            $batchId,
            'created',
            $userId,
            null,
            $candidate->toArray(),
            null,
            1,
            $deviceId,
            "Source: " . ($data['source_type'] ?? 'manual_entry')
        );

        $this->incrementParticipant($batchId, $userId, 'created');
        $this->invalidateProgressCache($batchId);

        Log::info('BatchIntakeService: package added', [
            'batch_id' => $batchId,
            'package_id' => $candidate->id,
            'tracking_id' => $candidate->tracking_id,
            'user_id' => $userId,
        ]);

        return [
            'success' => true,
            'duplicate_result' => DuplicateDetectionService::RESULT_NEW_PACKAGE,
            'matches' => [],
            'package' => $candidate,
        ];
    }

    public function updatePackage(int $packageId, array $data, int $userId, ?string $deviceId = null, ?int $scopedBusinessId = null): array
    {
        $package = UrbanGoodzBatchPackage::findOrFail($packageId);
        $batch = $package->batch;
        $this->validateScope($batch, $scopedBusinessId);

        if (!$batch->canEditPackage($userId)) {
            throw new \RuntimeException("Cannot edit package. Batch is locked or not open.");
        }

        $oldValues = $package->toArray();
        $versionBefore = $package->version;

        $expectedVersion = $data['version'] ?? null;
        if ($expectedVersion !== null && (int)$package->version !== (int)$expectedVersion) {
            $conflict = $package->getConflictInfo();

            UrbanGoodzBatchPackageAudit::logConflict(
                $packageId,
                $package->intake_batch_id,
                $package->modified_by_user_id ?? 0,
                $package->updated_at->toIso8601String(),
                $userId
            );

            Log::warning('BatchIntakeService: version conflict', [
                'package_id' => $packageId,
                'user_id' => $userId,
                'conflict' => $conflict,
            ]);

            $this->invalidateProgressCache($package->intake_batch_id);

            return [
                'success' => false,
                'error' => 'CONFLICT',
                'conflict' => $conflict,
            ];
        }

        $saved = $package->updateWithOwnership($data, $userId);

        if (!$saved) {
            $conflict = $package->getConflictInfo();

            UrbanGoodzBatchPackageAudit::logConflict(
                $packageId,
                $package->intake_batch_id,
                $package->modified_by_user_id ?? 0,
                $package->updated_at->toIso8601String(),
                $userId
            );

            Log::warning('BatchIntakeService: version conflict', [
                'package_id' => $packageId,
                'user_id' => $userId,
                'conflict' => $conflict,
            ]);

            $this->invalidateProgressCache($package->intake_batch_id);

            return [
                'success' => false,
                'error' => 'CONFLICT',
                'conflict' => $conflict,
            ];
        }

        $package->runValidation();

        $changedFields = array_diff_key($data, array_flip(['modified_by_user_id', 'version']));

        UrbanGoodzBatchPackageAudit::log(
            $packageId,
            $package->intake_batch_id,
            'updated',
            $userId,
            array_intersect_key($oldValues, $changedFields),
            $changedFields,
            $versionBefore,
            $package->version,
            $deviceId
        );

        $this->incrementParticipant($package->intake_batch_id, $userId, 'edited');
        $this->invalidateProgressCache($package->intake_batch_id);

        return [
            'success' => true,
            'package' => $package->fresh(),
        ];
    }

    public function bulkImport(
        array $packages,
        int $batchId,
        int $userId,
        string $sourceType = 'csv_import',
        ?string $fileRef = null,
        ?int $scopedBusinessId = null
    ): array {
        $batch = UrbanGoodzIntakeBatch::findOrFail($batchId);
        $this->validateScope($batch, $scopedBusinessId);

        if (!$batch->canAddPackages()) {
            throw new \RuntimeException("Batch does not accept new packages.");
        }

        $results = [
            'total' => count($packages),
            'created' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($packages, $batchId, $userId, $sourceType, $fileRef, &$results, $scopedBusinessId) {
            foreach ($packages as $idx => $pkgData) {
                $pkgData['source_type'] = $sourceType;
                $pkgData['source_file_ref'] = $fileRef;

                try {
                    $result = $this->addPackage($batchId, $pkgData, $userId, null, $scopedBusinessId);

                    if ($result['success']) {
                        $results['created']++;
                    } else {
                        $results['duplicates']++;
                        $results['errors'][] = [
                            'row' => $idx + 1,
                            'tracking_id' => $pkgData['tracking_id'] ?? null,
                            'reason' => $result['duplicate_result'],
                        ];
                    }
                } catch (\Exception $e) {
                    $results['invalid']++;
                    $results['errors'][] = [
                        'row' => $idx + 1,
                        'tracking_id' => $pkgData['tracking_id'] ?? null,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        $this->invalidateProgressCache($batchId);

        Log::info('BatchIntakeService: bulk import complete', [
            'batch_id' => $batchId,
            'results' => $results,
        ]);

        return $results;
    }

    // --- Review queue ---

    public function assignReview(int $packageId, int $assigneeId, int $assignedBy, ?int $scopedBusinessId = null): void
    {
        $package = UrbanGoodzBatchPackage::findOrFail($packageId);
        $batch = $package->batch;
        $this->validateScope($batch, $scopedBusinessId);

        $package->update(['validation_status' => 'needs_review']);

        UrbanGoodzBatchPackageAudit::log(
            $packageId,
            $package->intake_batch_id,
            'review_assigned',
            $assignedBy,
            null,
            ['assigned_to' => $assigneeId],
            $package->version,
            null,
            null,
            "Assigned to user {$assigneeId}"
        );

        $this->incrementParticipant($package->intake_batch_id, $assignedBy, 'validation');
        $this->invalidateProgressCache($package->intake_batch_id);
    }

    public function completeReview(
        int $packageId,
        int $reviewerId,
        string $resolution,
        ?array $correctedData = null,
        ?int $scopedBusinessId = null
    ): void {
        $package = UrbanGoodzBatchPackage::findOrFail($packageId);
        $batch = $package->batch;
        $this->validateScope($batch, $scopedBusinessId);

        $oldValues = $package->toArray();

        match ($resolution) {
            'approve' => $package->update(['validation_status' => 'valid']),
            'correct' => $package->updateWithOwnership(array_merge($correctedData ?? [], ['validation_status' => 'valid']), $reviewerId),
            'reject' => $package->update(['is_active' => false, 'validation_status' => 'invalid']),
            default => throw new \InvalidArgumentException("Unknown resolution: {$resolution}"),
        };

        UrbanGoodzBatchPackageAudit::log(
            $packageId,
            $package->intake_batch_id,
            'review_completed',
            $reviewerId,
            ['validation_status' => $oldValues['validation_status']],
            ['resolution' => $resolution],
            $package->version,
            null,
            $package->device_session_id,
            "Resolution: {$resolution}"
        );

        $this->incrementParticipant($package->intake_batch_id, $reviewerId, 'approval');
        $this->invalidateProgressCache($package->intake_batch_id);
    }

    // --- Helpers ---

    private function incrementParticipant(int $batchId, int $userId, string $action): void
    {
        $participant = UrbanGoodzBatchParticipant::where('intake_batch_id', $batchId)
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            match ($action) {
                'created' => $participant->incrementCreated(),
                'edited' => $participant->incrementEdited(),
                'validation' => $participant->incrementValidation(),
                'approval' => $participant->incrementApproval(),
                default => null,
            };
        }
    }
}
