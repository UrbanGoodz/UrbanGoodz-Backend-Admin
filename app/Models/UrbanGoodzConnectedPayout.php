<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzConnectedPayout extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
        'arrival_at' => 'datetime',
        'paid_at' => 'datetime',
        'returned_at' => 'datetime',
        'last_stripe_event_at' => 'datetime',
    ];
}
