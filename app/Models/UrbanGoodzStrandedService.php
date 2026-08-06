<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzStrandedService extends Model
{
    protected $table = 'urban_goodz_stranded_services';

    protected $fillable = [
        'slug', 'name', 'description', 'icon',
        'base_price_min_minor', 'base_price_max_minor', 'currency', 'pricing_note',
        'samaritan_eligible', 'typical_duration_minutes', 'sort_order', 'enabled',
    ];

    protected $casts = [
        'base_price_min_minor' => 'integer',
        'base_price_max_minor' => 'integer',
        'samaritan_eligible' => 'boolean',
        'typical_duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'enabled' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * No fixed starting price -- towing, mobile mechanic, fleet contracts.
     * The client must show a quote flow rather than a figure it cannot honour.
     */
    public function getIsQuoteOnlyAttribute(): bool
    {
        return (int) $this->base_price_min_minor === 0;
    }
}
