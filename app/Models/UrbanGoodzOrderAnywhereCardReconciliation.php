<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzOrderAnywhereCardReconciliation extends Model
{
    protected $fillable = [
        'card_request_id',
        'order_anywhere_request_id',
        'customer_payment_intent_id',
        'provider_authorization_id',
        'provider_transaction_id',
        'approved_budget',
        'authorized_amount',
        'transaction_amount',
        'receipt_amount',
        'refunded_amount',
        'reversed_amount',
        'unused_amount',
        'overage_amount',
        'partial_capture',
        'force_post',
        'status',
        'mismatch_category',
        'matched_at',
        'reviewed_by',
        'safe_metadata',
    ];

    protected $casts = [
        'approved_budget' => 'decimal:2',
        'authorized_amount' => 'decimal:2',
        'transaction_amount' => 'decimal:2',
        'receipt_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'reversed_amount' => 'decimal:2',
        'unused_amount' => 'decimal:2',
        'overage_amount' => 'decimal:2',
        'partial_capture' => 'boolean',
        'force_post' => 'boolean',
        'matched_at' => 'datetime',
        'reviewed_by' => 'integer',
        'safe_metadata' => 'array',
    ];

    public function cardRequest(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzOrderAnywhereCardRequest::class, 'card_request_id');
    }

    public function orderAnywhereRequest(): BelongsTo
    {
        return $this->belongsTo(OrderAnywhereRequest::class, 'order_anywhere_request_id');
    }
}
