<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzCommunityMarketplaceItem extends Model
{
    protected $table = 'urban_goodz_community_marketplace_items';

    protected $fillable = [
        'title', 'description', 'price', 'currency', 'condition',
        'seller_name', 'seller_contact', 'location', 'status', 'image_url', 'is_active',
        'zone_id', 'is_nationwide', 'user_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_nationwide' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForGroup($query, ?int $zoneId, bool $nationwide)
    {
        if ($nationwide) {
            return $query->where('is_nationwide', true);
        }
        return $query->where('zone_id', $zoneId);
    }
}
