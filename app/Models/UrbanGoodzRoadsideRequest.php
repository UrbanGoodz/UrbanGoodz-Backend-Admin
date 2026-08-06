<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzRoadsideRequest extends Model
{
    protected $table = 'urban_goodz_roadside_requests';

    protected $fillable = [
        'uuid', 'request_number', 'user_id', 'zone_id', 'service_id', 'service_slug', 'status',
        'latitude', 'longitude', 'address', 'location_notes',
        'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_color', 'vehicle_plate',
        'notes', 'photos', 'is_emergency', 'allow_samaritans',
        'assigned_provider_type', 'assigned_provider_id', 'assigned_at',
        'quoted_amount_minor', 'platform_fee_minor', 'tip_minor', 'currency',
        'payment_status', 'payment_transaction_id',
        'broadcast_radius_miles', 'broadcast_expires_at', 'broadcast_round',
        'en_route_at', 'arrived_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_emergency' => 'boolean',
        'allow_samaritans' => 'boolean',
        'quoted_amount_minor' => 'integer',
        'platform_fee_minor' => 'integer',
        'tip_minor' => 'integer',
        'broadcast_radius_miles' => 'integer',
        'broadcast_round' => 'integer',
        'photos' => 'array',
        'assigned_at' => 'datetime',
        'broadcast_expires_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** Radius ladder from the spec: keep widening until somebody accepts. */
    public const RADIUS_LADDER = [10, 20, 35, 50];

    /** Seconds a provider has to accept before the offer lapses. */
    public const OFFER_TTL_SECONDS = 45;

    public function service(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzRoadsideService::class, 'service_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(UrbanGoodzRoadsideOffer::class, 'request_id');
    }

    /**
     * The next radius to broadcast at, or null once 50 miles is exhausted.
     */
    public function nextRadius(): ?int
    {
        foreach (self::RADIUS_LADDER as $radius) {
            if ($radius > (int) $this->broadcast_radius_miles) {
                return $radius;
            }
        }
        return null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled', 'expired'], true);
    }
}
