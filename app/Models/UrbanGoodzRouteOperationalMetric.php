<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRouteOperationalMetric extends Model
{
    protected $fillable = [
        'dedicated_route_id', 'completion_version', 'driver_id',
        'business_client_id', 'miles_milli', 'eligible_miles_milli',
        'mileage_eligibility', 'accepted_optimization_version',
        'package_count', 'stop_count',
        'return_count', 'exception_count', 'duration_minutes',
        'distance_mode', 'provider', 'verified_at',
    ];

    protected $casts = [
        'completion_version' => 'integer',
        'miles_milli' => 'integer',
        'eligible_miles_milli' => 'integer',
        'accepted_optimization_version' => 'integer',
        'package_count' => 'integer',
        'stop_count' => 'integer',
        'return_count' => 'integer',
        'exception_count' => 'integer',
        'duration_minutes' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }
}
