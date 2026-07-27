<?php

namespace App\Services\UrbanGoodz\Compensation;

use InvalidArgumentException;

/**
 * Immutable input for a compensation calculation.
 *
 * Everything the engine is allowed to consider lives here. The engine never
 * reads request state, session state, or client-supplied payout figures; a
 * mobile client can supply measurements (miles, minutes, stops) but never an
 * amount. The backend is authoritative.
 */
final class CompensationContext
{
    public function __construct(
        public readonly string $workType,
        public readonly ?string $serviceScope = null,
        public readonly ?string $vehicleType = null,
        public readonly ?string $market = null,
        public readonly ?int $zoneId = null,

        // Measurements
        public readonly float $miles = 0.0,
        public readonly float $loadedMiles = 0.0,
        public readonly float $deadheadMiles = 0.0,
        public readonly int $stops = 0,
        public readonly int $packages = 0,
        public readonly int $deliveredPackages = 0,
        public readonly int $minutes = 0,
        public readonly int $waitMinutes = 0,
        public readonly int $detentionMinutes = 0,
        public readonly int $layoverNights = 0,
        public readonly int $extraStops = 0,

        // Revenue basis for percentage models
        public readonly int $customerChargeCents = 0,
        public readonly int $linehaulCents = 0,
        public readonly int $deliveryChargeCents = 0,

        // Pass-through / reimbursements
        public readonly int $tollsCents = 0,
        public readonly int $reimbursementsCents = 0,
        public readonly int $tipsCents = 0,

        // Flags
        public readonly bool $isPeak = false,
        public readonly bool $isAfterHours = false,
        public readonly bool $isWeekend = false,
        public readonly bool $isOvernight = false,
        public readonly bool $isStat = false,
        public readonly bool $requiresChainOfCustody = false,
        public readonly bool $requiresTemperatureControl = false,
        public readonly bool $isHeavyItem = false,
        public readonly bool $driverAssist = false,
        public readonly bool $isCancelled = false,
        public readonly bool $isFailedDelivery = false,
        public readonly bool $isFailedHandoff = false,
        public readonly bool $isRedelivery = false,
        public readonly bool $isReturnTrip = false,
        public readonly bool $isReturnSpecimen = false,
        public readonly bool $routeCompleted = false,
        public readonly int $batchedOrders = 0,

        // Identity
        public readonly ?int $driverId = null,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        public readonly ?string $occurredAt = null,
    ) {
        foreach ([
            'miles' => $this->miles,
            'loadedMiles' => $this->loadedMiles,
            'deadheadMiles' => $this->deadheadMiles,
        ] as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException("Context value [{$name}] may not be negative.");
            }
        }

