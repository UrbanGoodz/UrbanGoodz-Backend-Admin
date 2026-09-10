<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitAnalysis extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'retake_requirements' => 'array',
        'quality_warnings' => 'array',
        'unavailable_measurements' => 'array',
        'source_files' => 'array',
        'overall_confidence' => 'decimal:4',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function profile() { return $this->belongsTo(FashionFitProfile::class, 'profile_id'); }
    public function measurements() { return $this->hasMany(FashionFitMeasurement::class, 'analysis_id'); }
}
