<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzAppointment extends Model
{
    protected $table = 'urban_goodz_appointments';

    protected $fillable = [
        'service_request_id', 'service_provider_id', 'scheduled_at',
        'completed_at', 'status', 'notes',
    ];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
}
