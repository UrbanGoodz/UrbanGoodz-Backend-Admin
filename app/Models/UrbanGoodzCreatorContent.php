<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorContent extends Model
{
    protected $table = 'urban_goodz_creator_content';

    protected $fillable = [
        'creator_profile_id', 'creator_application_id', 'campaign_id',
        'title', 'description', 'content_type', 'media_urls',
        'linked_vendor_type', 'linked_vendor_id', 'linked_vendor_name',
        'cta_label', 'cta_url', 'likes_count', 'shares_count',
        'saves_count', 'clicks_count', 'is_published', 'is_shoppable',
        'is_featured', 'published_at', 'status', 'admin_notes',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'likes_count' => 'integer',
        'shares_count' => 'integer',
        'saves_count' => 'integer',
        'clicks_count' => 'integer',
        'is_published' => 'boolean',
        'is_shoppable' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }

    public function campaign()
    {
        return $this->belongsTo(UrbanGoodzCreatorCampaign::class, 'campaign_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzCreatorEarning::class, 'content_id');
    }
}
