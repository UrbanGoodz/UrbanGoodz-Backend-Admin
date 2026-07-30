<?php

namespace App\Services\UrbanGoodz;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Everything the driver compensation resolver may consider.
 *
 * Driver pay is not universally "delivery charge minus admin fee" — that is
 * only one of many configurable models — so the context carries the operational
 * shape of the job rather than a money figure.
 */
final class DriverCompensationContext
{
    public readonly DateTimeImmutable $at;

    public function __construct(
        /** Job family, e.g. marketplace_delivery, business_routes, medical_courier. */
        public readonly string $policyType,
        public readonly ?int $zoneId = null,
        public readonly ?string $market = null,
        public readonly ?int $moduleId = null,
        public readonly ?int $businessClientId = null,
        public readonly ?int $contractId = null,
        public readonly ?int $routeId = null,
        /** dedicated | recurring */
        public readonly ?string $routeScope = null,
        public readonly ?string $serviceType = null,
        public readonly ?int $vehicleTypeId = null,
        public readonly ?string $loadType = null,
        public readonly ?string $medicalType = null,
        /** The specific assignment, when an approved per-assignment rate exists. */
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        DateTimeInterface|string|null $at = null,
    ) {
        $this->at = match (true) {
            $at instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($at),
            is_string($at) && $at !== '' => new DateTimeImmutable($at),
            default => new DateTimeImmutable(),
        };
    }
}
