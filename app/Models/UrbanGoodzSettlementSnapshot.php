<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UrbanGoodzSettlementSnapshot extends Model
{
    /**
     * Financial facts are frozen when the settlement is created. Operational
     * state changes (refund/status/reconciliation) are represented separately
     * and the original cents/rule/input facts remain audit-safe.
     */
    private const IMMUTABLE_FIELDS = [
        'snapshot_number',
        'source_type',
        'source_id',
        'idempotency_key',
        'customer_id',
        'business_id',
        'provider_id',
        'driver_id',
        'service_type',
        'currency',
        'shopper_total_cents',
        'merchandise_subtotal_cents',
        'delivery_charge_cents',
        'business_commission_cents',
        'provider_proceeds_cents',
        'driver_compensation_cents',
        'driver_admin_fee_cents',
        'driver_net_cents',
        'platform_delivery_margin_cents',
        'platform_net_cents',
        'rule_snapshot',
        'inputs',
        'settled_by_admin_id',
        'settled_at',
    ];

    protected $fillable = [
        'snapshot_number',
        'source_type',
        'source_id',
        'idempotency_key',
        'customer_id',
        'business_id',
        'provider_id',
        'driver_id',
        'service_type',
        'currency',
        'shopper_total_cents',
        'merchandise_subtotal_cents',
        'delivery_charge_cents',
        'business_commission_cents',
        'provider_proceeds_cents',
        'driver_compensation_cents',
        'driver_admin_fee_cents',
        'driver_net_cents',
        'platform_delivery_margin_cents',
        'platform_net_cents',
        'refunded_cents',
        'status',
        'reconciliation_status',
        'rule_snapshot',
        'inputs',
        'settled_by_admin_id',
        'settled_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'business_id' => 'integer',
        'provider_id' => 'integer',
        'driver_id' => 'integer',
        'shopper_total_cents' => 'integer',
        'merchandise_subtotal_cents' => 'integer',
        'delivery_charge_cents' => 'integer',
        'business_commission_cents' => 'integer',
        'provider_proceeds_cents' => 'integer',
        'driver_compensation_cents' => 'integer',
        'driver_admin_fee_cents' => 'integer',
        'driver_net_cents' => 'integer',
        'platform_delivery_margin_cents' => 'integer',
        'platform_net_cents' => 'integer',
        'refunded_cents' => 'integer',
        'rule_snapshot' => 'array',
        'inputs' => 'array',
        'settled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $snapshot): void {
            foreach (self::IMMUTABLE_FIELDS as $field) {
                if ($snapshot->isDirty($field)) {
                    throw new LogicException("Settlement snapshot field [{$field}] is immutable.");
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Settlement snapshots are permanent financial records.');
        });
    }

    public function ledgerEntries()
    {
        return $this->hasMany(UrbanGoodzFinancialLedgerEntry::class, 'settlement_snapshot_id');
    }

    public function reconciliationRuns()
    {
        return $this->hasMany(UrbanGoodzReconciliationRun::class, 'settlement_snapshot_id');
    }
}
