<?php

namespace App\Services\UrbanGoodz\Compensation;

use App\Models\UrbanGoodzCompensationRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Selects exactly one rule for a context.
 *
 * Precedence, highest first:
 *   1. priority      (explicit operator intent)
 *   2. specificity   (zone > market > vehicle > service)
 *   3. version       (newest published version of an equally specific rule)
 *   4. id            (final deterministic tie-break — never random)
 *
 * Draft and archived rules are never resolvable. This is what keeps an
 * unfinished rule from silently paying drivers.
 */
final class RuleResolver
{
    public function resolve(CompensationContext $ctx, ?CarbonInterface $at = null): ?UrbanGoodzCompensationRule
    {
        $at = $at ?? Carbon::now();

        $candidates = UrbanGoodzCompensationRule::query()
            ->where('work_type', $ctx->workType)
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at);
            })
            ->get()
            ->filter(fn (UrbanGoodzCompensationRule $rule) => $this->matches($rule, $ctx))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sort(function ($a, $b) {
            return [$b->priority, $b->specificity(), $b->version, $b->id]
                <=> [$a->priority, $a->specificity(), $a->version, $a->id];
        })->first();
    }

    public function matches(UrbanGoodzCompensationRule $rule, CompensationContext $ctx): bool
    {
        if ($rule->service_scope !== null && $rule->service_scope !== $ctx->serviceScope) {
            return false;
        }

        if (!empty($rule->vehicle_scope)) {
            if ($ctx->vehicleType === null || !in_array($ctx->vehicleType, $rule->vehicle_scope, true)) {
                return false;
            }
        }

        if (!empty($rule->market_scope)) {
            if ($ctx->market === null || !in_array($ctx->market, $rule->market_scope, true)) {
                return false;
            }
        }

        if ($rule->zone_id !== null && $rule->zone_id !== $ctx->zoneId) {
            return false;
        }

        return true;
    }

    /**
     * All rules that would match, best first. Used by the admin simulator to
     * show operators why one rule won and what it displaced.
     *
     * @return array<int,UrbanGoodzCompensationRule>
     */
    public function explainCandidates(CompensationContext $ctx, ?CarbonInterface $at = null): array
    {
        $at = $at ?? Carbon::now();

        return UrbanGoodzCompensationRule::query()
            ->where('work_type', $ctx->workType)
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at);
            })
            ->get()
            ->filter(fn (UrbanGoodzCompensationRule $rule) => $this->matches($rule, $ctx))
            ->sort(function ($a, $b) {
                return [$b->priority, $b->specificity(), $b->version, $b->id]
                    <=> [$a->priority, $a->specificity(), $a->version, $a->id];
            })
            ->values()
            ->all();
    }
}
