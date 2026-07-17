<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzDriverPricingPolicy extends Model
{
    protected $table = 'urban_goodz_driver_pricing_policies';

    protected $fillable = [
        'policy_type', 'name', 'payout_model',
        'fixed_amount', 'base_fare', 'rate_per_mile', 'rate_per_minute',
        'rate_per_stop', 'rate_per_package', 'revenue_percentage',
        'dynamic_pricing_enabled', 'recommendation_only', 'auto_apply_within_limits',
        'dispatcher_approval_required', 'admin_approval_required',
        'live_pricing_enabled', 'sandbox_pricing_enabled',
        'zone_id', 'vehicle_multipliers', 'urgency_premium',
        'deadhead_pay_rate', 'waiting_pay_rate', 'return_pay_rate', 'exception_pay_rate',
        'minimum_payout', 'maximum_payout', 'minimum_margin',
        'effective_from', 'effective_to', 'is_active'
    ];

    protected $casts = [
        'fixed_amount' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'rate_per_mile' => 'decimal:2',
        'rate_per_minute' => 'decimal:2',
        'rate_per_stop' => 'decimal:2',
        'rate_per_package' => 'decimal:2',
        'revenue_percentage' => 'decimal:2',
        'dynamic_pricing_enabled' => 'boolean',
        'recommendation_only' => 'boolean',
        'auto_apply_within_limits' => 'boolean',
        'dispatcher_approval_required' => 'boolean',
        'admin_approval_required' => 'boolean',
        'live_pricing_enabled' => 'boolean',
        'sandbox_pricing_enabled' => 'boolean',
        'vehicle_multipliers' => 'array',
        'urgency_premium' => 'decimal:2',
        'deadhead_pay_rate' => 'decimal:2',
        'waiting_pay_rate' => 'decimal:2',
        'return_pay_rate' => 'decimal:2',
        'exception_pay_rate' => 'decimal:2',
        'minimum_payout' => 'decimal:2',
        'maximum_payout' => 'decimal:2',
        'minimum_margin' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
    }

    public function scopeForTypeAndZone($query, string $type, ?int $zoneId = null)
    {
        return $query->where('policy_type', $type)
            ->where('zone_id', $zoneId);
    }
}
