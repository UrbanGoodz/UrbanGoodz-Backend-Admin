<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzProviderPortfolioItem extends Model
{
    protected $table = 'urban_goodz_provider_portfolio_items';

    protected $fillable = [
        'provider_id', 'provider_service_id', 'title', 'caption',
        'media_path', 'media_type', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }
}
