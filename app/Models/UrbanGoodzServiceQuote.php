<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzServiceQuote extends Model
{
    protected $fillable = [
        'service_request_id', 'provider_id', 'amount_minor', 'deposit_minor',
        'currency', 'notes', 'scheduled_at', 'expires_at', 'status',
        'accepted_at', 'declined_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'deposit_minor' => 'integer',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceRequest::class, 'service_request_id');
    }
}
