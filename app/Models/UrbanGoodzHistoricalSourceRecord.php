<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzHistoricalSourceRecord extends Model
{
    protected $table = 'urban_goodz_historical_source_records';

    protected $fillable = [
        'configuration_id',
        'snapshot_id',
        'source_type',
        'source_description',
        'source_date',
        'source_data',
        'confidence_score',
        'confidence_label',
        'notes',
        'overrides_reconstruction',
        'imported_by',
    ];

    protected $casts = [
        'source_date' => 'date',
        'source_data' => 'array',
        'confidence_score' => 'decimal:2',
        'overrides_reconstruction' => 'boolean',
    ];

    const SOURCE_TYPES = [
        'actual_records' => 'Actual Surviving Urban Goodz Records',
        'imported_records' => 'Imported Historical Records',
        'tax_records' => 'Tax Records',
        'bank_records' => 'Bank Records',
        'payment_processor' => 'Payment Processor Records',
        'delivery_records' => 'Delivery/Order Records',
        'business_records' => 'Business Records',
        'communications' => 'Contemporaneous Communications',
        'public_records' => 'Public Business Records/Articles',
        'owner_recollection' => 'Owner Historical Reconstruction',
        'mathematical_estimation' => 'Mathematical Estimation',
    ];

    const CONFIDENCE_LABELS = [
        'verified' => 1.0,
        'high' => 0.85,
        'medium' => 0.65,
        'estimated' => 0.40,
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzHistoricalReconstructionConfiguration::class, 'configuration_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzHistoricalMonthlySnapshot::class, 'snapshot_id');
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? $this->source_type;
    }
}