        foreach ([
            'stops' => $this->stops,
            'packages' => $this->packages,
            'deliveredPackages' => $this->deliveredPackages,
            'minutes' => $this->minutes,
            'waitMinutes' => $this->waitMinutes,
            'detentionMinutes' => $this->detentionMinutes,
            'layoverNights' => $this->layoverNights,
            'extraStops' => $this->extraStops,
            'customerChargeCents' => $this->customerChargeCents,
            'linehaulCents' => $this->linehaulCents,
            'deliveryChargeCents' => $this->deliveryChargeCents,
            'tollsCents' => $this->tollsCents,
            'reimbursementsCents' => $this->reimbursementsCents,
            'tipsCents' => $this->tipsCents,
            'batchedOrders' => $this->batchedOrders,
        ] as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException("Context value [{$name}] may not be negative.");
            }
        }
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['work_type'])) {
            throw new InvalidArgumentException('work_type is required.');
        }

        $get = static fn (string $key, $default) => $data[$key] ?? $default;

        return new self(
            workType: (string) $data['work_type'],
            serviceScope: $get('service_scope', null),
            vehicleType: $get('vehicle_type', null),
            market: $get('market', null),
            zoneId: $get('zone_id', null) !== null ? (int) $data['zone_id'] : null,
            miles: (float) $get('miles', 0),
            loadedMiles: (float) $get('loaded_miles', 0),
            deadheadMiles: (float) $get('deadhead_miles', 0),
            stops: (int) $get('stops', 0),
            packages: (int) $get('packages', 0),
            deliveredPackages: (int) $get('delivered_packages', 0),
            minutes: (int) $get('minutes', 0),
            waitMinutes: (int) $get('wait_minutes', 0),
            detentionMinutes: (int) $get('detention_minutes', 0),
            layoverNights: (int) $get('layover_nights', 0),
            extraStops: (int) $get('extra_stops', 0),
            customerChargeCents: (int) $get('customer_charge_cents', 0),
            linehaulCents: (int) $get('linehaul_cents', 0),
            deliveryChargeCents: (int) $get('delivery_charge_cents', 0),
            tollsCents: (int) $get('tolls_cents', 0),
            reimbursementsCents: (int) $get('reimbursements_cents', 0),
            tipsCents: (int) $get('tips_cents', 0),
            isPeak: (bool) $get('is_peak', false),
            isAfterHours: (bool) $get('is_after_hours', false),
            isWeekend: (bool) $get('is_weekend', false),
            isOvernight: (bool) $get('is_overnight', false),
            isStat: (bool) $get('is_stat', false),
            requiresChainOfCustody: (bool) $get('requires_chain_of_custody', false),
            requiresTemperatureControl: (bool) $get('requires_temperature_control', false),
            isHeavyItem: (bool) $get('is_heavy_item', false),
            driverAssist: (bool) $get('driver_assist', false),
            isCancelled: (bool) $get('is_cancelled', false),
            isFailedDelivery: (bool) $get('is_failed_delivery', false),
            isFailedHandoff: (bool) $get('is_failed_handoff', false),
            isRedelivery: (bool) $get('is_redelivery', false),
            isReturnTrip: (bool) $get('is_return_trip', false),
            isReturnSpecimen: (bool) $get('is_return_specimen', false),
            routeCompleted: (bool) $get('route_completed', false),
            batchedOrders: (int) $get('batched_orders', 0),
            driverId: $get('driver_id', null) !== null ? (int) $data['driver_id'] : null,
            subjectType: $get('subject_type', null),
            subjectId: $get('subject_id', null) !== null ? (int) $data['subject_id'] : null,
            occurredAt: $get('occurred_at', null),
        );
    }

    public function toArray(): array
    {
        return [
            'work_type' => $this->workType,
            'service_scope' => $this->serviceScope,
            'vehicle_type' => $this->vehicleType,
            'market' => $this->market,
            'zone_id' => $this->zoneId,
            'miles' => $this->miles,
            'loaded_miles' => $this->loadedMiles,
            'deadhead_miles' => $this->deadheadMiles,
            'stops' => $this->stops,
            'packages' => $this->packages,
            'delivered_packages' => $this->deliveredPackages,
            'minutes' => $this->minutes,
            'wait_minutes' => $this->waitMinutes,
            'detention_minutes' => $this->detentionMinutes,
            'layover_nights' => $this->layoverNights,
            'extra_stops' => $this->extraStops,
            'customer_charge_cents' => $this->customerChargeCents,
            'linehaul_cents' => $this->linehaulCents,
            'delivery_charge_cents' => $this->deliveryChargeCents,
            'tolls_cents' => $this->tollsCents,
            'reimbursements_cents' => $this->reimbursementsCents,
            'tips_cents' => $this->tipsCents,
            'is_peak' => $this->isPeak,
            'is_after_hours' => $this->isAfterHours,
            'is_weekend' => $this->isWeekend,
            'is_overnight' => $this->isOvernight,
            'is_stat' => $this->isStat,
            'requires_chain_of_custody' => $this->requiresChainOfCustody,
            'requires_temperature_control' => $this->requiresTemperatureControl,
            'is_heavy_item' => $this->isHeavyItem,
            'driver_assist' => $this->driverAssist,
            'is_cancelled' => $this->isCancelled,
            'is_failed_delivery' => $this->isFailedDelivery,
            'is_failed_handoff' => $this->isFailedHandoff,
            'is_redelivery' => $this->isRedelivery,
            'is_return_trip' => $this->isReturnTrip,
            'is_return_specimen' => $this->isReturnSpecimen,
            'route_completed' => $this->routeCompleted,
            'batched_orders' => $this->batchedOrders,
            'driver_id' => $this->driverId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
