<?php

namespace App\Services\UrbanGoodz;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Everything the resolver is allowed to consider when choosing a commission
 * rule. Anything not expressed here cannot influence the outcome, which is what
 * makes resolution reproducible for a refund months later.
 */
final class CommissionContext
{
    public const TYPE_MARKETPLACE_ORDER = 'marketplace_order';
    public const TYPE_LOAD_BOARD = 'load_board';
    public const TYPE_DISPATCHER_FEE = 'dispatcher_fee';
    public const TYPE_MEDICAL_COURIER = 'medical_courier';
    public const TYPE_BUSINESS_ROUTE = 'business_route';
    public const TYPE_SERVICE_BOOKING = 'service_booking';
    public const TYPE_RENTAL = 'rental';
    public const TYPE_CREATOR = 'creator';
    public const TYPE_FASHION_FIT = 'fashion_fit';
    public const TYPE_ORDER_ANYWHERE = 'order_anywhere';

    public readonly DateTimeImmutable $at;

    public function __construct(
        public readonly string $transactionType,
        /** Gross qualifying revenue for the commission basis, in integer cents. */
        public readonly int $qualifyingAmountCents,
        public readonly ?int $moduleId = null,
        public readonly ?string $partnerType = null,
        public readonly ?int $partnerId = null,
        public readonly ?int $contractId = null,
        public readonly ?string $serviceType = null,
        public readonly ?int $zoneId = null,
        public readonly ?string $market = null,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        DateTimeInterface|string|null $at = null,
    ) {
        // Eloquent hands back a Carbon instance for a persisted row but a raw
        // string for an unsaved one, so accept either.
        $this->at = match (true) {
            $at instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($at),
            is_string($at) && $at !== '' => new DateTimeImmutable($at),
            default => new DateTimeImmutable(),
        };
    }
}
