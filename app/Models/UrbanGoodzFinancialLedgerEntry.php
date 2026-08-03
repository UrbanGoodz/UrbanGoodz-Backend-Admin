<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UrbanGoodzFinancialLedgerEntry extends Model
{
    protected $fillable = [
        'entry_number',
        'settlement_snapshot_id',
        'event_type',
        'account_code',
        'party_type',
        'party_id',
        'direction',
        'amount_cents',
        'currency',
        'reference',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'settlement_snapshot_id' => 'integer',
        'party_id' => 'integer',
        'amount_cents' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Financial ledger entries are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Financial ledger entries are append-only.');
        });
    }

    public function settlement()
    {
        return $this->belongsTo(UrbanGoodzFinancialSettlementSnapshot::class, 'settlement_snapshot_id');
    }
}
