<?php

namespace App\Services\UrbanGoodz\FinancialControl;

use App\Models\UrbanGoodzFinancialLedgerEntry;
use App\Models\UrbanGoodzFinancialRule;
use App\Models\UrbanGoodzReconciliationRun;
use App\Models\UrbanGoodzFinancialSettlementSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinancialControlService
{
    private const SCOPE_WEIGHT = [
        'platform' => 100,
        'service_type' => 200,
        'zone' => 300,
        'business' => 400,
        'provider' => 500,
        'driver' => 600,
    ];

    /**
     * Calculate a settlement without writing it.
     *
     * All money is integer cents. Distance is miles_milli (1 mile = 1000).
     */
    public function simulate(array $context, ?CarbonInterface $at = null): array
    {
        $context = $this->normalizeContext($context);
        $at ??= now();

        $commissionRule = $this->bestRule('business_commission', $context, $at);
        $commissionCents = $commissionRule
            ? $this->calculateRule($commissionRule, $context, $context['merchandise_subtotal_cents'])
            : 0;
        $commissionCents = min($context['merchandise_subtotal_cents'], $commissionCents);

        $compensationRules = $this->stackedRules('driver_compensation', $context, $at);
        $premiumRules = $this->stackedRules('driver_premium', $context, $at);
        $driverCompensationCents = $compensationRules
            ->merge($premiumRules)
            ->sum(fn (UrbanGoodzFinancialRule $rule) => $this->calculateRule(
                $rule,
                $context,
                $context['delivery_charge_cents']
            ));

        $feeRule = $this->bestRule('driver_admin_fee', $context, $at);
        $driverAdminFeeCents = $feeRule
            ? $this->calculateRule($feeRule, $context, $driverCompensationCents)
            : 0;
        $driverAdminFeeCents = min($driverCompensationCents, $driverAdminFeeCents);

        $providerProceedsCents = $context['merchandise_subtotal_cents'] - $commissionCents;
        $driverNetCents = $driverCompensationCents - $driverAdminFeeCents;
        $deliveryMarginCents = $context['delivery_charge_cents'] - $driverCompensationCents;

        return [
            'currency' => $context['currency'],
            // Commission never increases this value; it is deducted from provider proceeds.
            'shopper_total_cents' => $context['merchandise_subtotal_cents'] + $context['delivery_charge_cents'],
            'merchandise_subtotal_cents' => $context['merchandise_subtotal_cents'],
            'delivery_charge_cents' => $context['delivery_charge_cents'],
            'business_commission_cents' => $commissionCents,
            'provider_proceeds_cents' => $providerProceedsCents,
            'driver_compensation_cents' => $driverCompensationCents,
            'driver_admin_fee_cents' => $driverAdminFeeCents,
            'driver_net_cents' => $driverNetCents,
            'platform_delivery_margin_cents' => $deliveryMarginCents,
            'platform_net_cents' => $commissionCents + $driverAdminFeeCents + $deliveryMarginCents,
            'rules' => [
                'business_commission' => $this->ruleDecision($commissionRule),
                'driver_compensation' => $compensationRules->map(fn ($rule) => $this->ruleDecision($rule))->values()->all(),
                'driver_premiums' => $premiumRules->map(fn ($rule) => $this->ruleDecision($rule))->values()->all(),
                'driver_admin_fee' => $this->ruleDecision($feeRule),
            ],
            'inputs' => $context,
        ];
    }

    /**
     * Stable integration seam for completed routes, orders, bookings, and loads.
     */
    public function settle(
        string $sourceType,
        string|int $sourceId,
        array $context,
        string $idempotencyKey
    ): UrbanGoodzFinancialSettlementSnapshot {
        if ($sourceType === '' || (string) $sourceId === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException('Source type, source id, and idempotency key are required.');
        }

        if ($existing = UrbanGoodzFinancialSettlementSnapshot::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $context, $idempotencyKey) {
            if ($existing = UrbanGoodzFinancialSettlementSnapshot::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first()) {
                return $existing;
            }

            $result = $this->simulate($context);
            $inputs = $result['inputs'];

            $snapshot = UrbanGoodzFinancialSettlementSnapshot::create([
                'snapshot_number' => $this->number('UGS'),
                'source_type' => $sourceType,
                'source_id' => (string) $sourceId,
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $inputs['customer_id'],
                'business_id' => $inputs['business_id'],
                'provider_id' => $inputs['provider_id'],
                'driver_id' => $inputs['driver_id'],
                'service_type' => $inputs['service_type'],
                'currency' => $result['currency'],
                'shopper_total_cents' => $result['shopper_total_cents'],
                'merchandise_subtotal_cents' => $result['merchandise_subtotal_cents'],
                'delivery_charge_cents' => $result['delivery_charge_cents'],
                'business_commission_cents' => $result['business_commission_cents'],
                'provider_proceeds_cents' => $result['provider_proceeds_cents'],
                'driver_compensation_cents' => $result['driver_compensation_cents'],
                'driver_admin_fee_cents' => $result['driver_admin_fee_cents'],
                'driver_net_cents' => $result['driver_net_cents'],
                'platform_delivery_margin_cents' => $result['platform_delivery_margin_cents'],
                'platform_net_cents' => $result['platform_net_cents'],
                'refunded_cents' => 0,
                'status' => 'settled',
                'reconciliation_status' => 'pending',
                'rule_snapshot' => $result['rules'],
                'inputs' => $inputs,
                'settled_by_admin_id' => auth('admin')->id(),
                'settled_at' => now(),
            ]);

            $this->writeSettlementLedger($snapshot);
            $this->reconcile($snapshot, auth('admin')->id());

            return $snapshot->fresh(['ledgerEntries', 'reconciliationRuns']);
        });
    }

    public function refund(
        UrbanGoodzFinancialSettlementSnapshot $snapshot,
        int $amountCents,
        string $reason,
        string $idempotencyKey
    ): UrbanGoodzFinancialSettlementSnapshot {
        return $this->recordReversal($snapshot, $amountCents, $reason, $idempotencyKey, 'refund');
    }

    public function reverse(
        UrbanGoodzFinancialSettlementSnapshot $snapshot,
        string $reason,
        string $idempotencyKey
    ): UrbanGoodzFinancialSettlementSnapshot {
        $remaining = $snapshot->shopper_total_cents - $snapshot->refunded_cents;

        return $this->recordReversal($snapshot, $remaining, $reason, $idempotencyKey, 'reversal');
    }

    public function reconcile(
        UrbanGoodzFinancialSettlementSnapshot $snapshot,
        ?int $adminId = null
    ): UrbanGoodzReconciliationRun {
        $debits = (int) $snapshot->ledgerEntries()->where('direction', 'debit')->sum('amount_cents');
        $credits = (int) $snapshot->ledgerEntries()->where('direction', 'credit')->sum('amount_cents');
        $difference = $debits - $credits;
        $status = $difference === 0 ? 'balanced' : 'out_of_balance';

        $run = UrbanGoodzReconciliationRun::create([
            'run_number' => $this->number('UGR'),
            'settlement_snapshot_id' => $snapshot->id,
            'total_debits_cents' => $debits,
            'total_credits_cents' => $credits,
            'difference_cents' => $difference,
            'status' => $status,
            'details' => [
                'currency' => $snapshot->currency,
                'entry_count' => $snapshot->ledgerEntries()->count(),
            ],
            'run_by_admin_id' => $adminId,
            'ran_at' => now(),
        ]);

        $snapshot->update(['reconciliation_status' => $status]);

        return $run;
    }

    public function visibleSettlements(string $role, ?int $partyId = null): Builder
    {
        $query = UrbanGoodzFinancialSettlementSnapshot::query();

        return match ($role) {
            'master_admin', 'admin' => $query,
            'business' => $query->where('business_id', $partyId),
            'provider', 'vendor' => $query->where('provider_id', $partyId),
            'driver' => $query->where('driver_id', $partyId),
            'shopper', 'customer' => $query->where('customer_id', $partyId),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function bestRule(string $family, array $context, CarbonInterface $at): ?UrbanGoodzFinancialRule
    {
        return $this->matchingRules($family, $context, $at)->first();
    }

    private function stackedRules(string $family, array $context, CarbonInterface $at): Collection
    {
        return $this->matchingRules($family, $context, $at)
            ->unique('calculation_type')
            ->values();
    }

    private function matchingRules(string $family, array $context, CarbonInterface $at): Collection
    {
        $scopePairs = collect([
            ['platform', null],
            ['service_type', $context['service_type']],
            ['zone', $context['zone_id']],
            ['business', $context['business_id']],
            ['provider', $context['provider_id']],
            ['driver', $context['driver_id']],
        ])->filter(fn (array $pair) => $pair[0] === 'platform' || $pair[1] !== null);

        return UrbanGoodzFinancialRule::query()
            ->where('rule_family', $family)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $at))
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $at))
            ->where(fn (Builder $query) => $query
                ->whereNull('service_type')
                ->orWhere('service_type', $context['service_type']))
            ->where(function (Builder $query) use ($scopePairs) {
                foreach ($scopePairs as [$type, $key]) {
                    $query->orWhere(function (Builder $scope) use ($type, $key) {
                        $scope->where('scope_type', $type);
                        $key === null
                            ? $scope->whereNull('scope_key')
                            : $scope->where('scope_key', (string) $key);
                    });
                }
            })
            ->get()
            ->sortByDesc(fn (UrbanGoodzFinancialRule $rule) => sprintf(
                '%010d-%04d-%010d-%010d',
                $rule->priority,
                self::SCOPE_WEIGHT[$rule->scope_type] ?? 0,
                $rule->version,
                $rule->id
            ))
            ->values();
    }

    private function calculateRule(
        UrbanGoodzFinancialRule $rule,
        array $context,
        int $percentageBaseCents
    ): int {
        return max(0, match ($rule->calculation_type) {
            'percentage' => $this->roundDivide($percentageBaseCents * $rule->rate_basis_points, 10000),
            'per_mile' => $this->roundDivide($rule->amount_cents * $context['miles_milli'], 1000),
            'per_package' => $rule->amount_cents * $context['package_count'],
            'per_stop' => $rule->amount_cents * $context['stop_count'],
            'per_route' => $rule->amount_cents * $context['route_count'],
            'hourly' => $this->roundDivide($rule->amount_cents * $context['hours_minutes'], 60),
            'per_return' => $rule->amount_cents * $context['return_count'],
            'per_exception' => $rule->amount_cents * $context['exception_count'],
            default => $rule->amount_cents,
        });
    }

    private function writeSettlementLedger(UrbanGoodzFinancialSettlementSnapshot $snapshot): void
    {
        $prefix = $snapshot->idempotency_key.':settlement:';

        $this->writeSignedEntry($snapshot, 'settlement', 'shopper_clearing', -$snapshot->shopper_total_cents, 'shopper', $snapshot->customer_id, $prefix.'shopper');
        $this->writeSignedEntry($snapshot, 'settlement', 'provider_payable', $snapshot->provider_proceeds_cents, 'provider', $snapshot->provider_id, $prefix.'provider');
        $this->writeSignedEntry($snapshot, 'settlement', 'business_commission_revenue', $snapshot->business_commission_cents, 'platform', null, $prefix.'commission');
        $this->writeSignedEntry($snapshot, 'settlement', 'driver_payable', $snapshot->driver_net_cents, 'driver', $snapshot->driver_id, $prefix.'driver');
        $this->writeSignedEntry($snapshot, 'settlement', 'driver_admin_fee_revenue', $snapshot->driver_admin_fee_cents, 'platform', null, $prefix.'driver-fee');
        $this->writeSignedEntry($snapshot, 'settlement', 'delivery_margin', $snapshot->platform_delivery_margin_cents, 'platform', null, $prefix.'delivery-margin');
    }

    private function recordReversal(
        UrbanGoodzFinancialSettlementSnapshot $snapshot,
        int $amountCents,
        string $reason,
        string $idempotencyKey,
        string $event
    ): UrbanGoodzFinancialSettlementSnapshot {
        if ($amountCents <= 0 || $reason === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException('A positive refund amount, reason, and idempotency key are required.');
        }

        return DB::transaction(function () use ($snapshot, $amountCents, $reason, $idempotencyKey, $event) {
            $snapshot = UrbanGoodzFinancialSettlementSnapshot::lockForUpdate()->findOrFail($snapshot->id);
            if (UrbanGoodzFinancialLedgerEntry::where('idempotency_key', $idempotencyKey.':shopper')->exists()) {
                return $snapshot;
            }

            $remaining = $snapshot->shopper_total_cents - $snapshot->refunded_cents;
            if ($amountCents > $remaining) {
                throw new InvalidArgumentException('Refund exceeds the unsettled shopper total.');
            }

            $oldRefunded = $snapshot->refunded_cents;
            $newRefunded = $oldRefunded + $amountCents;
            $total = $snapshot->shopper_total_cents;
            if ($total <= 0) {
                throw new InvalidArgumentException('A zero-value settlement cannot be refunded.');
            }

            // Reverse the shopper clearing first, then each settlement component.
            $this->writeSignedEntry($snapshot, $event, 'shopper_clearing', $amountCents, 'shopper', $snapshot->customer_id, $idempotencyKey.':shopper', $reason);

            $signedComponents = [
                'provider_payable' => [$snapshot->provider_proceeds_cents, 'provider', $snapshot->provider_id],
                'business_commission_revenue' => [$snapshot->business_commission_cents, 'platform', null],
                'driver_payable' => [$snapshot->driver_net_cents, 'driver', $snapshot->driver_id],
                'driver_admin_fee_revenue' => [$snapshot->driver_admin_fee_cents, 'platform', null],
            ];

            $writtenSigned = $amountCents;
            foreach ($signedComponents as $account => [$originalSigned, $partyType, $partyId]) {
                $oldTarget = $this->roundDivide($originalSigned * $oldRefunded, $total);
                $newTarget = $this->roundDivide($originalSigned * $newRefunded, $total);
                $reversalSigned = -($newTarget - $oldTarget);
                $writtenSigned += $reversalSigned;
                $this->writeSignedEntry(
                    $snapshot,
                    $event,
                    $account,
                    $reversalSigned,
                    $partyType,
                    $partyId,
                    $idempotencyKey.':'.$account,
                    $reason
                );
            }

            // Delivery margin is the exact balancing component, preserving integer-cent reconciliation.
            $this->writeSignedEntry(
                $snapshot,
                $event,
                'delivery_margin',
                -$writtenSigned,
                'platform',
                null,
                $idempotencyKey.':delivery-margin',
                $reason
            );

            $status = $newRefunded === $total
                ? ($event === 'reversal' ? 'reversed' : 'refunded')
                : 'partially_refunded';
            $snapshot->update([
                'refunded_cents' => $newRefunded,
                'status' => $status,
            ]);
            $this->reconcile($snapshot, auth('admin')->id());

            return $snapshot->fresh(['ledgerEntries', 'reconciliationRuns']);
        });
    }

    private function writeSignedEntry(
        UrbanGoodzFinancialSettlementSnapshot $snapshot,
        string $event,
        string $account,
        int $signedCreditCents,
        ?string $partyType,
        ?int $partyId,
        string $idempotencyKey,
        ?string $reason = null
    ): void {
        if ($signedCreditCents === 0) {
            return;
        }

        UrbanGoodzFinancialLedgerEntry::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'entry_number' => $this->number('UGL'),
                'settlement_snapshot_id' => $snapshot->id,
                'event_type' => $event,
                'account_code' => $account,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'direction' => $signedCreditCents > 0 ? 'credit' : 'debit',
                'amount_cents' => abs($signedCreditCents),
                'currency' => $snapshot->currency,
                'reference' => $snapshot->snapshot_number,
                'metadata' => $reason ? ['reason' => $reason] : null,
            ]
        );
    }

    private function normalizeContext(array $context): array
    {
        $normalized = [
            'currency' => strtoupper((string) ($context['currency'] ?? 'USD')),
            'customer_id' => $this->nullableInteger($context['customer_id'] ?? null),
            'business_id' => $this->nullableInteger($context['business_id'] ?? null),
            'provider_id' => $this->nullableInteger($context['provider_id'] ?? null),
            'driver_id' => $this->nullableInteger($context['driver_id'] ?? null),
            'zone_id' => $this->nullableInteger($context['zone_id'] ?? null),
            'service_type' => (string) ($context['service_type'] ?? 'marketplace_delivery'),
            'merchandise_subtotal_cents' => $this->nonNegativeInteger($context, 'merchandise_subtotal_cents'),
            'delivery_charge_cents' => $this->nonNegativeInteger($context, 'delivery_charge_cents'),
            'miles_milli' => $this->nonNegativeInteger($context, 'miles_milli'),
            'package_count' => $this->nonNegativeInteger($context, 'package_count'),
            'stop_count' => $this->nonNegativeInteger($context, 'stop_count'),
            'route_count' => $this->nonNegativeInteger($context, 'route_count', 1),
            'hours_minutes' => $this->nonNegativeInteger($context, 'hours_minutes'),
            'return_count' => $this->nonNegativeInteger($context, 'return_count'),
            'exception_count' => $this->nonNegativeInteger($context, 'exception_count'),
        ];

        if ($normalized['currency'] === '' || strlen($normalized['currency']) > 8) {
            throw new InvalidArgumentException('Currency must be an ISO-style code up to 8 characters.');
        }

        return $normalized;
    }

    private function nonNegativeInteger(array $context, string $key, int $default = 0): int
    {
        $value = $context[$key] ?? $default;
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("{$key} must be a non-negative integer.");
        }

        return $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Party and zone identifiers must be positive integers.');
        }

        return $value;
    }

    private function ruleDecision(?UrbanGoodzFinancialRule $rule): ?array
    {
        if (! $rule) {
            return null;
        }

        return [
            'id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'version' => $rule->version,
            'name' => $rule->name,
            'family' => $rule->rule_family,
            'calculation_type' => $rule->calculation_type,
            'amount_cents' => $rule->amount_cents,
            'rate_basis_points' => $rule->rate_basis_points,
            'scope_type' => $rule->scope_type,
            'scope_key' => $rule->scope_key,
            'priority' => $rule->priority,
            'effective_from' => optional($rule->effective_from)?->toIso8601String(),
            'effective_to' => optional($rule->effective_to)?->toIso8601String(),
        ];
    }

    private function roundDivide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHisv').'-'.strtoupper(Str::random(6));
    }
}
