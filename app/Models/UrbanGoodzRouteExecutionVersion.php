<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRouteExecutionVersion extends Model
{
    protected $table = 'urban_goodz_route_execution_versions';

    protected $fillable = [
        'dedicated_route_id',
        'driver_id',
        'version',
        'endpoint_type',
        'private_endpoint_address',
        'private_endpoint_lat',
        'private_endpoint_lng',
        'miles',
        'duration_minutes',
        'stop_order_sequence',
        'status',
    ];

    protected $casts = [
        'stop_order_sequence' => 'array',
        'miles' => 'decimal:2',
        'duration_minutes' => 'integer',
        'version' => 'integer',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'driver_id');
    }
}
