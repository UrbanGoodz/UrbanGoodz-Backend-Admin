<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * An immutable record of how one transaction was settled.
 *
 * Write once. Corrections are made by writing a reversing snapshot, never by
 * editing history — a refund must be able to replay the terms that were in
 * force when the money moved, not today's terms.
 */
class UrbanGoodzSettlementSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'urban_goodz_settlement_snapshots';

    protected $fillable = [
        'settlement_number', 'subject_type', 'subject_id',
        'transaction_type', 'module_id', 'partner_type', 'partner_id',
        'commission_rule_id', 'commission_rule_version',
        'commission_calculation_type', 'commission_rate_percent',
        'commission_fixed_amount_cents', 'commission_basis',
        'qualifying_amount_cents', 'commission_amount_cents',
        'partner_gross_cents', 'partner_net_cents',
        'driver_comp_rule_id', 'driver_comp_rule_version', 'driver_comp_method',
        'driver_gross_cents', 'driver_admin_fee_cents', 'driver_net_cents',
        'currency', 'inputs', 'rule_snapshot', 'idempotency_key', 'effective_at',
    ];

    protected $casts = [
        'inputs' => 'array',
        'rule_snapshot' => 'array',
        'commission_rate_percent' => 'decimal:4',
        'qualifying_amount_cents' => 'integer',
        'commission_amount_cents' => 'integer',
        'partner_gross_cents' => 'integer',
        'partner_net_cents' => 'integer',
        'driver_gross_cents' => 'integer',
        'driver_admin_fee_cents' => 'integer',
        'driver_net_cents' => 'integer',
        'effective_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException(
                'Settlement snapshots are immutable. Write a reversing snapshot instead of editing one.'
            );
        });

        static::deleting(function () {
            throw new RuntimeException(
                'Settlement snapshots are immutable and cannot be deleted.'
            );
        });
    }

    /**
     * Side A must balance: what the partner grossed, less the platform
     * commission, is what the partner nets.
     */
    public function balances(): bool
    {
        return $this->partner_gross_cents - $this->commission_amount_cents === $this->partner_net_cents;
    }

    /**
     * Side B must balance independently of side A.
     */
    public function driverBalances(): bool
    {
        if ($this->driver_gross_cents === null) {
            return true;
        }

        return $this->driver_gross_cents - (int) $this->driver_admin_fee_cents === $this->driver_net_cents;
    }
}
