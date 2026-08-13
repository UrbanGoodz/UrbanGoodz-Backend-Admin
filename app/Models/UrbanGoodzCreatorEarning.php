<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorEarning extends Model
{
    protected $table = 'urban_goodz_creator_earnings';

    protected $fillable = [
        'creator_profile_id', 'creator_application_id', 'campaign_id',
        'content_id', 'type', 'amount', 'currency', 'status',
        'source_type', 'source_id', 'notes', 'paid_at',
        'ledger_entry_id', 'settlement_snapshot',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'settlement_snapshot' => 'array',
    ];

    public function profile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }

    public function campaign()
    {
        return $this->belongsTo(UrbanGoodzCreatorCampaign::class, 'campaign_id');
    }

    public function content()
    {
        return $this->belongsTo(UrbanGoodzCreatorContent::class, 'content_id');
    }

    public function ledgerEntry()
    {
        return $this->belongsTo(\App\Models\LedgerEntry::class, 'ledger_entry_id');
    }

    public function recordToLedger()
    {
        // Placeholder for ledger recording logic
        return true;
    }

    public function createSettlementSnapshot(array $data)
    {
        $this->update(['settlement_snapshot' => $data]);
        return true;
    }
}
