<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPaymentSplit extends Model
{
    protected $fillable = [
        'ledger_id',
        'feature',
        'payable_type',
        'payable_id',
        'recipient_type',
        'recipient_id',
        'split_type',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'ledger_id' => 'integer',
        'payable_id' => 'integer',
        'recipient_id' => 'integer',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function ledger()
    {
        return $this->belongsTo(UrbanGoodzPaymentLedger::class, 'ledger_id');
    }
}
