<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorCampaignAssignment extends Model
{
    protected $table = 'urban_goodz_creator_campaign_assignments';

    protected $fillable = [
        'campaign_id', 'creator_profile_id', 'creator_application_id',
        'approval_status', 'creator_notes', 'admin_notes',
        'approved_at', 'completed_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(UrbanGoodzCreatorCampaign::class, 'campaign_id');
    }

    public function profile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }
}
