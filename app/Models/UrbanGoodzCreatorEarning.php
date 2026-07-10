<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorEarning extends Model
{
    protected $table = 'urban_goodz_creator_earnings';

    protected $fillable = [
        'creator_profile_id', 'creator_application_id', 'campaign_id',
        'content_id', 'type', 'amount', 'currency', 'status',
        'source_type', 'source_id', 'notes', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
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

    public function content()
    {
        return $this->belongsTo(UrbanGoodzCreatorContent::class, 'content_id');
    }
}
