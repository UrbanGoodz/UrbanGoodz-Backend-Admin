<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRouteOptimizationHistory extends Model
{
    protected $fillable = [
        'dedicated_route_id', 'version', 'action', 'status', 'method',
        'provider', 'distance_mode', 'original_sequence', 'result_sequence',
        'constraints', 'package_count', 'stop_count',
        'original_distance_miles', 'result_distance_miles',
        'original_duration_minutes', 'result_duration_minutes',
        'actor_type', 'actor_id',
    ];

    protected $casts = [
        'original_sequence' => 'array',
        'result_sequence' => 'array',
        'constraints' => 'array',
        'package_count' => 'integer',
        'stop_count' => 'integer',
        'original_distance_miles' => 'decimal:3',
        'result_distance_miles' => 'decimal:3',
        'original_duration_minutes' => 'integer',
        'result_duration_minutes' => 'integer',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }
}
