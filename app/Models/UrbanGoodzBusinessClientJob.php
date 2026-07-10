<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBusinessClientJob extends Model
{
    use SoftDeletes;

    const JOB_TYPES = [
        'logistics', 'load_board', 'medical_courier', 'event', 'bulk_delivery', 'rental',
    ];

    const STATUSES = [
        'submitted', 'under_review', 'accepted', 'quoted', 'quote_accepted',
        'assigned', 'driver_en_route', 'picked_up', 'in_transit', 'delayed',
        'delivered', 'completed', 'invoiced', 'paid', 'canceled',
    ];

    const DRIVER_ACCESSIBLE_STATUSES = [
        'assigned', 'driver_en_route', 'picked_up', 'in_transit', 'delayed',
        'delivered', 'completed', 'canceled',
    ];

    const DRIVER_ACTIVE_STATUSES = [
        'assigned', 'driver_en_route', 'picked_up', 'in_transit', 'delayed',
    ];

    protected $fillable = [
        'job_number', 'business_client_id', 'created_by', 'job_type', 'status',
        'description', 'reference_number', 'po_number',
        'pickup_location_id', 'pickup_contact_name', 'pickup_contact_phone',
        'pickup_earliest', 'pickup_latest',
        'dropoff_location_id', 'dropoff_contact_name', 'dropoff_contact_phone',
        'delivery_deadline',
        'load_type', 'weight', 'weight_unit', 'dimensions', 'pallet_count',
        'vehicle_type_needed', 'needs_liftgate', 'needs_dock', 'special_handling',
        'specimen_type', 'temperature_requirement', 'urgency_level',
        'chain_of_custody_required', 'sealed_package_confirmed',
        'courier_certification_required',
        'rate_offered', 'quoted_amount', 'final_amount', 'currency',
        'assigned_delivery_man_id', 'assigned_at',
        'picked_up_at', 'delivered_at', 'proof_of_pickup', 'proof_of_delivery',
        'admin_notes', 'driver_notes', 'exception_reason', 'exception_reported_at',
        'driver_accepted_at', 'reviewed_by', 'reviewed_at',
        'invoice_number', 'invoiced_at', 'paid_at',
    ];

    protected $casts = [
        'pickup_earliest' => 'datetime',
        'pickup_latest' => 'datetime',
        'delivery_deadline' => 'datetime',
        'needs_liftgate' => 'boolean',
        'needs_dock' => 'boolean',
        'chain_of_custody_required' => 'boolean',
        'sealed_package_confirmed' => 'boolean',
        'weight' => 'decimal:2',
        'rate_offered' => 'decimal:2',
        'quoted_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'driver_accepted_at' => 'datetime',
        'exception_reported_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function creator()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'created_by');
    }

    public function pickupLocation()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientLocation::class, 'pickup_location_id');
    }

    public function dropoffLocation()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientLocation::class, 'dropoff_location_id');
    }

    public function assignedDriver()
    {
        return $this->belongsTo(DeliveryMan::class, 'assigned_delivery_man_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopeAssignedToDriver($query, $driverId)
    {
        return $query->where('assigned_delivery_man_id', $driverId);
    }

    public function scopeDriverAccessible($query)
    {
        return $query->whereIn('status', self::DRIVER_ACCESSIBLE_STATUSES);
    }

    public function scopeDriverActive($query)
    {
        return $query->whereIn('status', self::DRIVER_ACTIVE_STATUSES);
    }

    public static function nextJobNumber(): string
    {
        return 'UGJ-' . now()->format('Ymd') . '-' . str_pad((self::max('id') ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    }
}
