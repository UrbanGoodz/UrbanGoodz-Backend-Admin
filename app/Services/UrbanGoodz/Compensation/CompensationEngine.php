<?php

namespace App\Services\UrbanGoodz\Compensation;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Calculates driver compensation from a versioned rule.
 *
 * Order of operations is fixed and deliberate:
 *   1. Terminal states (cancellation / failed delivery / failed handoff) replace
 *      the earning calculation entirely — you cannot earn mileage on a job you
 *      did not perform.
 *   2. Earning components accumulate.
 *   3. Vehicle multiplier scales the earned subtotal.
 *   4. Peak/surge applies on top.
 *   5. Minimum/maximum clamp the *earned* amount only.
 *   6. Pass-through money (tolls, reimbursements, tips) is added after the
 *      clamp, so a minimum payout is never "satisfied" by a driver's own toll
 *      reimbursement and a maximum never confiscates a tip.
 */
final class CompensationEngine
{
    public function __construct(
        private readonly RuleResolver $resolver = new RuleResolver(),
    ) {
    }

    /**
     * Calculate using a rule resolved from the database.
     */
    public function calculate(CompensationContext $ctx, ?CarbonInterface $at = null): array
    {
        $rule = $this->resolver->resolve($ctx, $at);

        if ($rule === null) {
            throw new RuntimeException(sprintf(
                'No published compensation rule matches work_type [%s] service_scope [%s].',
                $ctx->workType,
                $ctx->serviceScope ?? 'any'
            ));
        }

        return $this->calculateWithRule($rule, $ctx);
    }

    /**
     * Calculate against a specific rule. Used by the simulator and by tests so a
     * rule can be evaluated without publishing it.
     */
    public function calculateWithRule(UrbanGoodzCompensationRule $rule, CompensationContext $ctx): array
    {
        $components = $rule->components ?? [];
        ComponentCalculator::assertSupported($components);

        $mode = $rule->rounding_mode ?: Money::HALF_UP;
        $calculator = new ComponentCalculator($mode);
        $breakdown = new CompensationBreakdown($rule->rule_key, $rule->version, $mode);

        $terminal = $this->firstTerminal($components, $ctx, $calculator, $breakdown);

        if ($terminal === null) {
            $this->applyEarningComponents($components, $ctx, $calculator, $breakdown);
            $earned = $breakdown->subtotalCents();
            $earned = $this->applyVehicleMultiplier($components, $ctx, $breakdown, $earned, $mode);
            $earned = $this->applyPeakSurge($components, $ctx, $breakdown, $earned, $mode);
        } else {
            $earned = $terminal;
            $breakdown->note('terminal_state', 'Terminal state replaced earning components', [
                'cancelled' => $ctx->isCancelled,
                'failed_delivery' => $ctx->isFailedDelivery,
                'failed_handoff' => $ctx->isFailedHandoff,
            ]);
        }

        $beforeClamp = $earned;
        $earned = Money::clamp($earned, $rule->minimum_payout_cents, $rule->maximum_payout_cents);

        if ($earned !== $beforeClamp) {
            $breakdown->note('clamp', 'Minimum/maximum payout applied', [
                'before_cents' => $beforeClamp,
                'after_cents' => $earned,
                'minimum_cents' => $rule->minimum_payout_cents,
                'maximum_cents' => $rule->maximum_payout_cents,
            ]);
        }

        $passThrough = $this->applyPassThrough($components, $ctx, $calculator, $breakdown);

        $driverCents = $earned + $passThrough;

        $splits = (new SplitCalculator($mode))->calculate(
            $rule->splits ?? [],
            $ctx,
            $earned,
            $passThrough
        );

        return [
            'rule_id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'rule_version' => $rule->version,
            'rounding_mode' => $mode,
            'earned_cents' => $earned,
            'pass_through_cents' => $passThrough,
            'driver_cents' => $driverCents,
            'driver_amount' => Money::toDecimal($driverCents),
            'breakdown' => $breakdown->toArray(),
            'splits' => $splits,
            'explanation' => $this->buildExplanation($breakdown, $earned, $passThrough, $driverCents, $splits),
        ];
    }

    private function firstTerminal(
        array $components,
        CompensationContext $ctx,
        ComponentCalculator $calculator,
        CompensationBreakdown $breakdown
    ): ?int {
        foreach (ComponentCalculator::TERMINAL as $component) {
            if (!array_key_exists($component, $components)) {
                continue;
            }

            $result = $calculator->compute($component, (array) $components[$component], $ctx);

            if ($result !== null) {
                [$cents, $label, $inputs] = $result;
                $breakdown->add($component, $label, $cents, $inputs);

                return $cents;
            }
        }

        // A terminal state with no configured component pays nothing, and says so.
        if ($ctx->isCancelled || $ctx->isFailedDelivery || $ctx->isFailedHandoff) {
            $breakdown->note('terminal_unconfigured', 'Terminal state with no configured compensation', [
                'cancelled' => $ctx->isCancelled,
                'failed_delivery' => $ctx->isFailedDelivery,
                'failed_handoff' => $ctx->isFailedHandoff,
            ]);

            return 0;
        }

        return null;
    }

