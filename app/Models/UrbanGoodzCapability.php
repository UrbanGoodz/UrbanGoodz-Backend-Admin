<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzCapability extends Model
{
    protected $table = 'urban_goodz_capabilities';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'admin_section_key',
        'group',
        'is_core',
        'sort_order',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            UrbanGoodzBusinessType::class,
            'urban_goodz_business_type_default_capabilities',
            'capability_id',
            'business_type_id'
        )->withPivot('is_required')->withTimestamps();
    }

    public function storeCapabilities(): HasMany
    {
        return $this->hasMany(UrbanGoodzBusinessCapability::class, 'capability_id');
    }
}
