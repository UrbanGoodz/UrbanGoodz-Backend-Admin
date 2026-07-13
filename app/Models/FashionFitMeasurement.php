<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitMeasurement extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'value' => 'decimal:3',
        'original_value' => 'decimal:3',
        'confidence' => 'decimal:4',
        'requires_confirmation' => 'boolean',
        'corrected_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
