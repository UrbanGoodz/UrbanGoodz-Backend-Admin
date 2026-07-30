<?php

namespace App\Services\UrbanGoodz;

use App\Exceptions\CommissionConfigurationException;
use App\Models\BusinessSetting;
use App\Models\Store;
use App\Models\UrbanGoodzCommissionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the Master Admin commission configuration for one transaction.
 *
 * The settlement *rule* is universal — Urban Goodz retains a commission from
 * the revenue attributable to the business or provider — while the *rate* is
 * configurable per module, business, provider, contract, service, load, route,
 * market, job and effective period.
 *
 * Resolution order, most specific first:
 *
 *   1. Transaction/job-specific approved override  (subject_type + subject_id)
 *   2. Contract-specific rule                      (contract_id)
 *   3. Business/store/provider-specific rule       (partner_type + partner_id)
 *   4. Service/load/route-type rule                (service_type)
 *   5. Module rule                                 (module_id)
 *   6. Market or zone rule                         (zone_id | market)
 *   7. Global fallback rule                        (all dimensions null)
 *   8. Safe failure with Admin alert
 *
 * Ties within a tier break on `priority` (higher first), then the later
 * `effective_from`, then the higher id — so the outcome is deterministic and a
 * general rule can never displace a more specific active one.
 *
 * Two legacy sources are consulted between tiers 7 and 8 so that introducing
 * this resolver changes no existing amount: the per-store `stores.comission`
 * column and the global `business_settings.admin_commission`. Both are
 * reported honestly in {@see ResolvedCommission::$source} and are expected to
 * be migrated into real rules.
 */
class UrbanGoodzCommissionResolver
{
    public function resolve(CommissionContext $context): ResolvedCommission
    {
        $rule = $this->selectRule($context);

        if ($rule !== null) {
            return $this->applyRule($rule, $context);
        }

        $legacy = $this->resolveLegacy($context);

        if ($legacy !== null) {
            return $legacy;
        }

        $exception = CommissionConfigurationException::missing($context);
        $this->alertAdmin($context, $exception->getMessage());

        throw $exception;
    }

    /**
     * The commission percentage for a context, without needing the qualifying
     * amount.
     *
     * The legacy marketplace settlement path in OrderLogic needs the rate
     * before it has finished computing the amount the rate applies to (the
     * store-discount split at OrderLogic:176 uses it, and the order amount is
     * not assembled until line 202). It also does the arithmetic itself, so it
     * needs a percentage rather than a computed figure.
     *
     * A fixed-amount rule cannot be expressed as a percentage without knowing
     * the base, so one is rejected outright rather than silently mis-settled.
     * Fixed marketplace commission becomes available once that path is moved
     * onto {@see resolve()}.
     */
    public function resolvePercentageRate(CommissionContext $context): string
    {
        $rule = $this->selectRule($context);

        if ($rule !== null) {
            if (! $rule->commission_enabled) {
                return '0';
            }

            if (! $rule->isPercentage()) {
                $exception = CommissionConfigurationException::invalidRate(
                    $context,
                    sprintf(
                        'rule %d is a fixed-amount commission, which the legacy marketplace '
                        . 'settlement path cannot apply. Configure a percentage rule for this store.',
                        $rule->id
                    )
                );
                $this->alertAdmin($context, $exception->getMessage());

                throw $exception;
            }

            // Validates range and raises on an out-of-bounds rate.
            $this->percentageOf(0, (string) $rule->rate_percent, $context);

            return (string) $rule->rate_percent;
        }

        $legacy = $this->resolveLegacy($context);

        if ($legacy !== null && $legacy->ratePercent !== null) {
            return $legacy->ratePercent;
        }

        $exception = CommissionConfigurationException::missing($context);
        $this->alertAdmin($context, $exception->getMessage());

        throw $exception;
    }

