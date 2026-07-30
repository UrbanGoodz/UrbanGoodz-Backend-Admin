<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UrbanGoodzReconciliationRun extends Model
{
    protected $fillable = [
        'run_number',
        'settlement_snapshot_id',
        'total_debits_cents',
        'total_credits_cents',
        'difference_cents',
        'status',
        'details',
        'run_by_admin_id',
        'ran_at',
    ];

    protected $casts = [
        'total_debits_cents' => 'integer',
        'total_credits_cents' => 'integer',
        'difference_cents' => 'integer',
        'details' => 'array',
        'ran_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Reconciliation runs are immutable audit records.');
        });

        static::deleting(function (): void {
            throw new LogicException('Reconciliation runs are immutable audit records.');
        });
    }

    public function settlement()
    {
        return $this->belongsTo(UrbanGoodzSettlementSnapshot::class, 'settlement_snapshot_id');
    }
}
