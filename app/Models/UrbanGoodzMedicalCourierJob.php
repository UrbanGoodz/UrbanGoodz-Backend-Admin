<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzMedicalCourierJob extends Model
{
    protected $table = 'urban_goodz_medical_courier_jobs';

    protected $fillable = [
        'job_number', 'pickup_location', 'delivery_location',
        'specimen_type', 'requires_refrigeration', 'is_biological_hazard',
        'status', 'assigned_driver_id', 'admin_notes',
    ];

    protected $casts = [
        'requires_refrigeration' => 'boolean', 'is_biological_hazard' => 'boolean',
    ];
}