    private function applyEarningComponents(
        array $components,
        CompensationContext $ctx,
        ComponentCalculator $calculator,
        CompensationBreakdown $breakdown
    ): void {
        $skip = array_merge(
            ComponentCalculator::TERMINAL,
            ComponentCalculator::PASS_THROUGH,
            ['peak_surge', 'vehicle_multiplier']
        );

        foreach ($components as $component => $config) {
            if (in_array($component, $skip, true)) {
                continue;
            }

            $result = $calculator->compute($component, (array) $config, $ctx);

            if ($result !== null) {
                [$cents, $label, $inputs] = $result;
                $breakdown->add($component, $label, $cents, $inputs);
            }
        }
    }

    private function applyVehicleMultiplier(
        array $components,
        CompensationContext $ctx,
        CompensationBreakdown $breakdown,
        int $earned,
        string $mode
    ): int {
        if (!isset($components['vehicle_multiplier']) || $ctx->vehicleType === null) {
            return $earned;
        }

        $map = (array) $components['vehicle_multiplier'];
        $multiplier = (float) ($map[$ctx->vehicleType] ?? 1.0);

        if ($multiplier < 0) {
            throw new InvalidArgumentException('Vehicle multiplier may not be negative.');
        }

        if ($multiplier === 1.0) {
            return $earned;
        }

        $adjusted = Money::round($earned * $multiplier, $mode);

        $breakdown->note('vehicle_multiplier', 'Vehicle multiplier applied', [
            'vehicle_type' => $ctx->vehicleType,
            'multiplier' => $multiplier,
            'before_cents' => $earned,
            'after_cents' => $adjusted,
        ]);

        return $adjusted;
    }

    private function applyPeakSurge(
        array $components,
        CompensationContext $ctx,
        CompensationBreakdown $breakdown,
        int $earned,
        string $mode
    ): int {
        if (!isset($components['peak_surge']) || !$ctx->isPeak) {
            return $earned;
        }

        $config = (array) $components['peak_surge'];
        $added = 0;

        if (isset($config['percent'])) {
            $percent = (float) $config['percent'];
            if ($percent < 0) {
                throw new InvalidArgumentException('Peak surge percent may not be negative.');
            }
            $added += Money::percent($earned, $percent, $mode);
        }

        if (isset($config['amount_cents'])) {
            $amount = (int) $config['amount_cents'];
            if ($amount < 0) {
                throw new InvalidArgumentException('Peak surge amount may not be negative.');
            }
            $added += $amount;
        }

        if ($added === 0) {
            return $earned;
        }

        $breakdown->add('peak_surge', 'Peak/surge adjustment', $added, [
            'percent' => $config['percent'] ?? null,
            'amount_cents' => $config['amount_cents'] ?? null,
            'base_cents' => $earned,
        ]);

        return $earned + $added;
    }

    private function applyPassThrough(
        array $components,
        CompensationContext $ctx,
        ComponentCalculator $calculator,
        CompensationBreakdown $breakdown
    ): int {
        $total = 0;

        foreach (ComponentCalculator::PASS_THROUGH as $component) {
            if (!array_key_exists($component, $components)) {
                continue;
            }

            $result = $calculator->compute($component, (array) $components[$component], $ctx);

            if ($result !== null) {
                [$cents, $label, $inputs] = $result;
                $breakdown->add($component, $label, $cents, $inputs);
                $total += $cents;
            }
        }

        return $total;
    }

    private function buildExplanation(
        CompensationBreakdown $breakdown,
        int $earned,
        int $passThrough,
        int $driverCents,
        array $splits
    ): string {
        $lines = [$breakdown->explain()];
        $lines[] = sprintf('  %-28s %10s', 'EARNED (after clamp)', Money::toDecimal($earned));
        $lines[] = sprintf('  %-28s %10s', 'PASS-THROUGH', Money::toDecimal($passThrough));
        $lines[] = sprintf('  %-28s %10s', 'DRIVER TOTAL', Money::toDecimal($driverCents));
        $lines[] = sprintf(
            '  %-28s %10s  (platform %s, reconciles: %s)',
            'SPLIT BASIS',
            Money::toDecimal($splits['basis_cents']),
            Money::toDecimal($splits['platform_cents']),
            $splits['reconciles'] ? 'yes' : 'NO'
        );

        return implode("\n", $lines);
    }

    /**
     * Persist an immutable record of a calculation.
     */
    public function record(array $calculation, CompensationContext $ctx, bool $final = false): UrbanGoodzCompensationResult
    {
        return UrbanGoodzCompensationResult::create([
            'rule_id' => $calculation['rule_id'],
            'rule_key' => $calculation['rule_key'],
            'rule_version' => $calculation['rule_version'],
            'subject_type' => $ctx->subjectType ?? 'unknown',
            'subject_id' => $ctx->subjectId ?? 0,
            'driver_id' => $ctx->driverId,
            'context' => $ctx->toArray(),
            'breakdown' => $calculation['breakdown'],
            'splits' => $calculation['splits'],
            'explanation' => $calculation['explanation'],
            'gross_cents' => $calculation['splits']['basis_cents'] ?? 0,
            'driver_cents' => $calculation['driver_cents'],
            'is_final' => $final,
            'finalized_at' => $final ? Carbon::now() : null,
        ]);
    }
}
