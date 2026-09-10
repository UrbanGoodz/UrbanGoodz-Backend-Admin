<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FashionFitProfile extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = [
        'fit_preferences' => 'array',
        'calibration_height' => 'decimal:2',
        'overall_confidence' => 'decimal:4',
        'approved_at' => 'datetime',
        'sharing_revoked_at' => 'datetime',
    ];

    public function measurements() { return $this->hasMany(FashionFitMeasurement::class, 'profile_id'); }
    public function photos() { return $this->hasMany(FashionFitPhoto::class, 'profile_id'); }
    public function analyses() { return $this->hasMany(FashionFitAnalysis::class, 'profile_id'); }
    public function consents() { return $this->hasMany(FashionFitConsent::class, 'profile_id'); }
    public function latestAnalysis() { return $this->hasOne(FashionFitAnalysis::class, 'profile_id')->latest(); }
}
