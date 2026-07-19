<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzBatchPackageAudit;
use Illuminate\Support\Facades\DB;

class DuplicateDetectionService
{
    const RESULT_NEW_PACKAGE = 'NEW_PACKAGE';
    const RESULT_ALREADY_IN_BATCH = 'ALREADY_IN_BATCH';
    const RESULT_POSSIBLE_DUPLICATE = 'POSSIBLE_DUPLICATE';
    const RESULT_CONFIRMED_DUPLICATE = 'CONFIRMED_DUPLICATE';
    const RESULT_ACTIVE_IN_OTHER_BATCH = 'ACTIVE_IN_OTHER_BATCH';

    public function checkBeforeInsert(UrbanGoodzBatchPackage $candidate, int $currentBatchId, int $userId): array
    {
        $criteria = $candidate->getDuplicateCriteria();

        if (empty($criteria)) {
            return ['result' => self::RESULT_NEW_PACKAGE, 'matches' => []];
        }

        // 1. Check in the current batch
        $existingInBatch = UrbanGoodzBatchPackage::where('intake_batch_id', $currentBatchId)
            ->where('is_active', true)
            ->where('id', '!=', $candidate->id ?? 0)
            ->where(function ($q) use ($criteria) {
                if (isset($criteria['barcode'])) {
                    $q->orWhereRaw('LOWER(TRIM(barcode)) = ?', [$criteria['barcode']]);
                }
                if (isset($criteria['tracking_id'])) {
                    $q->orWhereRaw('LOWER(TRIM(tracking_id)) = ?', [$criteria['tracking_id']]);
                }
                if (isset($criteria['order_ref'])) {
                    $q->orWhereRaw('LOWER(TRIM(order_reference_number)) = ?', [$criteria['order_ref']]);
                }
                if (isset($criteria['external_package'])) {
                    $ext = $criteria['external_package'];
                    $q->orWhere(function ($sq) use ($ext) {
                        $sq->where('business_client_id', $ext['business_client_id'])
                            ->whereRaw('LOWER(TRIM(external_package_id)) = ?', [$ext['external_package_id']]);
                    });
                }
                if (isset($criteria['manifest_row'])) {
                    $row = $criteria['manifest_row'];
                    $q->orWhere(function ($sq) use ($row) {
                        $sq->where('business_client_id', $row['business_client_id'])
                            ->whereRaw('LOWER(TRIM(source_manifest_row)) = ?', [$row['source_manifest_row']]);
                    });
                }
                if (isset($criteria['recipient_address'])) {
                    $addr = $criteria['recipient_address'];
                    $q->orWhere(function ($sq) use ($addr) {
                        $sq->whereRaw('LOWER(TRIM(dropoff_address)) = ?', [$addr['dropoff_address']])
                            ->whereRaw('LOWER(TRIM(dropoff_city)) = ?', [$addr['dropoff_city']])
                            ->whereRaw('LOWER(TRIM(dropoff_state)) = ?', [$addr['dropoff_state']])
                            ->whereRaw('LOWER(TRIM(dropoff_zip)) = ?', [$addr['dropoff_zip']])
                            ->whereRaw('LOWER(TRIM(recipient_name)) = ?', [$addr['recipient_name']]);
                    });
                }
            })
            ->get();

        if ($existingInBatch->isNotEmpty()) {
            $match = $existingInBatch->first();

            UrbanGoodzBatchPackageAudit::log(
                null,
                $currentBatchId,
                'duplicate_detected',
                $userId,
                null,
                ['match_package_id' => $match->id, 'match_tracking_id' => $match->tracking_id],
                null,
                null,
                $candidate->device_session_id,
                "Already in batch: package {$match->id}"
            );

            return [
                'result' => self::RESULT_ALREADY_IN_BATCH,
                'matches' => $existingInBatch->map(fn($m) => [
                    'package_id' => $m->id,
                    'tracking_id' => $m->tracking_id,
                    'barcode' => $m->barcode,
                    'created_by' => $m->createdBy?->name ?? 'unknown',
                    'created_at' => $m->created_at?->toIso8601String(),
                    'status' => $m->validation_status,
                    'route_assignment' => $m->route_assignment_status,
                ])->toArray(),
            ];
        }

        // 2. Check across other active batches
        $otherBatchMatches = UrbanGoodzBatchPackage::where('intake_batch_id', '!=', $currentBatchId)
            ->where('is_active', true)
            ->where(function ($q) use ($criteria) {
                if (isset($criteria['barcode'])) {
                    $q->orWhereRaw('LOWER(TRIM(barcode)) = ?', [$criteria['barcode']]);
                }
                if (isset($criteria['tracking_id'])) {
                    $q->orWhereRaw('LOWER(TRIM(tracking_id)) = ?', [$criteria['tracking_id']]);
                }
                if (isset($criteria['order_ref'])) {
                    $q->orWhereRaw('LOWER(TRIM(order_reference_number)) = ?', [$criteria['order_ref']]);
                }
                if (isset($criteria['external_package'])) {
                    $ext = $criteria['external_package'];
                    $q->orWhere(function ($sq) use ($ext) {
                        $sq->where('business_client_id', $ext['business_client_id'])
                            ->whereRaw('LOWER(TRIM(external_package_id)) = ?', [$ext['external_package_id']]);
                    });
                }
            })
            ->with('batch:id,batch_name,service_date,status')
            ->get();

        if ($otherBatchMatches->isNotEmpty()) {
            $match = $otherBatchMatches->first();

            UrbanGoodzBatchPackageAudit::log(
                null,
                $currentBatchId,
                'duplicate_detected',
                $userId,
                null,
                ['match_batch_id' => $match->intake_batch_id, 'match_package_id' => $match->id],
                null,
                null,
                $candidate->device_session_id,
                "Active in other batch: {$match->batch?->batch_name}"
            );

            return [
                'result' => self::RESULT_ACTIVE_IN_OTHER_BATCH,
                'matches' => $otherBatchMatches->map(fn($m) => [
                    'package_id' => $m->id,
                    'tracking_id' => $m->tracking_id,
                    'batch_id' => $m->intake_batch_id,
                    'batch_name' => $m->batch?->batch_name,
                    'batch_status' => $m->batch?->status,
                    'created_by' => $m->createdBy?->name ?? 'unknown',
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->toArray(),
            ];
        }

        return ['result' => self::RESULT_NEW_PACKAGE, 'matches' => []];
    }

    public function detectPossibleDuplicates(array $packages, int $batchId): array
    {
        $results = [];
        $processed = [];

        foreach ($packages as $pkg) {
            $addrKey = $pkg->addressKey();
            $nameKey = strtolower(trim($pkg->recipient_name ?? ''));

            $compositeKey = $addrKey . '|' . $nameKey;
            if (isset($processed[$compositeKey])) {
                $results[] = [
                    'package_id' => $pkg->id,
                    'possible_duplicate_of' => $processed[$compositeKey],
                    'reason' => 'Same address and recipient',
                ];
            }

            $processed[$compositeKey] = $pkg->id;
        }

        return $results;
    }

    public function resolveDuplicate(int $packageId, string $resolution, int $userId): void
    {
        $package = UrbanGoodzBatchPackage::findOrFail($packageId);

        match ($resolution) {
            'keep_original' => $package->update([
                'duplicate_status' => 'none',
                'duplicate_of_package_id' => null,
            ]),
            'keep_this' => $package->update([
                'duplicate_status' => 'none',
                'duplicate_of_package_id' => null,
            ]),
            'merge' => $package->update([
                'is_active' => false,
                'duplicate_status' => 'confirmed_duplicate',
            ]),
            'reject' => $package->update([
                'is_active' => false,
                'duplicate_status' => 'confirmed_duplicate',
            ]),
            default => throw new \InvalidArgumentException("Unknown resolution: {$resolution}"),
        };

        UrbanGoodzBatchPackageAudit::log(
            $packageId,
            $package->intake_batch_id,
            'duplicate_resolved',
            $userId,
            ['duplicate_status' => $package->getOriginal('duplicate_status')],
            ['resolution' => $resolution],
            $package->version,
            null,
            $package->device_session_id,
            "Resolution: {$resolution}"
        );
    }
}
