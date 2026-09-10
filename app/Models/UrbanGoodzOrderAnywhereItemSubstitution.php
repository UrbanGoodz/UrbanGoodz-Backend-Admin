<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item swap on an Order Anywhere purchase.
 *
 * Both sides of the swap are kept. Nothing is edited in place, so an order
 * stays explainable line by line: what the customer asked for, what the driver
 * actually bought, the money difference, and who agreed to it.
 *
 * price_delta is MERCHANDISE money. It moves the purchase funds the customer
 * owes; it never touches driver earnings or Urban Goodz revenue.
 */
class UrbanGoodzOrderAnywhereItemSubstitution extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_CUSTOMER_APPROVED = 'customer_approved';
    public const STATUS_CUSTOMER_REJECTED = 'customer_rejected';
    public const STATUS_AUTO_APPROVED = 'auto_approved';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_UNAVAILABLE = 'unavailable';
    public const REASON_OUT_OF_STOCK = 'out_of_stock';
    public const REASON_DRIVER_CHOICE = 'driver_choice';
    public const REASON_CUSTOMER_REQUEST = 'customer_request';
    public const REASON_REMOVED = 'removed';

    protected $table = 'urban_goodz_order_anywhere_item_substitutions';

    protected $fillable = [
        'order_anywhere_request_id',
        'card_request_id',
        'line_reference',
        'original_item_name',
        'original_quantity',
        'original_estimated_price',
        'substituted_item_name',
        'substituted_quantity',
        'substituted_actual_price',
        'price_delta',
        'reason',
        'status',
        'requires_customer_approval',
        'proposed_by',
        'proposed_by_id',
        'customer_responded_at',
        'reviewed_by',
        'applied_at',
        'notes',
        'safe_metadata',
    ];

    protected $casts = [
        'original_quantity' => 'decimal:2',
        'original_estimated_price' => 'decimal:2',
        'substituted_quantity' => 'decimal:2',
        'substituted_actual_price' => 'decimal:2',
        'price_delta' => 'decimal:2',
        'requires_customer_approval' => 'boolean',
        'customer_responded_at' => 'datetime',
        'applied_at' => 'datetime',
        'safe_metadata' => 'array',
    ];

    public function orderAnywhereRequest(): BelongsTo
    {
        return $this->belongsTo(OrderAnywhereRequest::class, 'order_anywhere_request_id');
    }

    public function cardRequest(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzOrderAnywhereCardRequest::class, 'card_request_id');
    }

    /**
     * Money difference this swap makes to the merchandise cost.
     *
     * A removal - nothing bought in place of the original - refunds the whole
     * original estimate, so the delta is negative by that amount.
     */
    public static function computeDelta(
        float $originalEstimatedPrice,
        ?float $substitutedActualPrice
    ): float {
        return round(($substitutedActualPrice ?? 0.0) - $originalEstimatedPrice, 2);
    }

    /** Swaps that actually moved money and have been agreed. */
    public function scopeEffective($query)
    {
        return $query->whereIn('status', [
            self::STATUS_CUSTOMER_APPROVED,
            self::STATUS_AUTO_APPROVED,
            self::STATUS_APPLIED,
        ]);
    }

    /** Still waiting on the customer, so not yet spendable. */
    public function scopeAwaitingCustomer($query)
    {
        return $query->where('status', self::STATUS_PROPOSED)
            ->where('requires_customer_approval', true);
    }

    public function isEffective(): bool
    {
        return in_array($this->status, [
            self::STATUS_CUSTOMER_APPROVED,
            self::STATUS_AUTO_APPROVED,
            self::STATUS_APPLIED,
        ], true);
    }

    public function isRemoval(): bool
    {
        return $this->reason === self::REASON_REMOVED
            || $this->substituted_item_name === null;
    }
}
