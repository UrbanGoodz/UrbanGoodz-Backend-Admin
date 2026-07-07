<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzServiceProvider extends Model
{
    protected $table = 'urban_goodz_service_providers';

    protected $fillable = [
        'business_name', 'slug', 'contact_name', 'email', 'phone',
        'service_category', 'description', 'is_verified', 'is_active', 'service_areas',
    ];

    protected $casts = ['is_verified' => 'boolean', 'is_active' => 'boolean', 'service_areas' => 'array'];
}
