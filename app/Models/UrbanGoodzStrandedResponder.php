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
    ];

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
