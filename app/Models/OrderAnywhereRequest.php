<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class OrderAnywhereRequest extends Model
{
    use LogsActivity;

    // ─── Status Constants ─────────────────────────────────────────────────

    public const STATUSES = [
        'pending_review',
        'sourcing',
        'quote_ready',
        'awaiting_payment',
        'payment_session_created',
        'authorized',
        'approved',
        // External merchant path
        'shopper_assigned',
        'shopper_accepted',
        'shopping',
        'purchased',
        // Participating vendor path
        'vendor_assigned',
        'vendor_accepted',
        'preparing',
        'ready_for_pickup',
        'driver_assigned',
        // Common delivery path
        'picked_up',
        'out_for_delivery',
        'delivered',
        'completed',
        // Terminal
        'rejected',
        'cancelled',
    ];

    /**
     * Valid state transitions for each status.
     * External merchant: pending_review → sourcing → quote_ready → awaiting_payment → payment_session_created → authorized → approved → shopper_assigned → shopper_accepted → shopping → purchased → picked_up → out_for_delivery → delivered → completed
     * Participating vendor: pending_review → sourcing → quote_ready → awaiting_payment → payment_session_created → authorized → approved → vendor_assigned → vendor_accepted → preparing → ready_for_pickup → driver_assigned → picked_up → out_for_delivery → delivered → completed
     */
    protected const VALID_TRANSITIONS = [
        'pending_review'          => ['sourcing', 'quote_ready', 'rejected', 'cancelled'],
        'sourcing'                => ['quote_ready', 'pending_review', 'rejected', 'cancelled'],
        'quote_ready'             => ['awaiting_payment', 'sourcing', 'rejected', 'cancelled'],
        'awaiting_payment'        => ['payment_session_created', 'quote_ready', 'cancelled'],
        'payment_session_created' => ['authorized', 'awaiting_payment', 'cancelled'],
        'authorized'              => ['approved', 'cancelled'],
        'approved'                => ['shopper_assigned', 'vendor_assigned', 'cancelled'],
        // External merchant path
        'shopper_assigned'        => ['shopper_accepted', 'rejected', 'cancelled'],
        'shopper_accepted'        => ['shopping', 'rejected', 'cancelled'],
        'shopping'                => ['purchased', 'sourcing', 'cancelled'],
        'purchased'               => ['picked_up', 'sourcing', 'cancelled'],
        // Participating vendor path
        'vendor_assigned'         => ['vendor_accepted', 'rejected', 'cancelled'],
        'vendor_accepted'         => ['preparing', 'rejected', 'cancelled'],
        'preparing'               => ['ready_for_pickup', 'vendor_accepted', 'cancelled'],
        'ready_for_pickup'        => ['driver_assigned', 'preparing', 'cancelled'],
        'driver_assigned'         => ['picked_up', 'cancelled'],
        // Common delivery
        'picked_up'               => ['out_for_delivery', 'sourcing'],
        'out_for_delivery'        => ['delivered', 'sourcing'],
        'delivered'               => ['completed'],
        'completed'               => [],
        'rejected'                => ['pending_review'],
        'cancelled'               => ['pending_review'],
    ];

    // ─── Payment Status Constants ─────────────────────────────────────────

    public const PAYMENT_STATUSES = [
        'unpaid',
        'awaiting_quote',
        'quoted',
        'awaiting_payment',
        'payment_session_created',
        'authorized',
        'capture_pending',
        'captured',
        'partially_captured',
        'refunded',
        'partially_refunded',
        'payment_failed',
        'authorization_failed',
        'capture_failed',
        'refund_failed',
        'disputed',
    ];

    // ─── Fulfillment Types ────────────────────────────────────────────────

    public const FULFILLMENT_EXTERNAL_MERCHANT = 'external_merchant';
    public const FULFILLMENT_PARTICIPATING_VENDOR = 'participating_vendor';

    // ─── Database Fillable ────────────────────────────────────────────────

    protected $fillable = [
        'request_number',
        'customer_id',
        'order_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'store_vendor_name',
        'store_vendor_address_or_website',
        'request_details',
        'item_details',
        'quantity',
        'budget_estimate',
        'item_subtotal',
        'service_fee',
        'delivery_fee',
        'tax',
        'tip',
        'quote_amount',
        'authorized_amount',
        'final_amount',
        'captured_amount',
        'refunded_amount',
        'status',
        'fulfillment_type',
        'sourcing_status',
        'payment_status',
        'payment_method',
        'authorization_reference',
        'capture_reference',
        'capture_idempotency_key',
        'refund_reference',
        'refund_idempotency_key',
        'receipt_path',
        'receipt_amount',
        'receipt_difference',
        'receipt_image_path',
        'receipt_notes',
        'reconciliation_status',
        'reconciled_at',
        'overage_approved',
        'overage_threshold',
        'payment_authorized_at',
        'payment_captured_at',
        'payment_refunded_at',
        'authorization_expires_at',
        'admin_notes',
        'vendor_id',
        'vendor_status',
        'vendor_notes',
        'vendor_quote_amount',
        'shopper_id',
        'shopper_status',
        'card_issued',
        'card_request_id',
        'assigned_delivery_man_id',
        'delivery_man_id',
        'driver_status',
        'assigned_at',
        'driver_accepted_at',
        'arrived_at_pickup_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'reviewed_by',
        'reviewed_at',
        'driver_task_status',
        'driver_notes',
        'metadata',
        'business_id',
        'product_id',
        'cart_items',
        'source_urls',
        'selected_options',
        'customer_visible_status',
        'fulfillment_mode',
        'psp_reference',
        'merchant_reference',
        'payment_link_id',
        'payment_url',
        'payment_provider',
        'provider_reference',
        'provider_payment_id',
        'connect_account_id',
        'payout_status',
        'transfer_status',
        'transfer_reference',
        'platform_fee',
        'vendor_payout_amount',
        'driver_payout_amount',
        'merchant_purchase_amount',
        'tax_amount',
        'processing_reserve',
        'dispatcher_commission',
        'urban_goodz_revenue',
        'payment_mode',
        'financial_rules_snapshot',
    ];

    // ─── Casts ────────────────────────────────────────────────────────────

    protected $casts = [
        'customer_id' => 'integer',
        'quantity' => 'integer',
        'budget_estimate' => 'decimal:2',
        'item_subtotal' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'tip' => 'decimal:2',
        'quote_amount' => 'decimal:2',
        'authorized_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'receipt_amount' => 'decimal:2',
        'receipt_difference' => 'decimal:2',
        'overage_threshold' => 'decimal:2',
        'merchant_purchase_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'processing_reserve' => 'decimal:2',
        'dispatcher_commission' => 'decimal:2',
        'urban_goodz_revenue' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'vendor_payout_amount' => 'decimal:2',
        'driver_payout_amount' => 'decimal:2',
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_refunded_at' => 'datetime',
        'authorization_expires_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'vendor_id' => 'integer',
        'vendor_quote_amount' => 'decimal:2',
        'shopper_id' => 'integer',
        'card_request_id' => 'integer',
        'assigned_delivery_man_id' => 'integer',
        'delivery_man_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'driver_accepted_at' => 'datetime',
        'arrived_at_pickup_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
        'business_id' => 'integer',
        'product_id' => 'integer',
        'cart_items' => 'array',
        'source_urls' => 'array',
        'selected_options' => 'array',
        'card_issued' => 'boolean',
        'overage_approved' => 'boolean',
        'financial_rules_snapshot' => 'array',
    ];

    // ─── Request Number ───────────────────────────────────────────────────

    public static function nextRequestNumber(): string
    {
        return 'OA-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
    }

    // ─── State Machine ────────────────────────────────────────────────────

    public static function validTransitions(): array
    {
        return self::VALID_TRANSITIONS;
    }

    public static function isValidTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public function transitionTo(string $newStatus): self
    {
        if (! self::isValidTransition($this->status, $newStatus)) {
            throw new InvalidArgumentException(
                "Invalid status transition from [{$this->status}] to [{$newStatus}]."
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;

        // Log the transition
        $this->logStatusTransition($oldStatus, $newStatus);

        // Only settle splits on final terminal states, and only if payment was captured
        if (in_array($newStatus, ['completed', 'cancelled', 'failed'], true)) {
            if (in_array($this->payment_status, ['captured', 'partially_captured'], true)) {
                // Settlement happens after capture, not on status transition
                // Only reverse splits on cancellation/refund
            } elseif ($newStatus === 'cancelled' && in_array($this->payment_status, ['authorized', 'capture_pending'], true)) {
                // Cancel authorization, reverse pending splits
                \Illuminate\Support\Facades\DB::transaction(function () use ($newStatus) {
                    $this->save();
                    app(\App\Services\UrbanGoodzPaymentService::class)->reversePendingSplits($this);
                });
            } else {
                $this->save();
            }
        } else {
            $this->save();
        }

        return $this->fresh();
    }

    // ─── Relationships ────────────────────────────────────────────────────

    public function ledgers()
    {
        return $this->hasMany(UrbanGoodzPaymentLedger::class, 'payable_id')
            ->where('payable_type', self::class);
    }

    public function paymentSplits()
    {
        return $this->hasMany(UrbanGoodzPaymentSplit::class, 'payable_id')
            ->where('payable_type', self::class);
    }

    public function cardRequests()
    {
        return $this->hasMany(UrbanGoodzOrderAnywhereCardRequest::class, 'order_anywhere_request_id');
    }

    public function activeCardRequest()
    {
        return $this->hasOne(UrbanGoodzOrderAnywhereCardRequest::class, 'order_anywhere_request_id')
            ->whereIn('card_status', ['issued', 'active', 'authorized'])
            ->whereNull('cancelled_at');
    }

    // ─── Fulfillment Helpers ──────────────────────────────────────────────

    public function isExternalMerchant(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_EXTERNAL_MERCHANT;
    }

    public function isParticipatingVendor(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_PARTICIPATING_VENDOR;
    }

    // ─── Payment Mode Helpers ─────────────────────────────────────────────

    public static function paymentMode(): string
    {
        return app(\App\Services\Payments\PaymentSettings::class)->mode();
    }

    public static function isLiveMode(): bool
    {
        return static::paymentMode() === 'live_controlled';
    }

    public static function isPaymentDisabled(): bool
    {
        return static::paymentMode() === 'disabled';
    }

    public static function isStagedTest(): bool
    {
        return config('urban_goodz_payments.staged_test.enabled', true)
            && ! config('urban_goodz_payments.adyen.enabled', false)
            && ! config('urban_goodz_payments.stripe.enabled', false);
    }

    public static function liveMaxAmount(): float
    {
        return (float) config('urban_goodz_payments.live_controlled.max_amount', 50.00);
    }

    public static function isLiveAdminAllowed(): bool
    {
        $allowed = config('urban_goodz_payments.live_controlled.allowed_admins', []);
        if (empty($allowed)) {
            return false;
        }
        $adminId = (string) auth('admin')->id();
        return in_array($adminId, $allowed, true);
    }

    public static function isLiveCustomerAllowed(?int $customerId): bool
    {
        $allowed = config('urban_goodz_payments.live_controlled.allowed_customers', []);
        if (empty($allowed)) {
            return false;
        }
        return in_array((string) $customerId, $allowed, true);
    }

    // ─── Card Issuing Helpers ─────────────────────────────────────────────

    public function isCardIssuingRequired(): bool
    {
        return $this->isExternalMerchant()
            && in_array($this->status, ['approved', 'shopper_assigned', 'shopping', 'picked_up', 'out_for_delivery'], true)
            && ! $this->card_issued;
    }

    public function canIssueCard(): bool
    {
        return $this->isExternalMerchant()
            && $this->status === 'approved'
            && $this->assigned_delivery_man_id !== null
            && in_array($this->payment_status, ['authorized', 'captured'], true)
            && ! $this->card_issued;
    }

    // ─── Capture Helpers ──────────────────────────────────────────────────

    public function canCapture(): bool
    {
        return in_array($this->payment_status, ['authorized', 'capture_pending'], true)
            && in_array($this->status, ['completed', 'delivered'], true);
    }

    public function canRefund(): bool
    {
        return in_array($this->payment_status, ['captured', 'partially_captured'], true)
            && (float) $this->refunded_amount < (float) $this->captured_amount;
    }

    // ─── Authorization Helpers ────────────────────────────────────────────

    public function isAuthorizationExpired(): bool
    {
        return $this->authorization_expires_at && $this->authorization_expires_at->isPast();
    }

    public function authorizationTimeRemaining(): ?int
    {
        if (! $this->authorization_expires_at) {
            return null;
        }

        $remaining = now()->diffInSeconds($this->authorization_expires_at, false);

        return $remaining > 0 ? $remaining : 0;
    }
}
