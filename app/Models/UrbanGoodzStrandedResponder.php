<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A responder's availability and standing.
 *
 * Going offline flips a flag rather than removing the row, so rating, trust
 * score and completion history survive across sessions.
 */
class UrbanGoodzStrandedResponder extends Model
{
    protected $table = 'urban_goodz_stranded_responders';

    protected $fillable = [
        'user_id', 'responder_type', 'is_online',
        'last_latitude', 'last_longitude', 'last_seen_at', 'max_travel_miles',
        'vehicle_make', 'vehicle_model', 'vehicle_color', 'vehicle_plate',
        'capabilities', 'safety_ack_at',
        'rating', 'trust_score', 'completed_jobs', 'declined_jobs', 'missed_jobs',
        'active_request_id',
        'stripe_connect_account_id', 'stripe_onboarding_status',
        'stripe_charges_enabled', 'stripe_payouts_enabled', 'stripe_details_submitted_at',
        'profile_photo_path', 'vehicle_photo_path',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
        'last_seen_at' => 'datetime',
        'max_travel_miles' => 'integer',
        'capabilities' => 'array',
        'safety_ack_at' => 'datetime',
        'rating' => 'float',
        'trust_score' => 'integer',
        'completed_jobs' => 'integer',
        'stripe_charges_enabled' => 'boolean',
        'stripe_payouts_enabled' => 'boolean',
        'stripe_details_submitted_at' => 'datetime',
    ];

    protected $appends = ['profile_photo_url', 'vehicle_photo_url'];

    /** True once Stripe has confirmed this responder can actually receive a transfer. */
    public function canReceivePayouts(): bool
    {
        return $this->stripe_payouts_enabled && $this->stripe_connect_account_id !== null;
    }

    /**
     * A complete, ready-to-dispatch Samaritan profile: photo on file, and a
     * registered vehicle. Everything else (rating, trust score) is earned
     * over time and is not a precondition for accepting a first request.
     */
    public function hasCompleteIdentity(): bool
    {
        return $this->profile_photo_path !== null
            && $this->vehicle_make !== null
            && $this->vehicle_photo_path !== null;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? \Illuminate\Support\Facades\Storage::disk(\App\CentralLogics\Helpers::getDisk())->url($this->profile_photo_path)
            : null;
    }

    public function getVehiclePhotoUrlAttribute(): ?string
    {
        return $this->vehicle_photo_path
            ? \Illuminate\Support\Facades\Storage::disk(\App\CentralLogics\Helpers::getDisk())->url($this->vehicle_photo_path)
            : null;
    }

    /** Precise coordinates are never sent to customers -- only distance is. */
    protected $hidden = ['last_latitude', 'last_longitude'];

    /**
     * Available means online, recently seen, and not already mid-rescue.
     *
     * The staleness check matters: an app killed without going offline would
     * otherwise leave a responder looking available forever, and dispatch
     * would keep broadcasting into a void while the customer waits.
     */
    public function scopeAvailable(Builder $query, int $staleMinutes = 10): Builder
    {
        return $query->where('is_online', true)
            ->whereNull('active_request_id')
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->where('last_seen_at', '>=', now()->subMinutes($staleMinutes));
    }
}
