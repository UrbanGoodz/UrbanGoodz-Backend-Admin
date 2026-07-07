<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzServiceRequest extends Model
{
    protected $table = 'urban_goodz_service_requests';

    protected $fillable = [
        'customer_name', 'customer_email', 'customer_phone', 'service_type',
        'description', 'status', 'assigned_vendor_id', 'admin_notes',
        'preferred_dates', 'location',
    ];

    protected $casts = ['preferred_dates' => 'array'];
}
