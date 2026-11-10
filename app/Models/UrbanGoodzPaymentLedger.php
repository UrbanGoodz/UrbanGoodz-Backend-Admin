<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPaymentLedger extends Model
{
    protected $fillable = [
        'ledger_number',
        'feature',
        'payable_type',
        'payable_id',
        'event_type',
        'direction',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'reference',
        'idempotency_key',
        'customer_id',
        'vendor_id',
        'delivery_man_id',
        'created_by_admin_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'customer_id' => 'integer',
        'vendor_id' => 'integer',
        'delivery_man_id' => 'integer',
        'created_by_admin_id' => 'integer',
        'metadata' => 'array',
    ];

    public function splits()
    {
        return $this->hasMany(UrbanGoodzPaymentSplit::class, 'ledger_id');
    }

    public static function nextLedgerNumber(): string
    {
        // 8 hex characters instead of 4 digits: with 9,000 possible suffixes per second, a busy second
        // (several ledger rows per payment) could hit the unique index and fail a payment step.
        return 'UGL-' . now()->format('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
