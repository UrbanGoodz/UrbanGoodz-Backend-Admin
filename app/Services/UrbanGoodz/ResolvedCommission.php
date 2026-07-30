<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzCommissionRule;

/**
 * The outcome of commission resolution, in integer cents.
 *
 * Carries enough detail to write a settlement snapshot and to explain the
 * number to a vendor without re-running the resolver.
 */
final class ResolvedCommission
{
    /** Resolved from a Master Admin rule row. */
    public const SOURCE_RULE = 'rule';
    /** Legacy per-store `stores.comission` override, pending migration to a rule. */
    public const SOURCE_LEGACY_STORE = 'legacy_store_override';
    /** Legacy global `business_settings.admin_commission`. */
    public const SOURCE_LEGACY_GLOBAL = 'legacy_global';

    public function __construct(
        public readonly int $qualifyingAmountCents,
        public readonly int $commissionAmountCents,
        public readonly int $partnerNetCents,
        public readonly string $calculationType,
        public readonly ?string $ratePercent,
        public readonly ?int $fixedAmountCents,
        public readonly string $basis,
        public readonly string $source,
        public readonly ?UrbanGoodzCommissionRule $rule = null,
        public readonly ?int $specificity = null,
    ) {
    }

    public function ruleId(): ?int
    {
        return $this->rule?->id;
    }

    public function ruleVersion(): ?int
    {
        return $this->rule?->version;
    }

    /**
     * The rule row as it stood at resolution, for the snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function ruleSnapshot(): ?array
    {
        return $this->rule?->attributesToArray();
    }

    public function balances(): bool
    {
        return $this->qualifyingAmountCents - $this->commissionAmountCents === $this->partnerNetCents;
    }
}
