<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzStrandedOffer extends Model
{
    protected $table = 'urban_goodz_stranded_offers';

    protected $fillable = [
        'request_id', 'responder_id', 'responder_type',
        'distance_miles', 'eta_minutes', 'broadcast_round',
        'response_mode', 'requested_amount_minor', 'status',
        'responder_rating', 'responder_trust_score', 'responder_completed_jobs',
        'offered_at', 'expires_at', 'responded_at', 'selected_at',
    ];

    protected $casts = [
        'distance_miles' => 'float',
        'eta_minutes' => 'integer',
        'broadcast_round' => 'integer',
        'requested_amount_minor' => 'integer',
        'responder_rating' => 'float',
        'responder_trust_score' => 'integer',
        'responder_completed_jobs' => 'integer',
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'selected_at' => 'datetime',
    ];

    public const MODE_VOLUNTEER = 'volunteer';
    public const MODE_TIPS_ONLY = 'tips_only';
    public const MODE_PAID = 'paid';

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedRequest::class, 'request_id');
    }

    /** Still open for the responder to answer. */
    public function isLive(): bool
    {
        return $this->status === 'offered'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Accepting does not win the job -- it puts the responder on the
     * customer's shortlist. Only selection assigns.
     */
    public function isSelectable(): bool
    {
        return $this->status === 'accepted';
    }

    /** What the responder will actually be paid, ignoring tips. */
    public function payableAmountMinor(): int
    {
        return $this->response_mode === self::MODE_PAID
            ? (int) $this->requested_amount_minor
            : 0;
    }
}
