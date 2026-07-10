<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorEventPromotion extends Model
{
    protected $table = 'urban_goodz_creator_event_promotions';

    protected $fillable = [
        'creator_profile_id', 'creator_application_id', 'event_id',
        'campaign_id', 'promo_type', 'promo_content', 'ticket_link',
        'reservation_link', 'vendor_booth_name', 'status',
        'commission_earned', 'admin_notes',
    ];

    protected $casts = [
        'commission_earned' => 'decimal:2',
    ];

    public function profile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }

    public function event()
    {
        return $this->belongsTo(UrbanGoodzEvent::class, 'event_id');
    }

    public function campaign()
    {
        return $this->belongsTo(UrbanGoodzCreatorCampaign::class, 'campaign_id');
    }
}
