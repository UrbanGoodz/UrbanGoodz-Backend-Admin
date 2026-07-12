<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzLoadBoardLoad extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_load_board_loads';

    protected $fillable = [
        'external_id', 'provider', 'load_number', 'status',
        'origin_name', 'origin_city', 'origin_state', 'origin_zip', 'origin_lat', 'origin_lng', 'origin_ready_at',
        'destination_name', 'destination_city', 'destination_state', 'destination_zip', 'destination_lat', 'destination_lng', 'destination_due_at',
        'distance_miles', 'estimated_duration_minutes',
        'payout_amount', 'payout_type', 'rate_per_mile',
        'load_type', 'equipment_type', 'weight_lbs', 'length_ft', 'pieces', 'commodity_description', 'special_requirements', 'notes',
        'is_hazmat', 'is_temperature_controlled', 'temperature_min_f', 'temperature_max_f',
        'requires_liftgate', 'requires_pallet_jack', 'is_team_load', 'is_expedited',
        'shipper_name', 'shipper_phone', 'consignee_name', 'consignee_phone',
        'assigned_driver_id', 'assigned_by', 'assigned_at', 'picked_up_at', 'delivered_at', 'delivery_proof',
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
        'distance_miles' => 'float',
        'estimated_duration_minutes' => 'integer',
        'payout_amount' => 'decimal:2',
        'rate_per_mile' => 'decimal:2',
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
            'assigned' => 'Assigned',
            'in_transit' => 'In Transit',
            'picked_up' => 'Picked Up',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
