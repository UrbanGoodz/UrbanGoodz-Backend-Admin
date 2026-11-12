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
        'uses_alternate_vehicle', 'vehicle_make', 'vehicle_model', 'vehicle_color',
        'vehicle_plate', 'vehicle_photo_path', 'identity_ready_at',
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
        'uses_alternate_vehicle' => 'boolean',
        'identity_ready_at' => 'datetime',
    ];

    public const MODE_VOLUNTEER = 'volunteer';
    public const MODE_TIPS_ONLY = 'tips_only';
    public const MODE_PAID = 'paid';

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedRequest::class, 'request_id');
    }

    /**
     * The responder's profile row. Not a true Eloquent relation -- offers key
     * on (responder_id, responder_type), which is a unique pair on the
     * responders table, not its primary key -- so this is a plain lookup.
     */
    public function responderProfile(): ?UrbanGoodzStrandedResponder
    {
        return UrbanGoodzStrandedResponder::where('user_id', $this->responder_id)
            ->where('responder_type', $this->responder_type)
            ->first();
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
     *
     * A samaritan additionally is not selectable until identity_ready_at is
     * set: the customer must be able to see who is actually coming and in
     * what vehicle before they can choose them. Professional/vendor
     * responder types skip this -- they go through the separate
     * vendor/business verification system, not the Samaritan trust model.
     */
    public function isSelectable(): bool
    {
        if ($this->status !== 'accepted') {
            return false;
        }

        return $this->responder_type !== 'samaritan' || $this->identity_ready_at !== null;
    }

    /**
     * What the customer sees for "who is coming and in what car": the
     * responder's verified profile, unless this specific offer declared a
     * different vehicle, in which case the override takes over entirely --
     * mixing an old plate with a new photo would defeat the point.
     */
    public function effectiveVehicle(): array
    {
        if ($this->uses_alternate_vehicle) {
            return [
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'color' => $this->vehicle_color,
                'plate' => $this->vehicle_plate,
                'photo_path' => $this->vehicle_photo_path,
            ];
        }

        $profile = $this->responderProfile();

        return [
            'make' => $profile?->vehicle_make,
            'model' => $profile?->vehicle_model,
            'color' => $profile?->vehicle_color,
            'plate' => $profile?->vehicle_plate,
            'photo_path' => $profile?->vehicle_photo_path,
        ];
    }

    /**
     * True once the customer has enough to recognise this responder: a
     * profile photo, and a vehicle from either source. Called after accept()
     * for the common case (already true from a complete profile, so
     * acceptance and readiness happen in the same instant) and again after
     * an alternate-vehicle submission.
     */
    public function computeIdentityReady(): bool
    {
        $profile = $this->responderProfile();
        if (!$profile?->profile_photo_path) {
            return false;
        }

        $vehicle = $this->effectiveVehicle();
        return !empty($vehicle['make']) && !empty($vehicle['photo_path']);
    }

    /** What the responder will actually be paid, ignoring tips. */
    public function payableAmountMinor(): int
    {
        return $this->response_mode === self::MODE_PAID
            ? (int) $this->requested_amount_minor
            : 0;
    }
}
