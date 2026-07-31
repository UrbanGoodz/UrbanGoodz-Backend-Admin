<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzStylistRequest extends Model
{
    public const TYPES = [
        'custom_garment', 'alteration', 'tailoring', 'fitting',
        'wardrobe_styling', 'event_outfit', 'virtual_consultation',
    ];

    protected $table = 'urban_goodz_stylist_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'deadline_at' => 'datetime',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'budget_min_minor' => 'integer',
        'budget_max_minor' => 'integer',
        'deposit_paid_minor' => 'integer',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistBid::class, 'stylist_request_id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistRequestInvite::class, 'stylist_request_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistRequestImage::class, 'stylist_request_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistRequestMessage::class, 'stylist_request_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(UrbanGoodzStylistMeasurementGrant::class, 'stylist_request_id');
    }

    /** Whether a provider is allowed to see and bid on this request at all. */
    public function isVisibleToProvider(int $providerId): bool
    {
        if (!in_array($this->status, ['published', 'bidding'], true)) {
            return false;
        }

        if ($this->visibility === 'invited_only') {
            return $this->invites()->where('provider_id', $providerId)->exists();
        }

        return true;
    }

    public function acceptsBids(): bool
    {
        return in_array($this->status, ['published', 'bidding'], true);
    }
}
