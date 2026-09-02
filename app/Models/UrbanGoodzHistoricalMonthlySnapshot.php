<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzHistoricalMonthlySnapshot extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_historical_monthly_snapshots';

    protected $fillable = [
        'reconstruction_id',
        'configuration_id',
        'snapshot_month',
        'snapshot_year',
        'snapshot_month_number',
        'estimated_orders',
        'estimated_average_order_value',
        'estimated_total_order_value',
        'estimated_order_commission_revenue',
        'estimated_delivery_fee_per_order',
        'estimated_delivery_fee_revenue',
        'estimated_platform_delivery_fee_revenue',
        'estimated_total_platform_revenue',
        'estimated_active_driver_count',
        'estimated_owner_deliveries',
        'estimated_driver_payouts',
        'estimated_operating_expenses',
        'estimated_net_income',
        'calculated_net_income',
        'net_income_variance_from_baseline',
        'source_type',
        'reconstruction_method',
        'reconstruction_version',
        'confidence',
        'notes',
        'assumptions_used',
        'calculation_log',
        'created_by',
    ];

    protected $casts = [
        'snapshot_month' => 'date',
        'snapshot_year' => 'integer',
        'snapshot_month_number' => 'integer',
        'estimated_orders' => 'decimal:2',
        'estimated_average_order_value' => 'decimal:2',
        'estimated_total_order_value' => 'decimal:2',
        'estimated_order_commission_revenue' => 'decimal:2',
        'estimated_delivery_fee_per_order' => 'decimal:2',
        'estimated_delivery_fee_revenue' => 'decimal:2',
        'estimated_platform_delivery_fee_revenue' => 'decimal:2',
        'estimated_total_platform_revenue' => 'decimal:2',
        'estimated_active_driver_count' => 'integer',
        'estimated_owner_deliveries' => 'integer',
        'estimated_driver_payouts' => 'decimal:2',
        'estimated_operating_expenses' => 'decimal:2',
        'estimated_net_income' => 'decimal:2',
        'calculated_net_income' => 'decimal:2',
        'net_income_variance_from_baseline' => 'decimal:2',
        'assumptions_used' => 'array',
        'calculation_log' => 'array',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzHistoricalReconstructionConfiguration::class, 'configuration_id');
    }

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(UrbanGoodzHistoricalSourceRecord::class, 'snapshot_id');
    }

    public function auditTrail(): HasMany
    {
        return $this->hasMany(UrbanGoodzHistoricalReconstructionAuditTrail::class, 'snapshot_id');
    }

    public function getMonthLabelAttribute(): string
    {
        return $this->snapshot_month->format('F Y');
    }

    public function getConfidenceLabelAttribute(): string
    {
        return match ($this->confidence) {
            'verified' => 'VERIFIED BUSINESS ACTIVITY',
            'high' => 'HIGH CONFIDENCE',
            'medium' => 'MEDIUM CONFIDENCE',
            'estimated' => 'RECONSTRUCTED BUSINESS ACTIVITY',
            default => 'UNKNOWN',
        };
    }
}
