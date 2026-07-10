<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRouteAssignment extends Model
{
    const STATUSES = ['assigned', 'accepted', 'en_route', 'started', 'completed', 'canceled'];

    protected $fillable = [
        'dedicated_route_id', 'delivery_man_id', 'status',
        'assigned_by', 'accepted_at', 'route_started_at',
        'route_completed_at', 'notes',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'route_started_at' => 'datetime',
        'route_completed_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function assigner()
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }
}
