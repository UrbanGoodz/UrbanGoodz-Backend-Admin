<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPackageScan extends Model
{
    const SCAN_TYPES = [
        'business_package_scan', 'route_assignment', 'driver_loading',
        'pickup', 'dropoff', 'custody_check', 'delivery_attempt',
        'proof_uploaded', 'exception', 'failed_delivery',
        'canceled', 'return_to_sender', 'return_scan', 'redelivery',
        'admin_override',
    ];

    protected $fillable = [
        'package_id', 'scan_type', 'scanned_by', 'scanner_type',
        'idempotency_key', 'business_client_id', 'dedicated_route_id',
        'input_method', 'device_id', 'occurred_at', 'received_at', 'was_offline',
        'latitude', 'longitude', 'photo', 'signature',
        'exception_reason', 'notes', 'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'was_offline' => 'boolean',
        'metadata' => 'array',
    ];

    public function package()
    {
        return $this->belongsTo(UrbanGoodzRoutePackage::class, 'package_id');
    }

    public function scanner()
    {
        return $this->belongsTo(DeliveryMan::class, 'scanned_by');
    }
}
