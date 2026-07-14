<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLoadPreference extends Model
{
    protected $table = 'driver_load_preferences';

    protected $fillable = [
        'delivery_man_id',
        'min_rate_per_mile', 'max_deadhead_miles', 'max_total_distance',
        'preferred_origins', 'preferred_destinations', 'excluded_origins', 'excluded_destinations',
        'preferred_equipment', 'excluded_commodities',
        'prefer_home_routes', 'prefer_high_value', 'prefer_short_haul', 'prefer_long_haul',
        'open_to_hazmat', 'open_to_temperature_controlled',
        'max_hours_per_day', 'available_from', 'notes',
    ];

    protected $casts = [
        'min_rate_per_mile' => 'decimal:4',
        'max_deadhead_miles' => 'decimal:2',
        'max_total_distance' => 'decimal:2',
        'preferred_origins' => 'array',
        'preferred_destinations' => 'array',
        'excluded_origins' => 'array',
        'excluded_destinations' => 'array',
        'preferred_equipment' => 'array',
        'excluded_commodities' => 'array',
        'prefer_home_routes' => 'boolean',
        'prefer_high_value' => 'boolean',
        'prefer_short_haul' => 'boolean',
        'prefer_long_haul' => 'boolean',
        'open_to_hazmat' => 'boolean',
        'open_to_temperature_controlled' => 'boolean',
        'max_hours_per_day' => 'integer',
        'available_from' => 'datetime',
        'notes' => 'array',
    ];

    public function driver(): BelongsTo { return $this->belongsTo(DeliveryMan::class, 'delivery_man_id'); }
}
