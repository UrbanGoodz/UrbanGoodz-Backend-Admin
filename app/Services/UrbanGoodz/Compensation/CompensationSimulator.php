<?php

namespace App\Services\UrbanGoodz\Compensation;

use App\Models\UrbanGoodzCompensationRule;
use Carbon\CarbonInterface;

/**
 * Dry-run a compensation calculation.
 *
 * Never persists. Returns both the winning calculation and the rules that were
 * considered, so an operator can see why one rule beat another before publishing
 * a change that affects real payouts.
 */
final class CompensationSimulator
{
    public function __construct(
        private readonly CompensationEngine $engine = new CompensationEngine(),
        private readonly RuleResolver $resolver = new RuleResolver(),
    ) {
    }

    public function simulate(CompensationContext $ctx, ?CarbonInterface $at = null): array
    {
        $candidates = $this->resolver->explainCandidates($ctx, $at);

        if ($candidates === []) {
            return [
                'matched' => false,
                'reason' => 'No published, active, in-effect rule matches this context.',
                'context' => $ctx->toArray(),
                'candidates' => [],
                'calculation' => null,
            ];
        }

        $winner = $candidates[0];

        return [
            'matched' => true,
            'context' => $ctx->toArray(),
            'selected_rule' => $this->describe($winner, true),
            'candidates' => array_map(
                fn (UrbanGoodzCompensationRule $rule) => $this->describe($rule, $rule->id === $winner->id),
                $candidates
            ),
            'calculation' => $this->engine->calculateWithRule($winner, $ctx),
        ];
    }

    /**
     * Evaluate an unpublished draft against a context without publishing it.
     */
    public function simulateDraft(UrbanGoodzCompensationRule $draft, CompensationContext $ctx): array
    {
        return [
            'matched' => $this->resolver->matches($draft, $ctx),
            'context' => $ctx->toArray(),
            'selected_rule' => $this->describe($draft, true),
            'candidates' => [$this->describe($draft, true)],
            'calculation' => $this->engine->calculateWithRule($draft, $ctx),
            'note' => 'Draft simulation — this rule is not resolvable for live payouts.',
        ];
    }

    /**
     * Compare two rules over the same context. Used when revising a published
     * rule so the operator sees the payout delta before publishing.
     */
    public function compare(
        UrbanGoodzCompensationRule $current,
        UrbanGoodzCompensationRule $proposed,
        CompensationContext $ctx
    ): array {
        $a = $this->engine->calculateWithRule($current, $ctx);
        $b = $this->engine->calculateWithRule($proposed, $ctx);

        return [
            'context' => $ctx->toArray(),
            'current' => ['rule' => $this->describe($current, false), 'calculation' => $a],
            'proposed' => ['rule' => $this->describe($proposed, false), 'calculation' => $b],
            'driver_delta_cents' => $b['driver_cents'] - $a['driver_cents'],
            'driver_delta' => Money::toDecimal($b['driver_cents'] - $a['driver_cents']),
        ];
    }

    private function describe(UrbanGoodzCompensationRule $rule, bool $selected): array
    {
        return [
            'id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'name' => $rule->name,
            'version' => $rule->version,
            'state' => $rule->state,
            'is_active' => $rule->is_active,
            'priority' => $rule->priority,
            'specificity' => $rule->specificity(),
            'work_type' => $rule->work_type,
            'service_scope' => $rule->service_scope,
            'vehicle_scope' => $rule->vehicle_scope,
            'market_scope' => $rule->market_scope,
            'zone_id' => $rule->zone_id,
            'selected' => $selected,
        ];
    }
}
