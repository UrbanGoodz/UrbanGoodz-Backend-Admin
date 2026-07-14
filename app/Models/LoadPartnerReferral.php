<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadPartnerReferral extends Model
{
    protected $table = 'load_partner_referrals';

    const ACTIONS = ['open_source', 'share', 'contact_broker'];
    const BOOKING_STATUSES = ['pending', 'booked', 'not_booked', 'unknown'];

    protected $fillable = [
        'external_load_id', 'source_id', 'referred_by', 'referred_by_type',
        'referral_action', 'external_url', 'user_confirmed_booked',
        'booking_status', 'rate_confirmation_url', 'notes',
    ];

    protected $casts = ['user_confirmed_booked' => 'boolean'];

    public function externalLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'external_load_id'); }
    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }
}
