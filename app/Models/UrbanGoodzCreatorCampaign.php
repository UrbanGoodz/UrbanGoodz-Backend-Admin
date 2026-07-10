<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorCampaign extends Model
{
    protected $table = 'urban_goodz_creator_campaigns';

    protected $fillable = [
        'title', 'type', 'category', 'vendor_id', 'city', 'zone',
        'pay_type', 'flat_payout', 'commission_rate', 'deadline',
        'deliverables', 'brief', 'status', 'admin_notes',
    ];

    protected $casts = [
        'flat_payout' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'deadline' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignments()
    {
        return $this->hasMany(UrbanGoodzCreatorCampaignAssignment::class, 'campaign_id');
    }

    public function content()
    {
        return $this->hasMany(UrbanGoodzCreatorContent::class, 'campaign_id');
    }

    public function earnings()
    {
        return $this->hasMany(UrbanGoodzCreatorEarning::class, 'campaign_id');
    }
}
