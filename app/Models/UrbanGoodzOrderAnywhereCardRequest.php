<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $fillable = [
        'order_anywhere_request_id',
        'delivery_man_id',
        'provider',
        'provider_card_id',
        'provider_cardholder_id',
        'provider_reference',
        'card_status',
        'card_type',
        'last4',
        'spending_limit',
        'buffer_amount',
        'currency',
        'authorized_amount',
        'captured_amount',
        'refunded_amount',
        'merchant_name',
        'merchant_category_code',
        'allowed_merchant',
        'allowed_mccs',
        'single_use',
        'usable_from',
        'expires_at',
        'issued_at',
        'activated_at',
        'used_at',
        'frozen_at',
        'cancelled_at',
        'failure_reason',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'order_anywhere_request_id' => 'integer',
        'delivery_man_id' => 'integer',
        'spending_limit' => 'decimal:2',
        'buffer_amount' => 'decimal:2',
        'authorized_amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'single_use' => 'boolean',
        'usable_from' => 'datetime',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'used_at' => 'datetime',
        'frozen_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
        return (float) $this->spending_limit - (float) $this->captured_amount;
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
            default => ucfirst($this->card_status),
        };
    }

    // ─── Static Helpers ─────────────────────────────────────────────────

    public static function activeForRequest(int $requestId): ?self
    {
        return static::where('order_anywhere_request_id', $requestId)
            ->whereIn('card_status', ['issued', 'active'])
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
