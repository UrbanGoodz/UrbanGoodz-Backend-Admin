<?php

namespace App\Services\UrbanGoodz\Compensation;

use InvalidArgumentException;

/**
 * Computes individual pay components.
 *
 * Each component is independent and reads only its own configuration slice plus
 * the context. Unknown component keys are rejected rather than silently ignored,
 * so a typo in a rule cannot quietly reduce a driver's pay.
 */
final class ComponentCalculator
{
    /** Components that, when triggered, replace the normal earning calculation. */
    public const TERMINAL = ['cancellation', 'failed_delivery', 'failed_handoff'];

    /** Pass-through money that must never be clamped or split as driver earnings. */
    public const PASS_THROUGH = ['tolls', 'reimbursements', 'tips'];

    public const SUPPORTED = [
        // Delivery
        'flat', 'base', 'per_mile', 'per_stop', 'per_package', 'per_minute',
        'percentage', 'wait_time', 'peak_surge', 'heavy_item', 'batching',
        'return_trip', 'redelivery',
        // Terminal
        'cancellation', 'failed_delivery', 'failed_handoff',
        // Routes
        'fixed_route', 'route_completion_bonus', 'exception_pay', 'return_pay',
        // Logistics
        'deadhead', 'detention', 'layover', 'fuel_surcharge', 'additional_stops',
        'driver_assist', 'overnight', 'weekend',
        // Medical
        'stat', 'chain_of_custody', 'temperature_control', 'after_hours',
        'return_specimen',
        // Pass-through
        'tolls', 'reimbursements', 'tips',
        // Modifiers (handled by the engine, listed so validation accepts them)
        'vehicle_multiplier',
    ];

    public function __construct(private readonly string $roundingMode = Money::HALF_UP)
    {
    }

    public static function assertSupported(array $components): void
    {
        foreach (array_keys($components) as $key) {
            if (!in_array($key, self::SUPPORTED, true)) {
                throw new InvalidArgumentException("Unsupported compensation component [{$key}].");
            }
        }
    }

    /**
     * @return array{0:int,1:string,2:array}|null  [cents, label, inputs]
     */
    public function compute(string $component, array $config, CompensationContext $ctx): ?array
    {
        return match ($component) {
            'flat' => $this->fixed($config, 'Flat pay'),
            'base' => $this->fixed($config, 'Base pay'),
            'fixed_route' => $this->fixed($config, 'Fixed route pay'),
            'heavy_item' => $ctx->isHeavyItem ? $this->fixed($config, 'Heavy item') : null,
            'driver_assist' => $ctx->driverAssist ? $this->fixed($config, 'Driver assist') : null,
            'overnight' => $ctx->isOvernight ? $this->fixed($config, 'Overnight adjustment') : null,
            'weekend' => $ctx->isWeekend ? $this->fixed($config, 'Weekend adjustment') : null,
            'after_hours' => $ctx->isAfterHours ? $this->fixed($config, 'After-hours premium') : null,
            'chain_of_custody' => $ctx->requiresChainOfCustody ? $this->fixed($config, 'Chain-of-custody premium') : null,
            'temperature_control' => $ctx->requiresTemperatureControl ? $this->fixed($config, 'Temperature-control premium') : null,
            'return_specimen' => $ctx->isReturnSpecimen ? $this->fixed($config, 'Return specimen') : null,
            'return_trip' => $ctx->isReturnTrip ? $this->returnTrip($config, $ctx) : null,
            'return_pay' => $ctx->isReturnTrip ? $this->fixed($config, 'Return compensation') : null,
            'redelivery' => $ctx->isRedelivery ? $this->fixed($config, 'Redelivery') : null,
            'exception_pay' => $this->fixed($config, 'Exception compensation'),

            'per_mile' => $this->perMile($config, $ctx),
            'per_stop' => $this->perStop($config, $ctx),
            'per_package' => $this->perPackage($config, $ctx),
            'per_minute' => $this->perMinute($config, $ctx),
            'percentage' => $this->percentage($config, $ctx),
            'wait_time' => $this->waitTime($config, $ctx),
            'detention' => $this->detention($config, $ctx),
            'layover' => $this->layover($config, $ctx),
            'deadhead' => $this->deadhead($config, $ctx),
            'fuel_surcharge' => $this->fuelSurcharge($config, $ctx),
            'additional_stops' => $this->additionalStops($config, $ctx),
            'batching' => $this->batching($config, $ctx),
            'stat' => $ctx->isStat ? $this->stat($config, $ctx) : null,
            'route_completion_bonus' => $ctx->routeCompleted ? $this->fixed($config, 'Route completion bonus') : null,

            'cancellation' => $ctx->isCancelled ? $this->fixed($config, 'Cancellation pay') : null,
            'failed_delivery' => $ctx->isFailedDelivery ? $this->fixed($config, 'Failed delivery pay') : null,
            'failed_handoff' => $ctx->isFailedHandoff ? $this->fixed($config, 'Failed handoff pay') : null,

            'tolls' => $this->passThrough($config, $ctx->tollsCents, 'Tolls reimbursement'),
            'reimbursements' => $this->passThrough($config, $ctx->reimbursementsCents, 'Reimbursements'),
            'tips' => $this->passThrough($config, $ctx->tipsCents, 'Tips'),

            'peak_surge', 'vehicle_multiplier' => null, // applied by the engine
            default => throw new InvalidArgumentException("Unsupported compensation component [{$component}]."),
        };
    }

