<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzStylistBid extends Model
{
    protected $table = 'urban_goodz_stylist_bids';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_minor' => 'integer',
        'deposit_minor' => 'integer',
        'fitting_required' => 'boolean',
        'fittings_count' => 'integer',
        'estimated_days' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStylistRequest::class, 'stylist_request_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistBidMilestone::class, 'stylist_bid_id');
    }

    public function isSelectable(): bool
    {
        return in_array($this->status, ['submitted', 'revised'], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
