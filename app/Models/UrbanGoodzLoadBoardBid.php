<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzLoadBoardBid extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_load_board_bids';

    const STATUSES = ['pending', 'accepted', 'rejected', 'withdrawn'];

    protected $fillable = [
        'load_id', 'driver_id', 'bid_amount', 'bid_message', 'status',
        'responded_at', 'responded_by', 'notes',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public function load()
    {
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'driver_id');
    }

    public function responder()
    {
        return $this->belongsTo(Admin::class, 'responded_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForLoad($query, $loadId)
    {
        return $query->where('load_id', $loadId);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}
