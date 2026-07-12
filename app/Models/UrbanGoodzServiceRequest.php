<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzServiceRequest extends Model
{
    protected $table = 'urban_goodz_service_requests';

    protected $fillable = [
        'customer_name', 'customer_email', 'customer_phone', 'service_type',
        'description', 'status', 'assigned_vendor_id', 'admin_notes',
        'preferred_dates', 'location',
    ];

    protected $casts = ['preferred_dates' => 'array'];

    public function assignedProvider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'assigned_vendor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(UrbanGoodzAppointment::class, 'service_request_id');
    }
}
