<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPaymentFinalization extends Model
{
    protected $fillable = [
        'provider',
        'payment_intent_id',
        'internal_reference',
        'operation',
        'payable_type',
        'payable_id',
        'canonical_key',
        'amount_cents',
        'currency',
        'status',
        'capture_ledger_id',
        'split_ledger_id',
        'notification_id',
        'ledger_count',
        'split_count',
        'completed_at',
    ];

    protected $casts = [
        'payable_id' => 'integer',
        'amount_cents' => 'integer',
        'capture_ledger_id' => 'integer',
        'split_ledger_id' => 'integer',
        'notification_id' => 'integer',
        'ledger_count' => 'integer',
        'split_count' => 'integer',
        'completed_at' => 'datetime',
    ];
}
