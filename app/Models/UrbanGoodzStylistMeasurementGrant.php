<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Shopper's explicit permission for one stylist to read the approved
 * measurements (and, only if separately allowed, the body photos) attached to
 * one stylist request.
 */
class UrbanGoodzStylistMeasurementGrant extends Model
{
    protected $table = 'urban_goodz_stylist_measurement_grants';

    protected $guarded = ['id'];

    protected $casts = [
        'measurements_allowed' => 'boolean',
        'photos_allowed' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function allowsMeasurements(): bool
    {
        return $this->isActive() && $this->measurements_allowed === true;
    }

    public function allowsPhotos(): bool
    {
        return $this->isActive() && $this->photos_allowed === true;
    }
}
