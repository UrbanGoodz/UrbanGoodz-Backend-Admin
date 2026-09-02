<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzHistoricalReconstructionAuditTrail extends Model
{
    protected $table = 'urban_goodz_historical_reconstruction_audit_trail';

    protected $fillable = [
        'reconstruction_id',
        'configuration_id',
        'snapshot_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'description',
        'admin_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzHistoricalReconstructionConfiguration::class, 'configuration_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzHistoricalMonthlySnapshot::class, 'snapshot_id');
    }
}
