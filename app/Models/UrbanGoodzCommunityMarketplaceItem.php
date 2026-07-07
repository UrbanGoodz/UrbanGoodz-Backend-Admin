<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCommunityMarketplaceItem extends Model
{
    protected $table = 'urban_goodz_community_marketplace_items';

    protected $fillable = [
        'title', 'description', 'price', 'currency', 'condition',
        'seller_name', 'seller_contact', 'location', 'status', 'image_url', 'is_active',
    ];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
}
