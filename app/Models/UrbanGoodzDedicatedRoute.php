<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzDedicatedRoute extends Model
{
    use SoftDeletes;

    const ROUTE_TYPES = ['logistics', 'medical_courier', 'load_board', 'bulk_delivery'];

    const STATUSES = ['draft', 'pending', 'pending_review', 'approved', 'active', 'in_progress', 'pickup_pending', 'completed', 'partially_completed', 'canceled', 'admin_review'];

    protected $fillable = [
        'business_client_id', 'route_name', 'route_type', 'pickup_location', 'end_location',
        'intake_batch_id', 'route_label',
        'end_lat', 'end_lng',
        'pickup_lat', 'pickup_lng', 'scheduled_date', 'recurring_rule',
        'max_packages_per_batch', 'status', 'assigned_driver_id',
        'vehicle_type_required', 'total_packages', 'completed_packages', 'failed_packages',
        'driver_pay_per_package', 'business_charge_per_package',
        'pickup_bonus', 'route_completion_bonus', 'priority_package_bonus',
        'failed_delivery_partial_pay', 'return_to_sender_pay',
        'instant_payout_allowed', 'weekly_payout_allowed',
        'returned_packages', 'payout_model', 'route_offer_amount',
        'estimated_miles', 'estimated_duration',
        'contains_age_restricted_items',
        'created_by', 'route_started_at', 'route_completed_at', 'admin_notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'end_lat' => 'decimal:7',
        'end_lng' => 'decimal:7',
        'driver_pay_per_package' => 'decimal:2',
        'business_charge_per_package' => 'decimal:2',
        'pickup_bonus' => 'decimal:2',
        'route_completion_bonus' => 'decimal:2',
        'priority_package_bonus' => 'decimal:2',
        'failed_delivery_partial_pay' => 'decimal:2',
        'return_to_sender_pay' => 'decimal:2',
        'instant_payout_allowed' => 'boolean',
        'weekly_payout_allowed' => 'boolean',
        'returned_packages' => 'integer',
        'route_offer_amount' => 'decimal:2',
        'estimated_miles' => 'decimal:2',
        'estimated_duration' => 'integer',
        'contains_age_restricted_items' => 'boolean',
        'route_started_at' => 'datetime',
        'route_completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'assigned_driver_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function batches()
    {
        return $this->hasMany(UrbanGoodzRouteBatch::class, 'dedicated_route_id');
    }

    public function packages()
    {
        return $this->hasMany(UrbanGoodzRoutePackage::class, 'dedicated_route_id');
    }

    public function assignments()
    {
        return $this->hasMany(UrbanGoodzRouteAssignment::class, 'dedicated_route_id');
    }

    public function optimizationStops()
    {
        return $this->hasMany(UrbanGoodzRouteOptimizationStop::class, 'dedicated_route_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzDriverEarning::class, 'dedicated_route_id');
    }

    public function invoices()
    {
        return $this->hasMany(UrbanGoodzClientInvoice::class, 'dedicated_route_id');
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('business_client_id', $clientId);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('assigned_driver_id', $driverId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'in_progress']);
    }

    public function progressPercent(): int
    {
        $total = (int) $this->total_packages;
        if ($total === 0) {
            return 0;
        }
        return (int) round(($this->completed_packages / $total) * 100);
    }

    public function activeExecutionVersion()
    {
        return $this->hasOne(UrbanGoodzRouteExecutionVersion::class, 'dedicated_route_id')
            ->where('status', 'active')
            ->latest();
    }

    private function shouldMaskPrivateEndpoint(): bool
    {
        $activeVersion = $this->activeExecutionVersion;
        if (!$activeVersion || $activeVersion->endpoint_type !== 'private_endpoint') {
            return false;
        }

        $driver = auth('delivery_men')->user() ?? auth('api')->user();
        if ($driver && (int)$driver->id === (int)$this->assigned_driver_id) {
            return false;
        }

        return true;
    }

    public function getEndLocationAttribute($value)
    {
        if ($this->shouldMaskPrivateEndpoint()) {
            return 'Driver Private Location';
        }

        $activeVersion = $this->activeExecutionVersion;
        if ($activeVersion && $activeVersion->endpoint_type === 'private_endpoint') {
            return $activeVersion->private_endpoint_address ?? 'Driver Private Location';
        }

        return $value;
    }

    public function getEndLatAttribute($value)
    {
        if ($this->shouldMaskPrivateEndpoint()) {
            return 0.0;
        }

        $activeVersion = $this->activeExecutionVersion;
        if ($activeVersion && $activeVersion->endpoint_type === 'private_endpoint') {
            return $activeVersion->private_endpoint_lat;
        }

        return $value;
    }

    public function getEndLngAttribute($value)
    {
        if ($this->shouldMaskPrivateEndpoint()) {
            return 0.0;
        }

        $activeVersion = $this->activeExecutionVersion;
        if ($activeVersion && $activeVersion->endpoint_type === 'private_endpoint') {
            return $activeVersion->private_endpoint_lng;
        }

        return $value;
    }

    public function getEstimatedMilesAttribute($value)
    {
        $activeVersion = $this->activeExecutionVersion;
        if ($activeVersion) {
            return $activeVersion->miles;
        }
        return $value;
    }

    public function getEstimatedDurationAttribute($value)
    {
        $activeVersion = $this->activeExecutionVersion;
        if ($activeVersion) {
            return $activeVersion->duration_minutes;
        }
        return $value;
    }
}
