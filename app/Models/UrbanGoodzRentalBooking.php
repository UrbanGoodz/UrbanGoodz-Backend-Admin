<?php

namespace App\Models;

use App\Contracts\Payments\PayableRequest;
use Illuminate\Database\Eloquent\Model;

class UrbanGoodzRentalBooking extends Model implements PayableRequest
{
    protected $table = 'urban_goodz_rental_bookings';

    /**
     * Rent and damage deposit are two independent money flows. The rent is
     * authorized then captured; the deposit is normally authorized and never
     * captured - voided on a clean return, or captured in part when an
     * inspection finds damage.
     */
    public const PAYMENT_STATUSES = [
        'pending',
        'authorized',
        'captured',
        'partially_refunded',
        'refunded',
        'cancelled',
        'authorization_failed',
        'capture_failed',
    ];

    public const DEPOSIT_STATUSES = [
        'pending',
        'held',
        'released',
        'partially_claimed',
        'claimed',
    ];

    protected $fillable = [
        'rental_asset_id', 'customer_id', 'customer_name', 'customer_phone',
        'start_at', 'end_at', 'status', 'payment_status', 'deposit_status',
        'verification_status', 'total_amount', 'deposit_amount',
        'admin_notes', 'customer_notes',

        'authorized_amount', 'captured_amount', 'refunded_amount',
        'deposit_authorized_amount', 'deposit_captured_amount', 'deposit_refunded_amount',
        'payment_provider', 'provider_reference',
        'authorization_reference', 'capture_reference', 'refund_reference',
        'deposit_authorization_reference', 'deposit_capture_reference', 'deposit_refund_reference',
        'payment_authorized_at', 'payment_captured_at', 'payment_refunded_at',
        'deposit_authorized_at', 'deposit_released_at', 'authorization_expires_at',
        'currency',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',

        'authorized_amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'deposit_authorized_amount' => 'decimal:2',
        'deposit_captured_amount' => 'decimal:2',
        'deposit_refunded_amount' => 'decimal:2',

        'payment_authorized_at' => 'datetime',
        'payment_captured_at' => 'datetime',
        'payment_refunded_at' => 'datetime',
        'deposit_authorized_at' => 'datetime',
        'deposit_released_at' => 'datetime',
        'authorization_expires_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(UrbanGoodzRentalAsset::class, 'rental_asset_id');
    }

    public function inspections()
    {
        return $this->hasMany(UrbanGoodzRentalInspection::class, 'rental_booking_id');
    }

    // ---------------------------------------------------- PayableRequest

    public function getPayableId(): int
    {
        return (int) $this->id;
    }

    /**
     * Bookings have no separate reference column, so the id is formatted into
     * a stable one. It must not change once a provider has seen it.
     */
    public function getPayableReference(): string
    {
        return 'RENT-' . $this->id;
    }

    public function getPayableCustomerId(): ?int
    {
        return $this->customer_id === null ? null : (int) $this->customer_id;
    }

    public function getCaptureReference(): ?string
    {
        return $this->capture_reference;
    }

    public function getProviderReference(): ?string
    {
        return $this->provider_reference;
    }

    public function getPayableFeature(): string
    {
        return 'rental';
    }
}
