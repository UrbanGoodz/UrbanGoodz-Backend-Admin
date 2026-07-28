<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzIssuingCardholder extends Model
{
    protected $fillable = [
        'delivery_man_id',
        'provider',
        'provider_cardholder_id',
        'verification_status',
        'provider_status',
        'verified_at',
        'safe_metadata',
    ];

    protected $casts = [
        'delivery_man_id' => 'integer',
        'verified_at' => 'datetime',
        'safe_metadata' => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }
}
