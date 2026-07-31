<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzPayoutTransfer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_cents' => 'integer',
        'reversed_amount_cents' => 'integer',
        'last_stripe_event_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(UrbanGoodzConnectedAccount::class, 'connected_account_id');
    }

    public function recipient()
    {
        return $this->belongsTo(UrbanGoodzSettlementRecipient::class, 'settlement_recipient_id');
    }
}
