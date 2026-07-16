<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class OrderAnywhereRequest extends Model
{
    use LogsActivity;
    public const STATUSES = [
        'pending_review',
        'reviewing',
        'quote_needed',
        'vendor_assigned',
        'vendor_accepted',
        'approved',
        'shopping',
        'picked_up',
        'out_for_delivery',
        'completed',
        'rejected',
        'cancelled',
    ];

    protected const VALID_TRANSITIONS = [
        'pending_review' => ['reviewing', 'quote_needed', 'vendor_assigned', 'rejected', 'cancelled'],
        'reviewing'      => ['quote_needed', 'vendor_assigned', 'rejected', 'cancelled'],
        'quote_needed'   => ['vendor_assigned', 'reviewing', 'rejected', 'cancelled'],
        'vendor_assigned'=> ['vendor_accepted', 'rejected', 'cancelled'],
        'vendor_accepted'=> ['approved', 'rejected', 'cancelled'],
        'approved'       => ['shopping', 'cancelled'],
        'shopping'       => ['picked_up', 'reviewing', 'cancelled'],
        'picked_up'      => ['out_for_delivery', 'reviewing'],
        'out_for_delivery'=> ['completed', 'reviewing'],
        'completed'      => [],
        'rejected'       => ['pending_review'],
        'cancelled'      => ['pending_review'],
    ];

    protected $fillable = [
        'request_number',
        'customer_id',
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
        'payment_status',
        'payment_method',
        'authorization_reference',
        'capture_reference',
        'refund_reference',
        'receipt_path',
        'payment_authorized_at',
        'payment_captured_at',
        'payment_refunded_at',
        'admin_notes',
        'vendor_id',
        'vendor_status',
        'vendor_notes',
        'vendor_quote_amount',
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
        'payment_mode',
    ];

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
        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_refunded_at' => 'datetime',
        'vendor_id' => 'integer',
        'vendor_quote_amount' => 'decimal:2',
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
        'platform_fee' => 'decimal:2',
        'vendor_payout_amount' => 'decimal:2',
        'driver_payout_amount' => 'decimal:2',
    ];

    public static function nextRequestNumber(): string
    {
        return 'OA-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
    }

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

        $this->status = $newStatus;

        if (in_array($newStatus, ['completed', 'cancelled', 'failed'], true)) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($newStatus) {
                $this->save();
                app(\App\Services\UrbanGoodzPaymentService::class)->settleSplits($this);
            });
        } else {
            $this->save();
        }

        return $this->fresh();
    }

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

    public static function paymentMode(): string
    {
        return config('urban_goodz_payments.mode', 'sandbox');
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
}
