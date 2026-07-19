<?php

namespace App\Services\UrbanGoodz\Routing\DTOs;

class RouteStop
{
    public function __construct(
        public readonly int $packageId,
        public readonly string $trackingId,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $address,
        public readonly string $city = '',
        public readonly string $state = '',
        public readonly string $zip = '',
        public readonly ?string $recipientName = null,
        public readonly ?string $recipientPhone = null,
        public readonly string $priority = 'normal',
        public readonly ?string $deliveryWindowStart = null,
        public readonly ?string $deliveryWindowEnd = null,
        public readonly bool $isLocked = false,
        public readonly bool $isAgeRestricted = false,
        public readonly bool $requiresCustody = false,
        public readonly bool $requiresSignature = false,
        public readonly bool $requiresPhoto = false,
        public readonly ?float $weightLbs = null,
        public readonly ?float $volumeCubicFt = null,
        public readonly string $packageType = 'parcel',
        public readonly ?int $lockedRouteId = null,
        public readonly ?int $lockedStopOrder = null,
        public readonly ?int $sameAddressGroupId = null,
        public readonly int $packageCount = 1,
        public readonly ?int $manifestId = null,
    ) {}

    public static function fromPackageModel($pkg, ?int $sameAddressGroupId = null): self
    {
        return new self(
            packageId: $pkg->id,
            trackingId: $pkg->tracking_id ?? '',
            lat: (float)($pkg->dropoff_lat ?? 0),
            lng: (float)($pkg->dropoff_lng ?? 0),
            address: $pkg->dropoff_address ?? '',
            city: $pkg->dropoff_city ?? '',
            state: $pkg->dropoff_state ?? '',
            zip: $pkg->dropoff_zip ?? '',
            priority: $pkg->priority ?? 'normal',
            deliveryWindowStart: $pkg->delivery_window_start?->toIso8601String(),
            deliveryWindowEnd: $pkg->delivery_window_end?->toIso8601String(),
            isLocked: (bool)($pkg->delivery_completion_locked_until_verified ?? false),
            isAgeRestricted: (bool)($pkg->age_restricted ?? false),
            requiresCustody: (bool)($pkg->requires_custody ?? false),
            requiresSignature: (bool)($pkg->requires_signature ?? false),
            requiresPhoto: (bool)($pkg->requires_photo ?? false),
            weightLbs: $pkg->weight ? (float)$pkg->weight : null,
            volumeCubicFt: null,
            packageType: $pkg->package_type ?? 'parcel',
            lockedRouteId: null,
            lockedStopOrder: null,
            sameAddressGroupId: $sameAddressGroupId,
            packageCount: 1,
            manifestId: $pkg->manifest_id,
        );
    }

    public function hasValidCoordinates(): bool
    {
        return $this->lat != 0 && $this->lng != 0
            && $this->lat > -90 && $this->lat < 90
            && $this->lng > -180 && $this->lng < 180;
    }

    public function isUrgent(): bool
    {
        return in_array($this->priority, ['urgent', 'medical']);
    }

    public function addressKey(): string
    {
        return strtolower(trim(
            $this->address . '|' . $this->city . '|' . $this->state . '|' . $this->zip
        ));
    }
}
