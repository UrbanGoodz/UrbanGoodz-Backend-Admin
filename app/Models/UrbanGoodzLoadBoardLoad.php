<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzLoadBoardLoad extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_load_board_loads';

    public const STATUSES = [
        'available', 'sourced', 'draft', 'under_review', 'recommended',
        'offered', 'assigned', 'in_transit', 'picked_up',
        'delivered', 'completed', 'cancelled', 'exception',
    ];

    protected $fillable = [
        'external_id', 'provider', 'source_id', 'fingerprint', 'load_number', 'status',
        'origin_name', 'origin_city', 'origin_state', 'origin_zip', 'origin_lat', 'origin_lng', 'origin_ready_at',
        'destination_name', 'destination_city', 'destination_state', 'destination_zip', 'destination_lat', 'destination_lng', 'destination_due_at',
        'distance_miles', 'estimated_duration_minutes',
        'payout_amount', 'payout_type', 'rate_per_mile', 'driver_payout_amount',
        'customer_price', 'platform_margin', 'dispatcher_incentive',
        'source_cost', 'processing_fee', 'accessorials',
        'load_type', 'raw_source_payload', 'source_url', 'expires_at',
        'equipment_type', 'weight_lbs', 'length_ft', 'pieces', 'commodity_description', 'special_requirements', 'notes',
        'is_hazmat', 'is_temperature_controlled', 'temperature_min_f', 'temperature_max_f',
        'requires_liftgate', 'requires_pallet_jack', 'is_team_load', 'is_expedited',
        'shipper_name', 'shipper_phone', 'consignee_name', 'consignee_phone',
        'assigned_driver_id', 'assigned_by', 'assigned_at', 'picked_up_at', 'delivered_at', 'delivery_proof',
        'reviewed_by', 'reviewed_at', 'cancelled_at', 'cancellation_reason',
        'business_client_id', 'order_id', 'metadata',
        'dispatch_company_id', 'dispatcher_id', 'dispatch_status',
        'commission_amount', 'commission_rate',
    ];

    protected $casts = [
        'origin_lat' => 'float',
        'origin_lng' => 'float',
        'destination_lat' => 'float',
        'destination_lng' => 'float',
        'origin_ready_at' => 'datetime',
        'destination_due_at' => 'datetime',
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'distance_miles' => 'float',
        'estimated_duration_minutes' => 'integer',
        'payout_amount' => 'decimal:2',
        'driver_payout_amount' => 'decimal:2',
        'rate_per_mile' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'platform_margin' => 'decimal:2',
        'dispatcher_incentive' => 'decimal:2',
        'source_cost' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'accessorials' => 'decimal:2',
        'weight_lbs' => 'float',
        'length_ft' => 'float',
        'pieces' => 'integer',
        'temperature_min_f' => 'float',
        'temperature_max_f' => 'float',
        'is_hazmat' => 'boolean',
        'is_temperature_controlled' => 'boolean',
        'requires_liftgate' => 'boolean',
        'requires_pallet_jack' => 'boolean',
        'is_team_load' => 'boolean',
        'is_expedited' => 'boolean',
        'metadata' => 'array',
        'raw_source_payload' => 'array',
        'expires_at' => 'datetime',
        'commission_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
    ];

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'assigned_driver_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function businessClient(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function dispatchCompany(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'dispatch_company_id');
    }

    public function dispatcherUser(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'dispatcher_id');
    }

    public function commissions()
    {
        return $this->hasMany(UrbanGoodzDispatchCommission::class, 'load_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(UrbanGoodzLoadBoardAuditLog::class, 'load_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(UrbanGoodzLoadBoardBid::class, 'load_id');
    }

    public function driverEarnings(): HasMany
    {
        return $this->hasMany(UrbanGoodzDriverEarning::class, 'load_id');
    }

    public function dispatchAuditLogs(): HasMany
    {
        return $this->hasMany(UrbanGoodzDispatchAuditLog::class, 'load_id');
    }

    public function scopeForDispatchCompany($query, int $companyId)
    {
        return $query->where('dispatch_company_id', $companyId);
    }

    public function scopeAssignedToDispatcher($query, int $dispatcherId)
    {
        return $query->where('dispatcher_id', $dispatcherId);
    }

    public function scopeInTerritory($query, array $states)
    {
        if (empty($states)) {
            return $query;
        }
        return $query->whereIn('origin_state', $states)->orWhereIn('destination_state', $states);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOriginState($query, string $state)
    {
        return $query->where('origin_state', $state);
    }

    public function scopeDestinationState($query, string $state)
    {
        return $query->where('destination_state', $state);
    }

    public function scopeLoadType($query, string $type)
    {
        return $query->where('load_type', $type);
    }

    public function scopeEquipmentType($query, string $type)
    {
        return $query->where('equipment_type', $type);
    }

    public function getOriginFullAttribute(): ?string
    {
        return collect([$this->origin_city, $this->origin_state])->filter()->implode(', ');
    }

    public function getDestinationFullAttribute(): ?string
    {
        return collect([$this->destination_city, $this->destination_state])->filter()->implode(', ');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'sourced' => 'Sourced',
            'draft' => 'Draft',
            'under_review' => 'Under Review',
            'recommended' => 'Recommended',
            'offered' => 'Offered',
            'assigned' => 'Assigned',
            'in_transit' => 'In Transit',
            'picked_up' => 'Picked Up',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'exception' => 'Exception',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'available' => 'badge-soft-success',
            'sourced' => 'badge-soft-info',
            'draft' => 'badge-soft-secondary',
            'under_review' => 'badge-soft-warning',
            'recommended' => 'badge-soft-primary',
            'offered' => 'badge-soft-primary',
            'assigned' => 'badge-soft-info',
            'in_transit' => 'badge-soft-primary',
            'picked_up' => 'badge-soft-warning',
            'delivered' => 'badge-soft-success',
            'completed' => 'badge-soft-success',
            'cancelled' => 'badge-soft-danger',
            'exception' => 'badge-soft-danger',
            default => 'badge-soft-secondary',
        };
    }

    public function getEffectiveDriverPayoutAttribute(): float
    {
        return $this->driver_payout_amount ?? $this->payout_amount ?? 0;
    }

    public function getEffectiveMarginAttribute(): float
    {
        if ($this->customer_price && $this->effective_driver_payout) {
            return $this->customer_price - $this->effective_driver_payout - ($this->dispatcher_incentive ?? 0) - ($this->processing_fee ?? 0) + ($this->accessorials ?? 0);
        }
        return $this->platform_margin ?? 0;
    }

    public function logEvent(string $eventType, ?string $oldValue = null, ?string $newValue = null, ?array $context = null, ?string $actorType = 'admin', ?int $actorId = null, ?string $notes = null): UrbanGoodzLoadBoardAuditLog
    {
        return $this->auditLogs()->create([
            'event_type' => $eventType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'context' => $context,
            'actor_type' => $actorType,
            'actor_id' => $actorId ?? auth('admin')->id(),
            'notes' => $notes,
        ]);
    }
}
