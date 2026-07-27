<?php

namespace App\Support\Compensation;

use App\Services\UrbanGoodz\Compensation\ComponentCalculator;

/**
 * Describes every configurable pay component for the rule builder UI.
 *
 * The catalog is the single source of truth the builder renders from, so a
 * component added to the engine surfaces in the UI by being described here
 * rather than by hand-editing a Blade template.
 */
final class ComponentCatalog
{
    public const VEHICLES = [
        'cargo_van' => 'Cargo van',
        'sprinter_van' => 'Sprinter van',
        'pickup_truck' => 'Pickup truck',
        'box_truck' => 'Box truck',
        'hotshot' => 'Hotshot',
        'dry_van' => 'Dry van',
        'reefer' => 'Reefer',
        'flatbed' => 'Flatbed',
        'power_only' => 'Power only',
    ];

    public const WORK_TYPES = [
        'delivery' => 'Delivery',
        'route' => 'Route',
        'logistics' => 'Logistics',
        'medical' => 'Medical courier',
    ];

    public const SERVICE_SCOPES = [
        'delivery' => [
            'marketplace_delivery' => 'Marketplace delivery',
            'courier_parcel' => 'Courier / parcel',
            'order_anywhere' => 'Order Anywhere',
            'shopping_job' => 'Shopping job',
        ],
        'route' => [
            'dedicated_route' => 'Dedicated route',
            'scheduled_route' => 'Scheduled route',
            'recurring_route' => 'Recurring route',
            'business_multi_stop' => 'Business multi-stop',
            'package_route' => 'Package route',
        ],
        'logistics' => [
            'full_truckload' => 'Full truckload (FTL)',
            'partial_ltl' => 'Partial / LTL',
            'local_logistics' => 'Local logistics',
            'otr_long_haul' => 'OTR / long haul',
        ],
        'medical' => [
            'stat_medical' => 'STAT medical',
            'scheduled_medical_route' => 'Scheduled medical route',
            'medical_courier' => 'Medical courier',
        ],
    ];

