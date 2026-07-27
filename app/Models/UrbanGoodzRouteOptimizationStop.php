<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRouteOptimizationStop extends Model
{
    const STATUSES = ['pending', 'completed', 'skipped'];

    protected $fillable = [
        'dedicated_route_id', 'package_id', 'stop_order', 'original_stop_order',
        'estimated_distance_from_prev', 'estimated_duration_from_prev', 'status',
    ];

    protected $casts = [
        'estimated_distance_from_prev' => 'decimal:2',
        'estimated_duration_from_prev' => 'integer',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function package()
    {
        return $this->belongsTo(UrbanGoodzRoutePackage::class, 'package_id');
    }
}
