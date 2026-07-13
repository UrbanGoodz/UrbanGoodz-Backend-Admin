<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitConsent extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'ai_processing_allowed' => 'boolean',
        'measurement_sharing_allowed' => 'boolean',
        'photo_sharing_allowed' => 'boolean',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
