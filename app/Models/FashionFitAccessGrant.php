<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitAccessGrant extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'measurements_allowed' => 'boolean',
        'photos_allowed' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
