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
        'landmark_sources' => 'array',
        'calculation' => 'array',
        'corrected_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function analysis() { return $this->belongsTo(FashionFitAnalysis::class, 'analysis_id'); }
    public function versions() { return $this->hasMany(FashionFitMeasurementVersion::class, 'measurement_id'); }

    public function isLocked(): bool
    {
        return $this->source !== 'ai' || $this->approved_at !== null;
    }
}
