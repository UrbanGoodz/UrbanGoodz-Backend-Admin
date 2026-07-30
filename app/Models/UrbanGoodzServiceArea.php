<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzServiceArea extends Model
{
    protected $fillable = [
        'provider_id', 'name', 'area_type', 'country_code', 'region_code', 'city',
        'postal_code', 'latitude', 'longitude', 'radius_miles', 'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_miles' => 'integer',
        'is_active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }
}
