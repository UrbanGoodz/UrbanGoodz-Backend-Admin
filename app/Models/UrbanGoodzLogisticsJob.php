<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzLogisticsJob extends Model
{
    protected $table = 'urban_goodz_logistics_jobs';

    protected $fillable = [
        'job_number', 'pickup_location', 'delivery_location',
        'pickup_by', 'deliver_by', 'description', 'weight_kg',
        'status', 'assigned_driver_id', 'offer_amount', 'admin_notes',
    ];

    protected $casts = [
        'pickup_by' => 'datetime', 'deliver_by' => 'datetime',
        'weight_kg' => 'decimal:2', 'offer_amount' => 'decimal:2',
    ];
}
