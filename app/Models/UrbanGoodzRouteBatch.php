<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzRouteBatch extends Model
{
    use SoftDeletes;

    const STATUSES = ['pending', 'assigned', 'picked_up', 'in_transit', 'completed', 'failed'];

    protected $fillable = [
        'dedicated_route_id', 'batch_number', 'package_count',
        'status', 'assigned_driver_id', 'picked_up_at', 'completed_at',
    ];

    protected $casts = [
        'picked_up_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function packages()
    {
        return $this->hasMany(UrbanGoodzRoutePackage::class, 'route_batch_id');
    }

    public function assignedDriver()
    {
        return $this->belongsTo(DeliveryMan::class, 'assigned_driver_id');
    }
}
