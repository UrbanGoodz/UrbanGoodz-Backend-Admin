<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzRoutePackage extends Model
{
    use SoftDeletes;

    const PACKAGE_TYPES = ['parcel', 'document', 'specimen', 'supply', 'pallet', 'envelope'];
    const PRIORITIES = ['normal', 'high', 'urgent', 'medical'];
    const STATUSES = ['pending', 'pending_review', 'ready_for_route', 'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'unable_to_deliver', 'failed', 'return_required', 'returning_to_pickup', 'returning_to_hub', 'returned_to_pickup', 'returned_to_hub', 'returned_to_business', 'admin_review', 'payout_eligible', 'payout_excluded', 'completed'];

    const AGE_VERIFICATION_STATUSES = ['pending', 'verified', 'failed', 'refused'];

    const AGE_VERIFICATION_REFUSAL_REASONS = [
        'no_id_provided', 'id_expired', 'recipient_underage',
        'recipient_name_mismatch', 'recipient_visibly_intoxicated_or_unsafe',
        'recipient_unavailable', 'address_mismatch', 'driver_safety_issue',
        'other_admin_review',
    ];

    protected $fillable = [
        'dedicated_route_id', 'route_batch_id', 'business_client_id',
        'manifest_id', 'manifest_session_id', 'scanned_by', 'scanned_at',
        'tracking_id', 'external_reference', 'barcode', 'qr_code',
        'pickup_location_id', 'pickup_contact_name', 'pickup_contact_phone', 'pickup_address',
        'dropoff_name', 'dropoff_address', 'dropoff_city', 'dropoff_state', 'dropoff_zip', 'dropoff_phone', 'dropoff_lat', 'dropoff_lng',
        'stop_order', 'delivery_window_start', 'delivery_window_end',
        'package_type', 'weight', 'weight_unit', 'dimensions', 'priority',
        'requires_signature', 'requires_photo', 'requires_custody', 'temperature_requirement',
        'age_restricted', 'requires_id_verification', 'no_contactless_delivery',
        'delivery_completion_locked_until_verified', 'admin_review_required_on_failure',
        'age_verification_status', 'age_verification_refusal_reason', 'age_verification_driver_notes',
        'age_verified_at', 'age_verified_by_driver_id',
        'status',
        'pickup_scanned_at', 'pickup_scanned_by', 'pickup_lat', 'pickup_lng',
        'dropoff_scanned_at', 'dropoff_scanned_by', 'dropoff_lat', 'dropoff_lng',
        'proof_photo', 'recipient_signature', 'delivery_result', 'delivered_to_name', 'delivered_location_type',
        'return_required', 'returned_at', 'return_location',
        'payout_status', 'payout_eligible',
        'geocode_status', 'geocode_confidence',
        'exception_reason', 'notes',
    ];

    protected $casts = [
        'delivery_window_start' => 'datetime',
        'delivery_window_end' => 'datetime',
        'weight' => 'decimal:2',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'requires_signature' => 'boolean',
        'requires_photo' => 'boolean',
        'requires_custody' => 'boolean',
        'return_required' => 'boolean',
        'age_restricted' => 'boolean',
        'requires_id_verification' => 'boolean',
        'no_contactless_delivery' => 'boolean',
        'delivery_completion_locked_until_verified' => 'boolean',
        'admin_review_required_on_failure' => 'boolean',
        'age_verified_at' => 'datetime',
        'payout_eligible' => 'boolean',
        'geocode_confidence' => 'decimal:2',
        'returned_at' => 'datetime',
        'scanned_at' => 'datetime',
        'pickup_scanned_at' => 'datetime',
        'dropoff_scanned_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzRouteBatch::class, 'route_batch_id');
    }

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function manifest()
    {
        return $this->belongsTo(UrbanGoodzManifest::class, 'manifest_id');
    }

    public function scans()
    {
        return $this->hasMany(UrbanGoodzPackageScan::class, 'package_id');
    }

    public function ageVerifications()
    {
        return $this->hasMany(UrbanGoodzAgeVerification::class, 'package_id');
    }

    public function ageVerifiedByDriver()
    {
        return $this->belongsTo(DeliveryMan::class, 'age_verified_by_driver_id');
    }

    public function isAgeRestricted(): bool
    {
        return $this->age_restricted || $this->requires_id_verification;
    }

    public function isDeliveryLocked(): bool
    {
        return $this->delivery_completion_locked_until_verified
            && $this->age_verification_status !== 'verified';
    }

    public function optimizationStop()
    {
        return $this->hasOne(UrbanGoodzRouteOptimizationStop::class, 'package_id');
    }

    public function pickupScannedBy()
    {
        return $this->belongsTo(DeliveryMan::class, 'pickup_scanned_by');
    }

    public function dropoffScannedBy()
    {
        return $this->belongsTo(DeliveryMan::class, 'dropoff_scanned_by');
    }

    public function scannedByUser()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'scanned_by');
    }

    public function custodyLogs()
    {
        return $this->hasMany(UrbanGoodzMedicalCustodyLog::class, 'package_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzDriverEarning::class, 'package_id');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public static function nextTrackingId(): string
    {
        return 'UGP-' . now()->format('Ymd') . '-' . str_pad((self::max('id') ?? 0) + 1, 6, '0', STR_PAD_LEFT);
    }
}
