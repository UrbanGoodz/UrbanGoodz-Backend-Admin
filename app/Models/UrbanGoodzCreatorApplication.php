<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorApplication extends Model
{
    protected $table = 'urban_goodz_creator_applications';

    protected $fillable = [
        'creator_name', 'email', 'phone', 'platform', 'username',
        'follower_count', 'bio', 'status', 'admin_notes',
        'niche', 'city', 'market', 'social_links', 'content_samples',
    ];

    protected $casts = [
        'follower_count' => 'integer',
        'social_links' => 'array',
        'content_samples' => 'array',
    ];

    public function profile()
    {
        return $this->hasOne(UrbanGoodzCreatorProfile::class, 'creator_application_id');
    }

    public function products()
    {
        return $this->hasMany(UrbanGoodzCreatorProduct::class, 'creator_application_id');
    }

    public function campaigns()
    {
        return $this->hasMany(UrbanGoodzCreatorCampaignAssignment::class, 'creator_application_id');
    }

    public function content()
    {
        return $this->hasMany(UrbanGoodzCreatorContent::class, 'creator_application_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzCreatorEarning::class, 'creator_application_id');
    }

    public function leads()
    {
        return $this->hasMany(UrbanGoodzCreatorBusinessLead::class, 'creator_application_id');
    }

    public function eventPromotions()
    {
        return $this->hasMany(UrbanGoodzCreatorEventPromotion::class, 'creator_application_id');
    }
}
