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
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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
