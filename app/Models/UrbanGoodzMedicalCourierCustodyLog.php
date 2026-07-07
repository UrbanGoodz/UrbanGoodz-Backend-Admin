<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzMedicalCourierCustodyLog extends Model
{
    protected $table = 'urban_goodz_medical_courier_custody_logs';

    protected $fillable = ['job_id', 'action', 'handler_name', 'notes', 'logged_at'];

    protected $casts = ['logged_at' => 'datetime'];
}