    private function amount(array $config, string $key = 'amount_cents'): int
    {
        $value = (int) ($config[$key] ?? 0);

        if ($value < 0) {
            throw new InvalidArgumentException("Component amount [{$key}] may not be negative.");
        }

        return $value;
    }

    private function rate(array $config, string $key): int
    {
        $value = (int) ($config[$key] ?? 0);

        if ($value < 0) {
            throw new InvalidArgumentException("Component rate [{$key}] may not be negative.");
        }

        return $value;
    }

    private function fixed(array $config, string $label): ?array
    {
        $cents = $this->amount($config);

        return $cents === 0 ? null : [$cents, $label, ['amount_cents' => $cents]];
    }

    private function passThrough(array $config, int $contextCents, string $label): ?array
    {
        if (($config['reimburse'] ?? true) === false || $contextCents === 0) {
            return null;
        }

        $cap = isset($config['max_cents']) ? (int) $config['max_cents'] : null;
        $cents = $cap !== null ? min($contextCents, $cap) : $contextCents;

        return [$cents, $label, ['claimed_cents' => $contextCents, 'cap_cents' => $cap]];
    }

    private function milesFor(array $config, CompensationContext $ctx): float
    {
        return match ($config['basis'] ?? 'miles') {
            'loaded_miles' => $ctx->loadedMiles,
            'total_miles' => $ctx->miles + $ctx->deadheadMiles,
            default => $ctx->miles,
        };
    }

    private function perMile(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents');
        $miles = $this->milesFor($config, $ctx);
        $free = (float) ($config['free_miles'] ?? 0);
        $billable = max(0.0, $miles - $free);

        if ($rate === 0 || $billable <= 0) {
            return null;
        }

        return [
            Money::multiply($rate, $billable, $this->roundingMode),
            'Mileage',
            ['rate_cents' => $rate, 'miles' => $miles, 'free_miles' => $free, 'billable_miles' => $billable],
        ];
    }

