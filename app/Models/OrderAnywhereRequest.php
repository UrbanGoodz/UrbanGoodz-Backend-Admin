<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAnywhereRequest extends Model
{
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
        'reviewed_by',
        'reviewed_at',
        'driver_task_status',
        'driver_notes',
        'metadata',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'quantity' => 'integer',
        'budget_estimate' => 'decimal:2',
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
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function nextRequestNumber(): string
    {
        return 'OA-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
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
}
