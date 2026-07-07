<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzBusinessType extends Model
{
    protected $table = 'urban_goodz_business_types';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function defaultCapabilities(): HasMany
    {
        return $this->hasMany(UrbanGoodzBusinessTypeDefaultCapability::class, 'business_type_id');
    }

    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(
            UrbanGoodzCapability::class,
            'urban_goodz_business_type_default_capabilities',
            'business_type_id',
            'capability_id'
        )->withPivot('is_required')->withTimestamps();
    }
}
