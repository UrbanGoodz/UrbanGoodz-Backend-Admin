<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzServiceProvider extends Model
{
    protected $table = 'urban_goodz_service_providers';

    protected $fillable = [
        'business_name', 'slug', 'contact_name', 'email', 'phone',
        'service_category', 'description', 'is_verified', 'is_active', 'service_areas',
    ];

    protected $casts = ['is_verified' => 'boolean', 'is_active' => 'boolean', 'service_areas' => 'array'];

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceRequest::class, 'assigned_vendor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(UrbanGoodzAppointment::class, 'service_provider_id');
    }
}
