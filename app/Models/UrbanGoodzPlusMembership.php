<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPlusMembership extends Model
{
    protected $table = 'urban_goodz_plus_memberships';

    protected $fillable = [
        'member_name', 'member_email', 'tier', 'status',
        'monthly_fee', 'subscribed_at', 'expires_at', 'benefits',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2', 'subscribed_at' => 'datetime',
        'expires_at' => 'datetime', 'benefits' => 'array',
    ];
}