    /**
     * Candidate rules matching every dimension, ordered deterministically.
     */
    private function selectRule(CommissionContext $context): ?UrbanGoodzCommissionRule
    {
        $candidates = UrbanGoodzCommissionRule::query()
            ->active()
            ->inForceAt($context->at)
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'transaction_type', $context->transactionType))
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'module_id', $context->moduleId))
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'contract_id', $context->contractId))
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'service_type', $context->serviceType))
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'zone_id', $context->zoneId))
            ->where(fn (Builder $q) => $this->matchOrWildcard($q, 'market', $context->market))
            ->where(fn (Builder $q) => $this->matchPair(
                $q, 'partner_type', $context->partnerType, 'partner_id', $context->partnerId
            ))
            ->where(fn (Builder $q) => $this->matchPair(
                $q, 'subject_type', $context->subjectType, 'subject_id', $context->subjectId
            ))
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortByDesc(fn (UrbanGoodzCommissionRule $rule) => [
                $rule->specificity(),
                $rule->priority,
                $rule->effective_from?->getTimestamp() ?? 0,
                $rule->id,
            ])
            ->first();
    }

    /**
     * A rule dimension matches when it is NULL (wildcard) or equal to context.
     */
    private function matchOrWildcard(Builder $query, string $column, int|string|null $value): Builder
    {
        if ($value === null) {
            return $query->whereNull($column);
        }

        return $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, $value));
    }

    /**
     * Two-column dimensions (partner, subject) must match as a pair — a rule
     * scoped to store 14 must not match vendor 14.
     */
    private function matchPair(
        Builder $query,
        string $typeColumn,
        ?string $typeValue,
        string $idColumn,
        ?int $idValue
    ): Builder {
        if ($typeValue === null || $idValue === null) {
            return $query->whereNull($typeColumn)->whereNull($idColumn);
        }

        return $query->where(function (Builder $q) use ($typeColumn, $typeValue, $idColumn, $idValue) {
            $q->where(fn (Builder $inner) => $inner->whereNull($typeColumn)->whereNull($idColumn))
                ->orWhere(fn (Builder $inner) => $inner->where($typeColumn, $typeValue)->where($idColumn, $idValue));
        });
    }

    private function applyRule(UrbanGoodzCommissionRule $rule, CommissionContext $context): ResolvedCommission
    {
        $qualifying = $context->qualifyingAmountCents;

        if (! $rule->commission_enabled) {
            return new ResolvedCommission(
                qualifyingAmountCents: $qualifying,
                commissionAmountCents: 0,
                partnerNetCents: $qualifying,
                calculationType: $rule->calculation_type,
                ratePercent: null,
                fixedAmountCents: null,
                basis: $rule->basis,
                source: ResolvedCommission::SOURCE_RULE,
                rule: $rule,
                specificity: $rule->specificity(),
            );
        }

        $commission = $rule->isPercentage()
            ? $this->percentageOf($qualifying, (string) $rule->rate_percent, $context)
            : $this->fixedAmount($rule, $context);

        $commission = $this->clamp($commission, $rule->minimum_cents, $rule->maximum_cents, $qualifying);

        return new ResolvedCommission(
            qualifyingAmountCents: $qualifying,
            commissionAmountCents: $commission,
            partnerNetCents: $qualifying - $commission,
            calculationType: $rule->calculation_type,
            ratePercent: $rule->isPercentage() ? (string) $rule->rate_percent : null,
            fixedAmountCents: $rule->isPercentage() ? null : (int) $rule->fixed_amount_cents,
            basis: $rule->basis,
            source: ResolvedCommission::SOURCE_RULE,
            rule: $rule,
            specificity: $rule->specificity(),
        );
    }

    private function percentageOf(int $qualifyingCents, ?string $rate, CommissionContext $context): int
    {
        if ($rate === null || ! is_numeric($rate)) {
            throw CommissionConfigurationException::invalidRate($context, 'percentage rule has no numeric rate');
        }

        $rateFloat = (float) $rate;

        if ($rateFloat < 0.0) {
            throw CommissionConfigurationException::invalidRate($context, "rate {$rate}% is below 0");
        }

        if ($rateFloat > 100.0) {
            throw CommissionConfigurationException::invalidRate($context, "rate {$rate}% is above 100");
        }

        return (int) round($qualifyingCents * $rateFloat / 100, 0, PHP_ROUND_HALF_UP);
    }

    private function fixedAmount(UrbanGoodzCommissionRule $rule, CommissionContext $context): int
    {
        if ($rule->fixed_amount_cents === null) {
            throw CommissionConfigurationException::invalidRate($context, 'fixed rule has no amount');
        }

        if ($rule->fixed_amount_cents < 0) {
            throw CommissionConfigurationException::invalidRate($context, 'fixed amount is negative');
        }

        return (int) $rule->fixed_amount_cents;
    }

    /**
     * Commission can never be negative, exceed the qualifying revenue, or fall
     * outside a configured floor/ceiling.
     */
    private function clamp(int $commission, ?int $minimum, ?int $maximum, int $qualifying): int
    {
        if ($minimum !== null) {
            $commission = max($commission, $minimum);
        }

        if ($maximum !== null) {
            $commission = min($commission, $maximum);
        }

        return max(0, min($commission, max(0, $qualifying)));
    }

    /**
     * Legacy marketplace configuration, consulted only when no rule matches.
     */
    private function resolveLegacy(CommissionContext $context): ?ResolvedCommission
    {
        if ($context->transactionType !== CommissionContext::TYPE_MARKETPLACE_ORDER) {
            return null;
        }

        $qualifying = $context->qualifyingAmountCents;

        if ($context->partnerType === 'store' && $context->partnerId !== null) {
            $storeRate = Store::withoutGlobalScopes()
                ->whereKey($context->partnerId)
                ->value('comission');

            // NULL means "inherit the global rate"; 0.00 is a real 0% override.
            if ($storeRate !== null) {
                $commission = $this->percentageOf($qualifying, (string) $storeRate, $context);

                return new ResolvedCommission(
                    qualifyingAmountCents: $qualifying,
                    commissionAmountCents: $commission,
                    partnerNetCents: $qualifying - $commission,
                    calculationType: UrbanGoodzCommissionRule::CALC_PERCENTAGE,
                    ratePercent: (string) $storeRate,
                    fixedAmountCents: null,
                    basis: 'merchandise_subtotal',
                    source: ResolvedCommission::SOURCE_LEGACY_STORE,
                );
            }
        }

        $global = BusinessSetting::where('key', 'admin_commission')->value('value');

        if ($global === null || ! is_numeric($global)) {
            return null;
        }

        $commission = $this->percentageOf($qualifying, (string) $global, $context);

        return new ResolvedCommission(
            qualifyingAmountCents: $qualifying,
            commissionAmountCents: $commission,
            partnerNetCents: $qualifying - $commission,
            calculationType: UrbanGoodzCommissionRule::CALC_PERCENTAGE,
            ratePercent: (string) $global,
            fixedAmountCents: null,
            basis: 'merchandise_subtotal',
            source: ResolvedCommission::SOURCE_LEGACY_GLOBAL,
        );
    }

    private function alertAdmin(CommissionContext $context, string $message): void
    {
        Log::error('[UrbanGoodz][commission] ' . $message, [
            'transaction_type' => $context->transactionType,
            'module_id' => $context->moduleId,
            'partner_type' => $context->partnerType,
            'partner_id' => $context->partnerId,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
            'qualifying_amount_cents' => $context->qualifyingAmountCents,
        ]);
    }
}
