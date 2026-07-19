<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzIntakeBatch extends Model
{
    use SoftDeletes;

    const STATUS_DRAFT = 'DRAFT';
    const STATUS_OPEN_FOR_INTAKE = 'OPEN_FOR_INTAKE';
    const STATUS_VALIDATING = 'VALIDATING';
    const STATUS_NEEDS_REVIEW = 'NEEDS_REVIEW';
    const STATUS_READY_FOR_ROUTING = 'READY_FOR_ROUTING';
    const STATUS_LOCKED_FOR_ROUTING = 'LOCKED_FOR_ROUTING';
    const STATUS_ROUTES_GENERATED = 'ROUTES_GENERATED';
    const STATUS_DISPATCHED = 'DISPATCHED';
    const STATUS_PARTIALLY_COMPLETED = 'PARTIALLY_COMPLETED';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN_FOR_INTAKE,
        self::STATUS_VALIDATING,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_READY_FOR_ROUTING,
        self::STATUS_LOCKED_FOR_ROUTING,
        self::STATUS_ROUTES_GENERATED,
        self::STATUS_DISPATCHED,
        self::STATUS_PARTIALLY_COMPLETED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    const ROUTING_POLICIES = [
        'standard',
        'time_window_priority',
        'cluster_first',
        'manual_assignment',
        'driver_preference',
    ];

    protected $fillable = [
        'business_client_id', 'business_location_id', 'batch_name',
        'service_date', 'intake_start_time', 'intake_cutoff_time',
        'expected_package_count', 'final_package_count',
        'routing_policy', 'created_by_user_id', 'supervisor_user_id',
        'dispatcher_user_id', 'status', 'version', 'is_locked',
        'locked_at', 'locked_by_user_id',
        'routing_snapshot', 'routing_snapshot_at',
        'late_package_policy', 'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'expected_package_count' => 'integer',
        'final_package_count' => 'integer',
        'version' => 'integer',
        'is_locked' => 'boolean',
        'routing_snapshot' => 'array',
        'late_package_policy' => 'array',
        'routing_snapshot_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    // --- Relationships ---

    public function businessClient()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function businessLocation()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientLocation::class, 'business_location_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_user_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function participants()
    {
        return $this->hasMany(UrbanGoodzBatchParticipant::class, 'intake_batch_id');
    }

    public function packages()
    {
        return $this->hasMany(UrbanGoodzBatchPackage::class, 'intake_batch_id');
    }

    public function activePackages()
    {
        return $this->packages()->where('is_active', true);
    }

    public function unassignedPackages()
    {
        return $this->activePackages()->where('route_assignment_status', 'unassigned');
    }

    public function packagesNeedingReview()
    {
        return $this->activePackages()->where('validation_status', 'needs_review');
    }

    public function duplicatePackages()
    {
        return $this->activePackages()->where('duplicate_status', '!=', 'none');
    }

    // --- Status helpers ---

    public function isIntakeOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN_FOR_INTAKE, self::STATUS_NEEDS_REVIEW]);
    }

    public function isLockedForRouting(): bool
    {
        return $this->is_locked || $this->status === self::STATUS_LOCKED_FOR_ROUTING;
    }

    public function canAddPackages(): bool
    {
        return $this->isIntakeOpen() && !$this->is_locked;
    }

    public function canEditPackage(?int $userId): bool
    {
        if ($this->isLockedForRouting()) return false;
        if (!$this->isIntakeOpen()) return false;
        return true;
    }

    public function canLockForRouting(?int $userId): bool
    {
        return in_array($this->status, [self::STATUS_READY_FOR_ROUTING, self::STATUS_OPEN_FOR_INTAKE, self::STATUS_NEEDS_REVIEW])
            && !$this->is_locked;
    }

    public function canGenerateRoutes(): bool
    {
        return $this->status === self::STATUS_LOCKED_FOR_ROUTING && $this->is_locked;
    }

    public function canAcceptLatePackage(): bool
    {
        return in_array($this->status, [
            self::STATUS_LOCKED_FOR_ROUTING,
            self::STATUS_ROUTES_GENERATED,
            self::STATUS_DISPATCHED,
        ]);
    }

    // --- Lifecycle ---

    public function openForIntake(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN_FOR_INTAKE,
            'intake_start_time' => $this->intake_start_time ?? now()->format('H:i:s'),
        ]);
    }

    public function lockForRouting(int $userId): void
    {
        $snapshot = [
            'package_count' => $this->activePackages()->count(),
            'locked_at' => now()->toIso8601String(),
            'locked_by' => $userId,
            'version' => $this->version,
        ];

        $this->update([
            'status' => self::STATUS_LOCKED_FOR_ROUTING,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by_user_id' => $userId,
            'final_package_count' => $this->activePackages()->count(),
            'routing_snapshot' => $snapshot,
            'routing_snapshot_at' => now(),
            'version' => $this->version + 1,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN_FOR_INTAKE,
            'is_locked' => false,
            'locked_at' => null,
            'locked_by_user_id' => null,
            'routing_snapshot' => null,
            'routing_snapshot_at' => null,
            'version' => $this->version + 1,
        ]);
    }

    public function markRoutesGenerated(): void
    {
        $this->update(['status' => self::STATUS_ROUTES_GENERATED]);
    }

    public function markDispatched(): void
    {
        $this->update(['status' => self::STATUS_DISPATCHED]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    // --- Progress ---

    public function getProgress(): array
    {
        $active = $this->activePackages();

        return [
            'expected_count' => $this->expected_package_count,
            'entered_count' => $this->packages()->count(),
            'active_count' => $active->count(),
            'validated_count' => (clone $active)->where('validation_status', 'valid')->count(),
            'needs_review_count' => (clone $active)->where('validation_status', 'needs_review')->count(),
            'duplicate_count' => (clone $active)->where('duplicate_status', '!=', 'none')->count(),
            'invalid_address_count' => (clone $active)->where('geocoding_status', 'failed')->count(),
            'geocoding_pending_count' => (clone $active)->where('geocoding_status', 'pending')->count(),
            'unassigned_count' => (clone $active)->where('route_assignment_status', 'unassigned')->count(),
            'assigned_count' => (clone $active)->where('route_assignment_status', 'assigned')->count(),
            'completion_percentage' => $this->expected_package_count > 0
                ? round((clone $active)->count() / $this->expected_package_count * 100, 1)
                : 0,
            'worker_counts' => $this->getWorkerPackageCounts(),
            'recent_activity' => $this->getRecentActivity(20),
            'last_updated_at' => now()->toIso8601String(),
        ];
    }

    private function getWorkerPackageCounts(): array
    {
        return $this->packages()
            ->where('is_active', true)
            ->selectRaw('created_by_user_id, COUNT(*) as package_count')
            ->groupBy('created_by_user_id')
            ->get()
            ->mapWithKeys(fn($row) => [$row->created_by_user_id => $row->package_count])
            ->toArray();
    }

    private function getRecentActivity(int $limit): array
    {
        return UrbanGoodzBatchPackageAudit::where('intake_batch_id', $this->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($audit) => [
                'id' => $audit->id,
                'package_id' => $audit->batch_package_id,
                'user' => $audit->user ? ['id' => $audit->user->id, 'name' => $audit->user->name] : null,
                'action' => $audit->action,
                'timestamp' => $audit->created_at->toIso8601String(),
            ])
            ->toArray();
    }
}