    private function perStop(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents');
        $free = (int) ($config['free_stops'] ?? 0);
        $billable = max(0, $ctx->stops - $free);

        if ($rate === 0 || $billable === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $billable, $this->roundingMode),
            'Per stop',
            ['rate_cents' => $rate, 'stops' => $ctx->stops, 'billable_stops' => $billable],
        ];
    }

    private function perPackage(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents');
        $count = ($config['basis'] ?? 'packages') === 'delivered_packages'
            ? $ctx->deliveredPackages
            : $ctx->packages;

        if ($rate === 0 || $count === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $count, $this->roundingMode),
            'Per package',
            ['rate_cents' => $rate, 'packages' => $count, 'basis' => $config['basis'] ?? 'packages'],
        ];
    }

    private function perMinute(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents');

        if ($rate === 0 || $ctx->minutes === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $ctx->minutes, $this->roundingMode),
            'Time',
            ['rate_cents' => $rate, 'minutes' => $ctx->minutes],
        ];
    }

    private function percentage(array $config, CompensationContext $ctx): ?array
    {
        $percent = (float) ($config['percent'] ?? 0);

        if ($percent < 0) {
            throw new InvalidArgumentException('Percentage may not be negative.');
        }

        $basis = $config['basis'] ?? 'customer_charge';
        $baseCents = match ($basis) {
            'linehaul' => $ctx->linehaulCents,
            'delivery_charge' => $ctx->deliveryChargeCents,
            default => $ctx->customerChargeCents,
        };

        if ($percent === 0.0 || $baseCents === 0) {
            return null;
        }

        return [
            Money::percent($baseCents, $percent, $this->roundingMode),
            'Percentage of ' . $basis,
            ['percent' => $percent, 'basis' => $basis, 'base_cents' => $baseCents],
        ];
    }

    private function waitTime(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents_per_minute');
        $free = (int) ($config['free_minutes'] ?? 0);
        $billable = max(0, $ctx->waitMinutes - $free);

        if ($rate === 0 || $billable === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $billable, $this->roundingMode),
            'Wait time',
            ['rate_cents_per_minute' => $rate, 'wait_minutes' => $ctx->waitMinutes, 'free_minutes' => $free, 'billable_minutes' => $billable],
        ];
    }

    private function detention(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents_per_minute');
        $free = (int) ($config['free_minutes'] ?? 0);
        $billable = max(0, $ctx->detentionMinutes - $free);

        if ($rate === 0 || $billable === 0) {
            return null;
        }

        $cents = Money::multiply($rate, $billable, $this->roundingMode);
        $cap = isset($config['max_cents']) ? (int) $config['max_cents'] : null;

        if ($cap !== null) {
            $cents = min($cents, $cap);
        }

        return [
            $cents,
            'Detention',
            ['rate_cents_per_minute' => $rate, 'detention_minutes' => $ctx->detentionMinutes, 'free_minutes' => $free, 'billable_minutes' => $billable, 'cap_cents' => $cap],
        ];
    }

    private function layover(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents_per_night');

        if ($rate === 0 || $ctx->layoverNights === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $ctx->layoverNights, $this->roundingMode),
            'Layover',
            ['rate_cents_per_night' => $rate, 'nights' => $ctx->layoverNights],
        ];
    }

    private function deadhead(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents_per_mile');
        $free = (float) ($config['free_miles'] ?? 0);
        $billable = max(0.0, $ctx->deadheadMiles - $free);

        if ($rate === 0 || $billable <= 0) {
            return null;
        }

        return [
            Money::multiply($rate, $billable, $this->roundingMode),
            'Deadhead',
            ['rate_cents_per_mile' => $rate, 'deadhead_miles' => $ctx->deadheadMiles, 'free_miles' => $free, 'billable_miles' => $billable],
        ];
    }

    private function fuelSurcharge(array $config, CompensationContext $ctx): ?array
    {
        if (isset($config['percent'])) {
            $percent = (float) $config['percent'];
            $basis = $config['basis'] ?? 'linehaul';
            $baseCents = $basis === 'customer_charge' ? $ctx->customerChargeCents : $ctx->linehaulCents;

            if ($percent <= 0 || $baseCents === 0) {
                return null;
            }

            return [
                Money::percent($baseCents, $percent, $this->roundingMode),
                'Fuel surcharge',
                ['percent' => $percent, 'basis' => $basis, 'base_cents' => $baseCents],
            ];
        }

        $rate = $this->rate($config, 'rate_cents_per_mile');
        $miles = $this->milesFor($config, $ctx);

        if ($rate === 0 || $miles <= 0) {
            return null;
        }

        return [
            Money::multiply($rate, $miles, $this->roundingMode),
            'Fuel surcharge',
            ['rate_cents_per_mile' => $rate, 'miles' => $miles],
        ];
    }

    private function additionalStops(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'rate_cents');

        if ($rate === 0 || $ctx->extraStops === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $ctx->extraStops, $this->roundingMode),
            'Additional stops',
            ['rate_cents' => $rate, 'extra_stops' => $ctx->extraStops],
        ];
    }

    private function batching(array $config, CompensationContext $ctx): ?array
    {
        $rate = $this->rate($config, 'per_additional_order_cents');
        $additional = max(0, $ctx->batchedOrders - 1);

        if ($rate === 0 || $additional === 0) {
            return null;
        }

        return [
            Money::multiply($rate, $additional, $this->roundingMode),
            'Multi-order batching',
            ['rate_cents' => $rate, 'batched_orders' => $ctx->batchedOrders, 'additional_orders' => $additional],
        ];
    }

    private function returnTrip(array $config, CompensationContext $ctx): ?array
    {
        $flat = $this->amount($config);
        $perMile = $this->rate($config, 'rate_cents_per_mile');
        $cents = $flat;
        $inputs = ['amount_cents' => $flat];

        if ($perMile > 0 && $ctx->miles > 0) {
            $cents += Money::multiply($perMile, $ctx->miles, $this->roundingMode);
            $inputs['rate_cents_per_mile'] = $perMile;
            $inputs['miles'] = $ctx->miles;
        }

        return $cents === 0 ? null : [$cents, 'Return trip', $inputs];
    }

    private function stat(array $config, CompensationContext $ctx): ?array
    {
        $flat = $this->amount($config);
        $percent = (float) ($config['percent'] ?? 0);
        $cents = $flat;
        $inputs = ['amount_cents' => $flat];

        if ($percent > 0 && $ctx->customerChargeCents > 0) {
            $cents += Money::percent($ctx->customerChargeCents, $percent, $this->roundingMode);
            $inputs['percent'] = $percent;
            $inputs['base_cents'] = $ctx->customerChargeCents;
        }

        return $cents === 0 ? null : [$cents, 'STAT premium', $inputs];
    }
}
