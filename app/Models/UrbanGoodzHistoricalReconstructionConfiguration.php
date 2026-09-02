<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzHistoricalReconstructionConfiguration extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_historical_reconstruction_configurations';

    protected $fillable = [
        'configuration_name',
        'reconstruction_start_date',
        'reconstruction_end_date',
        'owner_name',
        'owner_non_delivery_months',
        'baseline_monthly_orders',
        'baseline_average_order_value',
        'baseline_order_commission_pct',
        'baseline_delivery_fee',
        'baseline_platform_delivery_fee_pct',
        'baseline_active_drivers',
        'baseline_avg_monthly_net',
        'orders_variation_pct',
        'aov_variation_pct',
        'delivery_fee_variation_pct',
        'driver_count_variation_pct',
        'operating_expense_ratio',
        'evidentiary_disclaimer',
        'is_active',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reconstruction_start_date' => 'date',
        'reconstruction_end_date' => 'date',
        'owner_non_delivery_months' => 'array',
        'baseline_monthly_orders' => 'decimal:2',
        'baseline_average_order_value' => 'decimal:2',
        'baseline_order_commission_pct' => 'decimal:2',
        'baseline_delivery_fee' => 'decimal:2',
        'baseline_platform_delivery_fee_pct' => 'decimal:2',
        'baseline_active_drivers' => 'integer',
        'baseline_avg_monthly_net' => 'decimal:2',
        'orders_variation_pct' => 'decimal:2',
        'aov_variation_pct' => 'decimal:2',
        'delivery_fee_variation_pct' => 'decimal:2',
        'driver_count_variation_pct' => 'decimal:2',
        'operating_expense_ratio' => 'decimal:2',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(UrbanGoodzHistoricalMonthlySnapshot::class, 'configuration_id');
    }

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(UrbanGoodzHistoricalSourceRecord::class, 'configuration_id');
    }

    public function auditTrail(): HasMany
    {
        return $this->hasMany(UrbanGoodzHistoricalReconstructionAuditTrail::class, 'configuration_id');
    }

    public function getMonthCountAttribute(): int
    {
        return $this->reconstruction_start_date->diffInMonths($this->reconstruction_end_date) + 1;
    }

    public function getEvidentiaryDisclaimerAttribute(): ?string
    {
        return $this->attributes['evidentiary_disclaimer']
            ?? 'IMPORTANT: The original production database was lost during a subsequent application rebuild. This report reconstructs historical business operations using surviving business records and owner-provided historical operating assumptions. Reconstructed values are estimates and are not represented as recovered original database records.';
    }
}
