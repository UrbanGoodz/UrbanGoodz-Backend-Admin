<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRentalBooking extends Model
{
    protected $table = 'urban_goodz_rental_bookings';

    protected $fillable = [
        'rental_asset_id', 'customer_id', 'customer_name', 'customer_phone',
        'start_at', 'end_at', 'status', 'payment_status', 'deposit_status',
        'verification_status', 'total_amount', 'deposit_amount',
        'admin_notes', 'customer_notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(UrbanGoodzRentalAsset::class, 'rental_asset_id');
    }

    public function inspections()
    {
        return $this->hasMany(UrbanGoodzRentalInspection::class, 'rental_booking_id');
    }
}
