<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorBusinessLead extends Model
{
    protected $table = 'urban_goodz_creator_business_leads';

    protected $fillable = [
        'creator_profile_id', 'creator_application_id',
        'business_name', 'category', 'address', 'phone', 'social_link',
        'city', 'zone', 'photos', 'video_url', 'notes',
        'suggested_module', 'status', 'admin_notes',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function profile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }
}
