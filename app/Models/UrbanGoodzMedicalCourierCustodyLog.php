<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzMedicalCourierCustodyLog extends Model
{
    protected $table = 'urban_goodz_medical_courier_custody_logs';

    protected $fillable = [
        'job_id', 'action', 'handler_name', 'handler_role',
        'handler_id', 'signature_path', 'notes', 'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzMedicalCourierJob::class, 'job_id');
    }
}
