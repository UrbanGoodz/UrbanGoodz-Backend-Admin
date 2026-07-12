<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzMedicalCourierJob extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_medical_courier_jobs';

    protected $fillable = [
        'job_number', 'pickup_location', 'pickup_facility_name',
        'pickup_contact_name', 'pickup_contact_phone', 'pickup_lat', 'pickup_lng',
        'delivery_location', 'delivery_facility_name', 'delivery_contact_name',
        'delivery_contact_phone', 'delivery_lat', 'delivery_lng',
        'distance_miles', 'payout_amount', 'payout_type',
        'specimen_type', 'specimen_count', 'requires_refrigeration', 'is_biological_hazard',
        'temperature_min_f', 'temperature_max_f',
        'priority', 'status', 'assigned_driver_id', 'admin_notes',
        'pickup_window_start', 'pickup_window_end',
        'delivery_window_start', 'delivery_window_end',
        'assigned_at', 'picked_up_at', 'delivered_at',
        'signature_path', 'metadata',
    ];

    protected $casts = [
        'requires_refrigeration' => 'boolean',
        'is_biological_hazard' => 'boolean',
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
        'distance_miles' => 'float',
        'payout_amount' => 'decimal:2',
        'specimen_count' => 'integer',
        'temperature_min_f' => 'float',
        'temperature_max_f' => 'float',
        'pickup_window_start' => 'datetime',
        'pickup_window_end' => 'datetime',
        'delivery_window_start' => 'datetime',
        'delivery_window_end' => 'datetime',
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'assigned_driver_id');
    }

    public function custodyLogs(): HasMany
    {
        return $this->hasMany(UrbanGoodzMedicalCourierCustodyLog::class, 'job_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'picked_up']);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'Urgent',
            'high' => 'High',
            'normal' => 'Normal',
            'low' => 'Low',
            default => ucfirst($this->priority),
        };
    }
}
