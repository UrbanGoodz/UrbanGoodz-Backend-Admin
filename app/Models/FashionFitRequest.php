<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FashionFitRequest extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = [
        'budget' => 'decimal:2',
        'accepted_amount' => 'decimal:2',
        'share_measurements' => 'boolean',
        'share_photos' => 'boolean',
        'requested_completion_date' => 'date',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'access_revoked_at' => 'datetime',
    ];

    public function profile() { return $this->belongsTo(FashionFitProfile::class, 'profile_id'); }
    public function estimates() { return $this->hasMany(FashionFitEstimate::class, 'request_id'); }
    public function accessGrant() { return $this->hasOne(FashionFitAccessGrant::class, 'request_id'); }
}
