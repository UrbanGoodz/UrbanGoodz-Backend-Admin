<?php

namespace App\Models;

use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzStrandedRequest extends Model
{
    protected $table = 'urban_goodz_stranded_requests';

    protected $fillable = [
        'uuid', 'request_number', 'user_id', 'zone_id', 'service_id', 'service_slug', 'status',
        'latitude', 'longitude', 'address', 'location_notes',
        'destination_latitude', 'destination_longitude', 'destination_address',
        'vehicle_type', 'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_color', 'vehicle_plate',
        'notes', 'photos', 'passenger_count', 'safety_status', 'is_emergency', 'allow_samaritans',
        'help_request_fee_minor', 'help_request_fee_status', 'help_request_fee_transaction_id', 'broadcast_at',
        'reward_offer_minor', 'escrow_status', 'escrow_transaction_id', 'escrow_released_at',
        'tip_minor', 'currency', 'priority_upgrade_minor',
        'assigned_responder_type', 'assigned_responder_id', 'selected_offer_id', 'assigned_at',
        'broadcast_radius_miles', 'broadcast_expires_at', 'broadcast_round',
        'escalation_due_at', 'escalated_at',
        'en_route_at', 'arrived_at', 'completed_at', 'customer_confirmed_at',
        'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'destination_latitude' => 'float',
        'destination_longitude' => 'float',
        'is_emergency' => 'boolean',
        'allow_samaritans' => 'boolean',
        'photos' => 'array',
        'passenger_count' => 'integer',
        'help_request_fee_minor' => 'integer',
        'reward_offer_minor' => 'integer',
        'tip_minor' => 'integer',
        'priority_upgrade_minor' => 'integer',
        'broadcast_radius_miles' => 'integer',
        'broadcast_round' => 'integer',
        'broadcast_at' => 'datetime',
        'escrow_released_at' => 'datetime',
        'assigned_at' => 'datetime',
        'broadcast_expires_at' => 'datetime',
        'escalation_due_at' => 'datetime',
        'escalated_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // No dispatch or money values are declared here. Radius ladder, fee,
    // offer TTL and escalation window are all administrator-configurable and
    // resolve through UrbanGoodzStrandedSettings, so changing a price is an
    // admin action rather than a deploy.

    public function service(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedService::class, 'service_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(UrbanGoodzStrandedOffer::class, 'request_id');
    }

    /** The next radius to broadcast at, or null once the ladder is exhausted. */
    public function nextRadius(): ?int
    {
        foreach (UrbanGoodzStrandedSettings::radiusLadder() as $radius) {
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

    /**
     * The fee buys the broadcast. Once that has happened it is not refundable,
     * so this is the flag any refund path must consult.
     */
    public function feeIsConsumed(): bool
    {
        return $this->broadcast_at !== null;
    }
}
