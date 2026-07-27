<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCompensationRule extends Model
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PUBLISHED = 'published';
    public const STATE_ARCHIVED = 'archived';

    public const WORK_TYPES = ['delivery', 'route', 'logistics', 'medical'];

    protected $table = 'urban_goodz_compensation_rules';

    protected $fillable = [
        'rule_key', 'name', 'version', 'state', 'is_active',
        'work_type', 'service_scope', 'vehicle_scope', 'market_scope', 'zone_id',
        'priority', 'effective_from', 'effective_to',
        'components', 'splits', 'rounding_mode',
        'minimum_payout_cents', 'maximum_payout_cents',
        'created_by', 'published_by', 'published_at', 'notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'vehicle_scope' => 'array',
        'market_scope' => 'array',
        'components' => 'array',
        'splits' => 'array',
        'priority' => 'integer',
        'zone_id' => 'integer',
        'minimum_payout_cents' => 'integer',
        'maximum_payout_cents' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function audits()
    {
        return $this->hasMany(UrbanGoodzCompensationRuleAudit::class, 'rule_id');
    }

    public function isPublished(): bool
    {
        return $this->state === self::STATE_PUBLISHED && $this->is_active;
    }

    /**
     * How specific this rule is. Used to break priority ties deterministically:
     * a zone rule beats a market rule, which beats a vehicle rule, and so on.
     */
    public function specificity(): int
    {
        $score = 0;
        $score += $this->zone_id !== null ? 8 : 0;
        $score += !empty($this->market_scope) ? 4 : 0;
        $score += !empty($this->vehicle_scope) ? 2 : 0;
        $score += $this->service_scope !== null ? 1 : 0;

        return $score;
    }
}
