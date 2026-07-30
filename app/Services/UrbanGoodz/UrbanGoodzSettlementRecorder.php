<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzSettlementSnapshot;
use Illuminate\Database\QueryException;

/**
 * Writes the immutable settlement snapshot for one transaction.
 *
 * Recording is idempotent on `idempotency_key`: a replayed finalization returns
 * the existing snapshot rather than creating a second one. That is what stops a
 * duplicate "delivered" callback from settling the same money twice.
 */
class UrbanGoodzSettlementRecorder
{
    /**
     * @param array<string, mixed> $inputs verified operational data (miles, packages, stops, ...)
     * @param array<string, mixed> $driver optional side-B figures in integer cents
     */
    public function record(
        string $subjectType,
        int $subjectId,
        CommissionContext $context,
        ResolvedCommission $commission,
        array $inputs = [],
        array $driver = [],
        ?string $idempotencyKey = null,
        string $currency = 'USD'
    ): UrbanGoodzSettlementSnapshot {
        $key = $idempotencyKey ?? $this->defaultKey($subjectType, $subjectId, $context);

        $existing = UrbanGoodzSettlementSnapshot::where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $attributes = [
            'settlement_number' => $this->settlementNumber($subjectType, $subjectId),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'transaction_type' => $context->transactionType,
            'module_id' => $context->moduleId,
            'partner_type' => $context->partnerType,
            'partner_id' => $context->partnerId,

            'commission_rule_id' => $commission->ruleId(),
            'commission_rule_version' => $commission->ruleVersion(),
            'commission_calculation_type' => $commission->calculationType,
            'commission_rate_percent' => $commission->ratePercent,
            'commission_fixed_amount_cents' => $commission->fixedAmountCents,
            'commission_basis' => $commission->basis,
            'qualifying_amount_cents' => $commission->qualifyingAmountCents,
            'commission_amount_cents' => $commission->commissionAmountCents,
            'partner_gross_cents' => $commission->qualifyingAmountCents,
            'partner_net_cents' => $commission->partnerNetCents,

            'driver_comp_rule_id' => $driver['rule_id'] ?? null,
            'driver_comp_rule_version' => $driver['rule_version'] ?? null,
            'driver_comp_method' => $driver['method'] ?? null,
            'driver_gross_cents' => $driver['gross_cents'] ?? null,
            'driver_admin_fee_cents' => $driver['admin_fee_cents'] ?? null,
            'driver_net_cents' => $driver['net_cents'] ?? null,

            'currency' => $currency,
            'inputs' => $inputs + ['commission_source' => $commission->source, 'specificity' => $commission->specificity],
            'rule_snapshot' => $commission->ruleSnapshot(),
            'idempotency_key' => $key,
            'effective_at' => $context->at,
        ];

        try {
            return UrbanGoodzSettlementSnapshot::create($attributes);
        } catch (QueryException $exception) {
            // Concurrent finalization raced us to the unique key; the winner's
            // snapshot is authoritative.
            $winner = UrbanGoodzSettlementSnapshot::where('idempotency_key', $key)->first();

            if ($winner !== null) {
                return $winner;
            }

            throw $exception;
        }
    }

    private function defaultKey(string $subjectType, int $subjectId, CommissionContext $context): string
    {
        return sprintf(
            '%s:%d:%s:commission',
            str_replace('\\', '_', $subjectType),
            $subjectId,
            $context->transactionType
        );
    }

    private function settlementNumber(string $subjectType, int $subjectId): string
    {
        $prefix = strtoupper(substr(class_basename($subjectType), 0, 3));

        return sprintf('UGS-%s-%d-%s', $prefix, $subjectId, bin2hex(random_bytes(4)));
    }
}
