<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzOrderAnywhereCardEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'safe_metadata' => 'array',
        'processed_at' => 'datetime',
    ];
}
