<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzPaymentTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_payment_transactions';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'provider',
        'environment',
        'transaction_type',
        'internal_status',
        'provider_status',
        'amount_minor',
        'currency',
        'merchant_reference',
        'provider_reference',
        'provider_payment_id',
        'provider_payment_link_id',
        'idempotency_key',
        'parent_transaction_id',
        'request_payload_hash',
        'response_summary',
        'failure_code',
        'failure_message',
        'processed_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'response_summary' => 'array',
        'processed_at' => 'datetime',
        'parent_transaction_id' => 'integer',
    ];
}
