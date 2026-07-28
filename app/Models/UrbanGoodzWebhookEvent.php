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
        'amount_cents',
        'currency',
        'payable_type',
        'payable_id',
        'idempotency_key',
        'processing_status',
        'signature_valid',
        'failure_type',
        'payload_hash',
        'duplicate_count',
        'processing_latency_ms',
        'received_at',
        'processed_at',
        'last_duplicate_at',
    ];

    protected $casts = [
        'payable_id' => 'integer',
        'amount_cents' => 'integer',
        'signature_valid' => 'boolean',
        'duplicate_count' => 'integer',
        'processing_latency_ms' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_duplicate_at' => 'datetime',
    ];
}
