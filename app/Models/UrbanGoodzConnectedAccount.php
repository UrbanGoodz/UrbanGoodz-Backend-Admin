<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzConnectedAccount extends Model
{
    public const ROLES = [
        'vendor', 'business', 'driver', 'service_provider', 'stylist',
        'creator', 'dispatcher', 'event_organiser',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['requirement_errors'];

    protected $casts = [
        'requirements_currently_due' => 'array',
        'requirements_eventually_due' => 'array',
        'requirement_errors' => 'array',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'admin_payouts_enabled' => 'boolean',
        'manual_hold' => 'boolean',
        'refund_hold' => 'boolean',
        'is_suspended' => 'boolean',
        'instant_payout_eligible' => 'boolean',
        'available_balance_cents' => 'integer',
        'pending_balance_cents' => 'integer',
        'minimum_payout_cents' => 'integer',
        'payout_delay_days' => 'integer',
        'next_expected_payout_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_stripe_event_at' => 'datetime',
    ];

    public function transfers()
    {
        return $this->hasMany(UrbanGoodzPayoutTransfer::class, 'connected_account_id');
    }

    public function payouts()
    {
        return $this->hasMany(UrbanGoodzConnectedPayout::class, 'connected_account_id');
    }

    public function canReceiveTransfers(): bool
    {
        return $this->environment === 'sandbox'
            && $this->stripe_account_id
            && $this->transfer_capability_status === 'active'
            && $this->payouts_enabled
            && $this->admin_payouts_enabled
            && ! $this->manual_hold
            && ! $this->is_suspended
            && $this->restriction_status === 'enabled';
    }
}
