<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzSpotlightBusiness extends Model
{
    protected $table = 'urban_goodz_spotlight_businesses';

    protected $fillable = [
        'business_name', 'vendor_id', 'description', 'category',
        'image_url', 'is_featured', 'is_active', 'featured_until',
    ];

    protected $casts = [
        'is_featured' => 'boolean', 'is_active' => 'boolean', 'featured_until' => 'datetime',
    ];
}
