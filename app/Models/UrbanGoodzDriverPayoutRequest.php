<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzDriverPayoutRequest extends Model
{
    use SoftDeletes;

    const PAYOUT_TYPES = ['instant', 'weekly', 'held'];
    const STATUSES = ['pending', 'approved', 'processing', 'paid', 'rejected', 'held'];

    protected $fillable = [
        'delivery_man_id', 'payout_type', 'requested_amount',
        'instant_fee', 'net_amount', 'currency', 'status',
        'approved_by', 'approved_at', 'paid_at',
        'admin_notes', 'driver_notes',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'instant_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('delivery_man_id', $driverId);
    }
}
