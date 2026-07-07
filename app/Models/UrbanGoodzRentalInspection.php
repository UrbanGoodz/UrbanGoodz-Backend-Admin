<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRentalInspection extends Model
{
    protected $table = 'urban_goodz_rental_inspections';

    protected $fillable = [
        'rental_booking_id', 'inspection_type', 'photos', 'notes',
        'damage_found', 'damage_amount', 'status', 'inspected_by',
    ];

    protected $casts = [
        'photos' => 'array',
        'damage_found' => 'boolean',
        'damage_amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(UrbanGoodzRentalBooking::class, 'rental_booking_id');
    }
}
