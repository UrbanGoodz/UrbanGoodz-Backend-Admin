<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRentalAsset extends Model
{
    protected $table = 'urban_goodz_rental_assets';

    protected $fillable = [
        'business_type_slug', 'store_id', 'vendor_id', 'title', 'description',
        'asset_type', 'make', 'model', 'year', 'plate_number', 'vin', 'unit_number',
        'photos', 'status', 'daily_rate', 'hourly_rate', 'deposit_amount',
        'mileage_limit', 'pickup_location', 'return_location', 'instructions', 'is_active',
    ];

    protected $casts = [
        'photos' => 'array',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(UrbanGoodzRentalBooking::class, 'rental_asset_id');
    }
}
