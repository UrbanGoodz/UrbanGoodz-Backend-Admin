<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEarnMoneyOpportunity extends Model
{
    protected $table = 'urban_goodz_earn_money_opportunities';

    protected $fillable = [
        'title', 'description', 'type', 'reward_amount', 'reward_type',
        'status', 'terms', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
    ];
}
