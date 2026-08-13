<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzImportBatch extends Model
{
    protected $table = 'urban_goodz_import_batches';

    protected $fillable = [
        'city',
        'state',
        'category',
        'module',
        'queue_type',
        'priority',
        'source_query',
        'source_platforms',
        'source_payload',
        'classification_summary',
        'validation_summary',
        'preview_summary',
        'total_found',
        'total_imported',
        'total_needs_review',
        'total_failed',
        'status',
        'attempt_count',
        'max_attempts',
        'failure_code',
        'failure_message',
        'retry_after',
        'admin_id',
        'approved_by',
        'approved_at',
        'rolled_back_by',
        'rolled_back_at',
        'rollback_reason',
        'completed_at',
    ];

    protected $casts = [
        'source_platforms' => 'array',
        'source_payload' => 'array',
        'classification_summary' => 'array',
        'validation_summary' => 'array',
        'preview_summary' => 'array',
        'priority' => 'integer',
        'total_found' => 'integer',
        'total_imported' => 'integer',
        'total_needs_review' => 'integer',
        'total_failed' => 'integer',
        'attempt_count' => 'integer',
        'max_attempts' => 'integer',
        'retry_after' => 'datetime',
        'admin_id' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'rolled_back_by' => 'integer',
        'rolled_back_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sourcedBusinesses()
    {
        return $this->hasMany(UrbanGoodzSourcedBusiness::class, 'import_batch_id');
    }

    public function sourcedProducts()
    {
        return $this->hasMany(UrbanGoodzSourcedProduct::class, 'import_batch_id');
    }

    public function revisions()
    {
        return $this->hasMany(UrbanGoodzDataCenterRevision::class, 'import_batch_id');
    }
}
