<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreatorCommerceAttribution extends Model
{
    use HasUuids;

    protected $fillable = [
        'reel_id', 'creator_profile_id', 'store_id', 'user_id', 'source_type', 'source_id',
        'gross_amount', 'commission_rate', 'commission_amount', 'currency', 'status',
        'converted_at', 'reversed_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'converted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function creatorProfile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }
}
