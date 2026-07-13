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
    ];

    protected $casts = ['preferred_dates' => 'array', 'requested_start_at' => 'datetime', 'scheduled_at' => 'datetime', 'accepted_at' => 'datetime', 'completed_at' => 'datetime'];

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
}
