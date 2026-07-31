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
        'user_id', 'categories', 'audience_info', 'portfolio', 'verification_status',
        'verified_at', 'follower_count', 'following_count', 'reel_count',
        'source', 'source_url', 'source_date', 'validation_state', 'duplicate_state',
        'approval_state', 'visibility_state', 'failure_reason', 'retry_count',
        'last_verified_at', 'payout_setup_status'
    ];

    protected $casts = [
        'niches' => 'array',
        'social_links' => 'array',
        'content_samples' => 'array',
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime',
        'featured_at' => 'datetime',
        'categories' => 'array',
        'audience_info' => 'array',
        'portfolio' => 'array',
        'verified_at' => 'datetime',
        'source_date' => 'datetime',
        'last_verified_at' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function followers()
    {
        return $this->hasMany(UrbanGoodzCreatorFollow::class, 'creator_profile_id');
    }

    public function blocks()
    {
        return $this->hasMany(UrbanGoodzCreatorBlock::class, 'blocked_user_id', 'user_id');
    }

    public function reels()
    {
        return $this->hasMany(UrbanGoodzReel::class, 'creator_profile_id');
    }

    public function sourcingRecord()
    {
        return $this->morphOne(UrbanGoodzSourcingRecord::class, 'sourceable');
    }

    public function reelComments()
    {
        return $this->hasManyThrough(UrbanGoodzReelComment::class, UrbanGoodzReel::class, 'creator_profile_id', 'reel_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_state', 'approved')->orWhere('is_approved', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility_state', 'visible');
    }

    public function scopeCity($query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeCategory($query, $category)
    {
        return $query->whereJsonContains('categories', $category);
    }

    public function scopeFeatured($query)
    {
        return $query->whereNotNull('featured_at');
    }

    public function incrementFollowerCount()
    {
        $this->increment('follower_count');
    }

    public function decrementFollowerCount()
    {
        if ($this->follower_count > 0) {
            $this->decrement('follower_count');
        }
    }
}
