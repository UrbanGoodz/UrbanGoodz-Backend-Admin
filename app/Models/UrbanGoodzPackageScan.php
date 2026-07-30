<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPackageScan extends Model
{
    const SCAN_TYPES = ['pickup', 'dropoff', 'custody_check', 'exception', 'return_to_sender', 'business_package_scan', 'delivery_attempt', 'failed_delivery', 'return_scan', 'proof_uploaded', 'admin_override'];

    protected $fillable = [
        'package_id', 'scan_type', 'scanned_by', 'scanner_type',
        'latitude', 'longitude', 'photo', 'signature',
        'exception_reason', 'notes',
        'route_id', 'stop_id', 'business_client_id',
        'identifier_type', 'identifier_value',
        'status_before', 'status_after',
        'proof_reference', 'device_source',
        'metadata', 'occurred_at', 'idempotency_key',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
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
