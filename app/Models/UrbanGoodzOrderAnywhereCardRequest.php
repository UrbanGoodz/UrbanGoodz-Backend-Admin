<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzOrderAnywhereCardRequest extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = [
        'requested',
        'provider_pending',
        'issued',
        'active',
        'authorized',
        'used',
        'frozen',
        'expired',
        'cancelled',
        'failed',
        'reconciled',
        'revocation_pending',
        'awaiting_provider_configuration',
        'issuance_pending',
        'issuance_retry_pending',
    ];

    protected $fillable = [
        'issuance_key',
        'customer_payment_intent_id',
        'order_anywhere_request_id',
        'delivery_man_id',
        'provider',
        'provider_card_id',
        'provider_cardholder_id',
        'provider_reference',
        'provider_authorization_id',
        'provider_transaction_id',
        'card_status',
        'card_type',
        'last4',
        'spending_limit',
        'approved_purchase_budget',
        'approved_quote_version',
        'market_zone_reference',
        'payment_count_limit',
        'eligible_at',
        'provider_configuration_status',
        'retry_eligible_at',
        'issuance_attempts',
        'final_failure_at',
        'buffer_amount',
        'currency',
        'authorized_amount',
        'captured_amount',
        'refunded_amount',
        'merchant_name',
        'merchant_category_code',
        'allowed_merchant',
        'allowed_mccs',
        'usable_from',
        'expires_at',
        'issued_at',
        'activated_at',
        'used_at',
        'frozen_at',
        'cancelled_at',
        'failure_reason',
        'failure_category',
        'failure_reported_at',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime',
        'receipt_size',
        'receipt_total',
        'receipt_notes',
        'receipt_submitted_at',
        'reconciled_at',
        'reconciled_by',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'order_anywhere_request_id' => 'integer',
        'delivery_man_id' => 'integer',
        'spending_limit' => 'decimal:2',
        'approved_purchase_budget' => 'decimal:2',
        'payment_count_limit' => 'integer',
        'eligible_at' => 'datetime',
        'retry_eligible_at' => 'datetime',
        'issuance_attempts' => 'integer',
        'final_failure_at' => 'datetime',
        'buffer_amount' => 'decimal:2',
        'authorized_amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'usable_from' => 'datetime',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'used_at' => 'datetime',
        'frozen_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failure_reported_at' => 'datetime',
        'receipt_size' => 'integer',
        'receipt_total' => 'decimal:2',
        'receipt_submitted_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'reconciled_by' => 'integer',
        'allowed_mccs' => 'array',
        'metadata' => 'array',
        'created_by' => 'integer',
    ];

    // ─── Relationships ──────────────────────────────────────────────────

    public function orderAnywhereRequest(): BelongsTo
    {
        return $this->belongsTo(OrderAnywhereRequest::class, 'order_anywhere_request_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\DeliveryMan::class, 'delivery_man_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(UrbanGoodzOrderAnywhereCardReconciliation::class, 'card_request_id');
    }

    // ─── Status Helpers ─────────────────────────────────────────────────

    public function isUsable(): bool
    {
        return in_array($this->card_status, ['issued', 'active'], true)
            && $this->expires_at
            && $this->expires_at->isFuture()
            && (! $this->usable_from || $this->usable_from->isPast());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function remainingBalance(): float
    {
        return (float) $this->spending_limit - (float) $this->captured_amount - (float) ($this->authorized_amount ?? 0);
    }

    public function statusLabel(): string
    {
        return match ($this->card_status) {
            'requested' => 'Card Requested',
            'provider_pending' => 'Provider Pending',
            'issued' => 'Card Issued',
            'active' => 'Card Active',
            'authorized' => 'Purchase Authorized',
            'used' => 'Card Used',
            'frozen' => 'Card Frozen',
            'expired' => 'Card Expired',
            'cancelled' => 'Card Cancelled',
            'failed' => 'Card Failed',
            'reconciled' => 'Reconciled',
            'revocation_pending' => 'Revocation Pending',
            'awaiting_provider_configuration' => 'Awaiting Provider Configuration',
            'issuance_pending' => 'Issuance Pending',
            'issuance_retry_pending' => 'Issuance Retry Pending',
            default => ucfirst($this->card_status),
        };
    }

    // ─── Static Helpers ─────────────────────────────────────────────────

    public static function activeForRequest(int $requestId): ?self
    {
        return static::where('order_anywhere_request_id', $requestId)
            ->whereIn('card_status', [
                'requested',
                'provider_pending',
                'awaiting_provider_configuration',
                'issuance_pending',
                'issuance_retry_pending',
                'issued',
                'active',
                'authorized',
                'frozen',
                'revocation_pending',
            ])
            ->whereNull('cancelled_at')
            ->first();
    }

    public static function findUsableForDriver(int $driverId, int $requestId): ?self
    {
        return static::where('delivery_man_id', $driverId)
            ->where('order_anywhere_request_id', $requestId)
            ->whereIn('card_status', ['issued', 'active'])
            ->whereNull('cancelled_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('usable_from')->orWhere('usable_from', '<=', now());
            })
            ->first();
    }
}
