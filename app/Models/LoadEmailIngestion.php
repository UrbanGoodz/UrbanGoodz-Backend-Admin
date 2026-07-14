<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadEmailIngestion extends Model
{
    protected $table = 'load_email_ingestions';

    const STATUSES = ['received', 'extracted', 'pending_review', 'approved', 'rejected', 'imported'];

    protected $fillable = [
        'source_email_id', 'from_address', 'from_name', 'subject', 'received_at', 'raw_body',
        'origin_city', 'origin_state', 'destination_city', 'destination_state',
        'equipment_type', 'weight', 'commodity', 'rate',
        'broker_name', 'broker_contact', 'broker_reference', 'confidence_score',
        'status', 'external_load_id', 'processed_by', 'processed_at',
        'rejection_reason', 'metadata',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'weight' => 'decimal:2',
        'rate' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function externalLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'external_load_id'); }

    public function scopePendingReview($query) { return $query->where('status', 'pending_review'); }
    public function scopeLowConfidence($query, float $threshold = 0.6) { return $query->where('confidence_score', '<', $threshold); }
}
