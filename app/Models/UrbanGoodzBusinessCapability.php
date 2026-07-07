<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzBusinessCapability extends Model
{
    protected $table = 'urban_goodz_business_capabilities';

    protected $fillable = [
        'store_id',
        'capability_id',
        'is_enabled',
        'settings',
        'enabled_at',
        'disabled_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzCapability::class, 'capability_id');
    }
}
