<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorProfile extends Model
{
    protected $table = 'urban_goodz_creator_profiles';

    protected $fillable = [
        'vendor_id', 'creator_application_id', 'handle', 'display_name', 'bio',
        'avatar_url', 'banner_url', 'city', 'zone', 'niches',
        'social_links', 'content_samples', 'is_approved', 'is_featured',
        'approved_at', 'featured_at', 'admin_notes', 'status',
    ];

    protected $casts = [
        'niches' => 'array',
        'social_links' => 'array',
        'content_samples' => 'array',
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime',
        'featured_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }

    public function campaigns()
    {
        return $this->hasMany(UrbanGoodzCreatorCampaignAssignment::class, 'creator_profile_id');
    }

    public function content()
    {
        return $this->hasMany(UrbanGoodzCreatorContent::class, 'creator_profile_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzCreatorEarning::class, 'creator_profile_id');
    }

    public function leads()
    {
        return $this->hasMany(UrbanGoodzCreatorBusinessLead::class, 'creator_profile_id');
    }

    public function eventPromotions()
    {
        return $this->hasMany(UrbanGoodzCreatorEventPromotion::class, 'creator_profile_id');
    }
}
