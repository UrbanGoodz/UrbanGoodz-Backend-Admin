<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzServiceDispute extends Model
{
    protected $table = 'urban_goodz_service_disputes';

    protected $fillable = [
        'service_request_id', 'provider_id', 'user_id', 'reason', 'details',
        'requested_amount_minor', 'status', 'resolution_notes',
        'resolved_amount_minor', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'requested_amount_minor' => 'integer',
        'resolved_amount_minor' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceRequest::class, 'service_request_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }
}
