<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzBatchPackageAudit extends Model
{
    const ACTIONS = [
        'created',
        'updated',
        'duplicate_detected',
        'duplicate_resolved',
        'validation_passed',
        'validation_failed',
        'review_assigned',
        'review_completed',
        'review_rejected',
        'geocoded',
        'geocode_failed',
        'route_assigned',
        'route_unassigned',
        'late_package_added',
        'late_policy_applied',
        'merged_with',
        'restored',
        'conflict_rejected',
        'conflict_merged',
        'batch_locked',
        'batch_unlocked',
    ];

    protected $fillable = [
        'batch_package_id', 'intake_batch_id', 'user_id',
        'action', 'old_values', 'new_values',
        'conflict_with_user_id', 'conflict_timestamp',
        'device_session_id', 'version_before', 'version_after',
        'notes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'version_before' => 'integer',
        'version_after' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(UrbanGoodzBatchPackage::class, 'batch_package_id');
    }

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzIntakeBatch::class, 'intake_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        ?int $packageId,
        int $batchId,
        string $action,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $versionBefore = null,
        ?int $versionAfter = null,
        ?string $deviceId = null,
        ?string $notes = null
    ): self {
        return static::create([
            'batch_package_id' => $packageId,
            'intake_batch_id' => $batchId,
            'user_id' => $userId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'version_before' => $versionBefore,
            'version_after' => $versionAfter,
            'device_session_id' => $deviceId,
            'notes' => $notes,
        ]);
    }

    public static function logConflict(
        ?int $packageId,
        int $batchId,
        int $conflictingUserId,
        string $conflictTimestamp,
        ?int $userId = null
    ): self {
        return static::create([
            'batch_package_id' => $packageId,
            'intake_batch_id' => $batchId,
            'user_id' => $userId,
            'action' => 'conflict_rejected',
            'conflict_with_user_id' => $conflictingUserId,
            'conflict_timestamp' => $conflictTimestamp,
            'notes' => "Update rejected: conflict with user {$conflictingUserId} at {$conflictTimestamp}",
        ]);
    }
}
