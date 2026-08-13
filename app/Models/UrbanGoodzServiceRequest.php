<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzServiceRequest extends Model
{
    protected $table = 'urban_goodz_service_requests';

    protected $fillable = [
        'user_id', 'customer_name', 'customer_email', 'customer_phone', 'service_type',
        'description', 'status', 'assigned_vendor_id', 'admin_notes',
        'preferred_dates', 'location', 'provider_id', 'provider_service_id', 'location_mode',
        'location_details', 'requested_start_at', 'scheduled_at', 'quoted_amount_minor',
        'deposit_amount_minor', 'currency', 'provider_notes', 'cancellation_reason',
        'payment_status', 'accepted_at', 'completed_at',
        'active_quote_id', 'scheduled_end_at', 'amount_paid_minor', 'refunded_amount_minor',
        'canceled_by', 'canceled_at', 'service_area_id',
    ];

    protected $casts = [
        'preferred_dates' => 'array',
        'requested_start_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'amount_paid_minor' => 'integer',
        'refunded_amount_minor' => 'integer',
    ];

    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(UrbanGoodzAppointment::class, 'service_request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceBookingEvent::class, 'service_request_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(UrbanGoodzServiceQuote::class, 'service_request_id');
    }

    public function activeQuote(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceQuote::class, 'active_quote_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzProviderService::class, 'provider_service_id');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceArea::class, 'service_area_id');
    }
}
