<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzMedicalCustodyLog extends Model
{
    const CUSTODY_EVENTS = ['pickup', 'handoff', 'dropoff', 'temp_check', 'seal_check', 'exception'];

    protected $fillable = [
        'package_id', 'custody_event',
        'from_user_id', 'from_user_type',
        'to_user_id', 'to_user_type',
        'temperature', 'seal_intact', 'notes', 'signature',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'seal_intact' => 'boolean',
    ];

    public function package()
    {
        return $this->belongsTo(UrbanGoodzRoutePackage::class, 'package_id');
    }
}
