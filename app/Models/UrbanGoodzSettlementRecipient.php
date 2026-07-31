<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class UrbanGoodzSettlementRecipient extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'gross_amount_cents' => 'integer',
        'commission_cents' => 'integer',
        'admin_fee_cents' => 'integer',
        'net_amount_cents' => 'integer',
        'refunded_cents' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            foreach (['settlement_snapshot_id', 'owner_role', 'owner_id', 'gross_amount_cents',
                'commission_cents', 'admin_fee_cents', 'net_amount_cents', 'currency'] as $field) {
                if ($recipient->isDirty($field)) {
                    throw new LogicException("Settlement recipient field [{$field}] is immutable.");
                }
            }
        });
        static::deleting(fn () => throw new LogicException('Settlement recipients are permanent.'));
    }

    public function settlement()
    {
        return $this->belongsTo(UrbanGoodzSettlementSnapshot::class, 'settlement_snapshot_id');
    }
}
