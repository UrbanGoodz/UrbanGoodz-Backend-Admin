<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzStylistBidMilestone extends Model
{
    protected $table = 'urban_goodz_stylist_bid_milestones';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_minor' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStylistBid::class, 'stylist_bid_id');
    }
}
