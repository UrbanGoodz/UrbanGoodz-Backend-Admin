<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payment_intent_id',
        'charge_id',
        'internal_reference',
        'operation',
        'amount_minor',
        'currency',
        'allocation_hash',
        'payable_type',
        'payable_id',
        'idempotency_key',
        'received_at',
        'processed_at',
        'status',
        'failure_type',
        'attempt_count',
        'duplicate_count',
        'result',
    ];

    protected $casts = [
        'payable_id' => 'integer',
        'amount_minor' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempt_count' => 'integer',
        'duplicate_count' => 'integer',
        'result' => 'array',
    ];
}