    /**
     * @return array<string,array<string,array<string,mixed>>> group => component => spec
     */
    public static function groups(): array
    {
        return [
            'delivery' => [
                'flat' => self::spec('Flat pay', ['amount_cents' => 'Amount']),
                'base' => self::spec('Base pay', ['amount_cents' => 'Amount']),
                'per_mile' => self::spec('Per mile', ['rate_cents' => 'Rate per mile', 'free_miles' => 'Free miles', 'basis' => 'Mileage basis']),
                'per_stop' => self::spec('Per stop', ['rate_cents' => 'Rate per stop', 'free_stops' => 'Free stops']),
                'per_package' => self::spec('Per package', ['rate_cents' => 'Rate per package', 'basis' => 'Package basis']),
                'per_minute' => self::spec('Per minute', ['rate_cents' => 'Rate per minute']),
                'percentage' => self::spec('Percentage of revenue', ['percent' => 'Percent', 'basis' => 'Revenue basis']),
                'peak_surge' => self::spec('Surge', ['percent' => 'Percent uplift', 'amount_cents' => 'Flat uplift']),
                'wait_time' => self::spec('Wait time', ['rate_cents_per_minute' => 'Rate per minute', 'free_minutes' => 'Free minutes']),
                'cancellation' => self::spec('Cancellation', ['amount_cents' => 'Amount'], true),
                'return_trip' => self::spec('Return', ['amount_cents' => 'Flat amount', 'rate_cents_per_mile' => 'Rate per mile']),
                'failed_delivery' => self::spec('Failed delivery', ['amount_cents' => 'Amount'], true),
                'redelivery' => self::spec('Redelivery', ['amount_cents' => 'Amount']),
                'heavy_item' => self::spec('Heavy item', ['amount_cents' => 'Amount']),
                'batching' => self::spec('Batch bonus', ['per_additional_order_cents' => 'Per additional order']),
            ],
            'route' => [
                'fixed_route' => self::spec('Fixed route', ['amount_cents' => 'Amount']),
                'per_package' => self::spec('Per package', ['rate_cents' => 'Rate per package', 'basis' => 'Package basis']),
                'per_stop' => self::spec('Per stop', ['rate_cents' => 'Rate per stop', 'free_stops' => 'Free stops']),
                'per_mile' => self::spec('Mileage', ['rate_cents' => 'Rate per mile', 'basis' => 'Mileage basis']),
                'route_completion_bonus' => self::spec('Completion bonus', ['amount_cents' => 'Amount']),
                'exception_pay' => self::spec('Exception', ['amount_cents' => 'Amount']),
                'return_pay' => self::spec('Return', ['amount_cents' => 'Amount']),
            ],
            'logistics' => [
                'vehicle_multiplier' => self::spec('Vehicle / equipment multipliers', self::VEHICLES),
                'deadhead' => self::spec('Deadhead', ['rate_cents_per_mile' => 'Rate per mile', 'free_miles' => 'Free miles']),
                'detention' => self::spec('Detention', ['rate_cents_per_minute' => 'Rate per minute', 'free_minutes' => 'Free minutes', 'max_cents' => 'Cap']),
                'layover' => self::spec('Layover', ['rate_cents_per_night' => 'Rate per night']),
                'tolls' => self::spec('Tolls', ['reimburse' => 'Reimburse', 'max_cents' => 'Cap'], false, true),
                'fuel_surcharge' => self::spec('Fuel surcharge', ['rate_cents_per_mile' => 'Rate per mile', 'percent' => 'Percent', 'basis' => 'Basis']),
                'additional_stops' => self::spec('Additional stops', ['rate_cents' => 'Rate per extra stop']),
                'driver_assist' => self::spec('Driver assist', ['amount_cents' => 'Amount']),
                'overnight' => self::spec('Overnight', ['amount_cents' => 'Amount']),
                'weekend' => self::spec('Weekend', ['amount_cents' => 'Amount']),
            ],
            'medical' => [
                'stat' => self::spec('STAT', ['amount_cents' => 'Flat amount', 'percent' => 'Percent of charge']),
                'chain_of_custody' => self::spec('Chain of custody', ['amount_cents' => 'Amount']),
                'temperature_control' => self::spec('Temperature control', ['amount_cents' => 'Amount']),
                'wait_time' => self::spec('Wait time', ['rate_cents_per_minute' => 'Rate per minute', 'free_minutes' => 'Free minutes']),
                'return_specimen' => self::spec('Return specimen', ['amount_cents' => 'Amount']),
                'failed_handoff' => self::spec('Failed handoff', ['amount_cents' => 'Amount'], true),
                'after_hours' => self::spec('After-hours premium', ['amount_cents' => 'Amount']),
            ],
            'pass_through' => [
                'tolls' => self::spec('Tolls', ['reimburse' => 'Reimburse', 'max_cents' => 'Cap'], false, true),
                'reimbursements' => self::spec('Reimbursements', ['reimburse' => 'Reimburse', 'max_cents' => 'Cap'], false, true),
                'tips' => self::spec('Tips', ['reimburse' => 'Pass through'], false, true),
            ],
        ];
    }

    private static function spec(string $label, array $fields, bool $terminal = false, bool $passThrough = false): array
    {
        return [
            'label' => $label,
            'fields' => $fields,
            'terminal' => $terminal,
            'pass_through' => $passThrough,
        ];
    }

    /** Components valid for a given work type, plus the shared pass-through set. */
    public static function forWorkType(string $workType): array
    {
        $groups = self::groups();

        return array_merge(
            $groups[$workType] ?? [],
            $groups['pass_through']
        );
    }

    public static function supported(): array
    {
        return ComponentCalculator::SUPPORTED;
    }

    public static function splitParties(): array
    {
        return [
            'dispatcher' => 'Dispatcher',
            'creator' => 'Creator / referral',
            'vendor' => 'Vendor',
            'provider' => 'Service provider',
            'tax' => 'Taxes and adjustments',
        ];
    }
}
