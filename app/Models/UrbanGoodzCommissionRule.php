<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Master Admin commission configuration.
 *
 * Populated dimensions narrow the rule; NULL dimensions are wildcards. See
 * {@see \App\Services\UrbanGoodz\UrbanGoodzCommissionResolver} for the
 * resolution order.
 */
class UrbanGoodzCommissionRule extends Model
{
    use SoftDeletes;

    public const CALC_PERCENTAGE = 'percentage';
    public const CALC_FIXED = 'fixed';

    /**
     * Specificity tiers, most specific first. The resolver derives a rule's
     * tier from which dimensions it populates, so precedence cannot drift out
     * of step with the data.
     */
    public const TIER_SUBJECT = 70;   // transaction/job-specific approved override
    public const TIER_CONTRACT = 60;  // contract-specific
    public const TIER_PARTNER = 50;   // business/store/provider-specific
    public const TIER_SERVICE = 40;   // service/load/route type
    public const TIER_MODULE = 30;    // module
    public const TIER_MARKET = 20;    // market or zone
    public const TIER_GLOBAL = 10;    // global fallback

    protected $table = 'urban_goodz_commission_rules';

    protected $fillable = [
        'name',
        'transaction_type', 'module_id', 'partner_type', 'partner_id',
        'contract_id', 'service_type', 'zone_id', 'market',
        'subject_type', 'subject_id',
        'commission_enabled', 'calculation_type', 'rate_percent',
        'fixed_amount_cents', 'basis', 'minimum_cents', 'maximum_cents',
        'priority', 'effective_from', 'effective_to', 'is_active',
        'version', 'internal_reason',
        'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected $casts = [
        'commission_enabled' => 'boolean',
        'is_active' => 'boolean',
        'rate_percent' => 'decimal:4',
        'fixed_amount_cents' => 'integer',
        'minimum_cents' => 'integer',
        'maximum_cents' => 'integer',
        'priority' => 'integer',
        'version' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rules in force at $at. A null bound is open-ended.
     */
    public function scopeInForceAt(Builder $query, \DateTimeInterface $at): Builder
    {
        return $query
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            });
    }

    /**
     * How specific this rule is. Higher wins.
     */
    public function specificity(): int
    {
        if ($this->subject_type !== null && $this->subject_id !== null) {
            return self::TIER_SUBJECT;
        }
        if ($this->contract_id !== null) {
            return self::TIER_CONTRACT;
        }
        if ($this->partner_type !== null && $this->partner_id !== null) {
            return self::TIER_PARTNER;
        }
        if ($this->service_type !== null) {
            return self::TIER_SERVICE;
        }
        if ($this->module_id !== null) {
            return self::TIER_MODULE;
        }
        if ($this->zone_id !== null || $this->market !== null) {
            return self::TIER_MARKET;
        }

        return self::TIER_GLOBAL;
    }

    public function isPercentage(): bool
    {
        return $this->calculation_type === self::CALC_PERCENTAGE;
    }
}
