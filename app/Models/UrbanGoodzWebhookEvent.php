<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payable_type',
        'payable_id',
        'idempotency_key',
        'processed_at',
    ];

    protected $casts = [
        'payable_id' => 'integer',
        'processed_at' => 'datetime',
    ];
}
