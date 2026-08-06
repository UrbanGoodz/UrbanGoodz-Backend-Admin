<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzRoadsideOffer extends Model
{
    protected $table = 'urban_goodz_roadside_offers';

    protected $fillable = [
        'request_id', 'provider_id', 'provider_type',
        'distance_miles', 'payout_minor', 'broadcast_round',
        'status', 'offered_at', 'expires_at', 'responded_at',
    ];

    protected $casts = [
        'distance_miles' => 'float',
        'payout_minor' => 'integer',
        'broadcast_round' => 'integer',
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzRoadsideRequest::class, 'request_id');
    }

    public function isLive(): bool
    {
        return $this->status === 'offered'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
