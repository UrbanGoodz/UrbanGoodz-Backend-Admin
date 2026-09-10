<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitMeasurementVersion extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'value' => 'decimal:3',
        'confidence' => 'decimal:4',
        'corrected' => 'boolean',
        'approved' => 'boolean',
    ];

    public function measurement() { return $this->belongsTo(FashionFitMeasurement::class, 'measurement_id'); }
}
