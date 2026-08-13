<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzSourcingRecord extends Model
{
    protected $table = 'urban_goodz_sourcing_records';
    
    protected $fillable = [
        'sourceable_type',
        'sourceable_id',
        'source',
        'source_url',
        'source_date',
        'validation_state',
        'duplicate_state',
        'approval_state',
        'visibility_state',
        'failure_reason',
        'retry_state',
        'retry_count',
        'last_verified_at',
        'admin_id',
        'audit_log',
    ];

    protected $casts = [
        'audit_log' => 'array',
        'source_date' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public function sourceable()
    {
        return $this->morphTo();
    }
}
