<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzOrderAnywhereCardRevealSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'first_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function cardRequest()
    {
        return $this->belongsTo(UrbanGoodzOrderAnywhereCardRequest::class, 'card_request_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
