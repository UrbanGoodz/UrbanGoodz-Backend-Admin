<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzServiceProvider extends Model
{
    protected $table = 'urban_goodz_service_providers';

    protected $fillable = [
        'vendor_id', 'business_name', 'slug', 'contact_name', 'email', 'phone',
        'service_category', 'description', 'is_verified', 'is_active', 'service_areas',
        'approval_status', 'location_modes', 'rating', 'rating_count',
        'onboarding_data', 'submitted_at', 'approved_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'service_areas' => 'array',
        'location_modes' => 'array',
        'onboarding_data' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(UrbanGoodzProviderService::class, 'provider_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(UrbanGoodzProviderAvailability::class, 'provider_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceArea::class, 'provider_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceRequest::class, 'provider_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(UrbanGoodzAppointment::class, 'service_provider_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceReview::class, 'provider_id');
    }
}
