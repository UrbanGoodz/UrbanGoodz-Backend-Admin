<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzCompensationRule;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationEngine;
use App\Services\UrbanGoodz\Compensation\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Compensation engine arithmetic.
 *
 * These run without a database: calculateWithRule() accepts an unsaved model, so
 * the maths is verified in isolation from rule resolution.
 */
class UrbanGoodzCompensationEngineTest extends TestCase
{
    private function rule(array $overrides = []): UrbanGoodzCompensationRule
    {
        return new UrbanGoodzCompensationRule(array_merge([
            'rule_key' => 'test.rule',
            'name' => 'Test rule',
            'version' => 1,
            'state' => UrbanGoodzCompensationRule::STATE_PUBLISHED,
            'is_active' => true,
            'work_type' => 'delivery',
            'priority' => 0,
            'rounding_mode' => Money::HALF_UP,
            'components' => [],
            'splits' => [],
        ], $overrides));
    }

    private function engine(): CompensationEngine
    {
        return new CompensationEngine();
    }

    // ---------------------------------------------------------------- rounding

    public function test_half_up_rounding_is_applied_to_fractional_cents(): void
    {
        // 2.5 miles x 65c = 162.5c -> 163c under half-up.
        $rule = $this->rule(['components' => ['per_mile' => ['rate_cents' => 65]]]);
        $ctx = CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => 2.5]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(163, $result['earned_cents']);
    }

    public function test_half_even_rounding_differs_from_half_up_on_exact_halves(): void
    {
        $ctx = CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => 2.5]);

        $halfUp = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 65]], 'rounding_mode' => Money::HALF_UP]),
            $ctx
        );

        $halfEven = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 65]], 'rounding_mode' => Money::HALF_EVEN]),
            $ctx
        );

        $this->assertSame(163, $halfUp['earned_cents']);
        $this->assertSame(162, $halfEven['earned_cents']);
    }

    public function test_floor_and_ceil_rounding_modes(): void
    {
        $ctx = CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => 2.5]);

        $floor = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 65]], 'rounding_mode' => Money::FLOOR]),
            $ctx
        );
        $ceil = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 65]], 'rounding_mode' => Money::CEIL]),
            $ctx
        );

        $this->assertSame(162, $floor['earned_cents']);
        $this->assertSame(163, $ceil['earned_cents']);
    }

    // ------------------------------------------------- fixed plus variable pay

    public function test_base_plus_mileage_plus_stops_accumulate(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 500],
            'per_mile' => ['rate_cents' => 60],
            'per_stop' => ['rate_cents' => 250],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 10, 'stops' => 4,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // 500 + (10 x 60 = 600) + (4 x 250 = 1000) = 2100
        $this->assertSame(2100, $result['earned_cents']);
        $this->assertCount(3, $result['breakdown']['lines']);
    }

    public function test_free_allowances_reduce_billable_quantities(): void
    {
        $rule = $this->rule(['components' => [
            'per_mile' => ['rate_cents' => 100, 'free_miles' => 3],
            'per_stop' => ['rate_cents' => 200, 'free_stops' => 1],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 5, 'stops' => 3,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // (5-3) x 100 = 200, (3-1) x 200 = 400
        $this->assertSame(600, $result['earned_cents']);
    }

    public function test_per_package_can_bill_delivered_packages_only(): void
    {
        $rule = $this->rule(['components' => [
            'per_package' => ['rate_cents' => 75, 'basis' => 'delivered_packages'],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'packages' => 20, 'delivered_packages' => 18,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(1350, $result['earned_cents']);
    }

    // ------------------------------------------------------ minimum / maximum

    public function test_minimum_payout_raises_a_low_calculation(): void
    {
        $rule = $this->rule([
            'components' => ['per_mile' => ['rate_cents' => 50]],
            'minimum_payout_cents' => 1200,
        ]);

        $ctx = CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => 2]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(1200, $result['earned_cents']);
    }

    public function test_maximum_payout_caps_a_high_calculation(): void
    {
        $rule = $this->rule([
            'components' => ['per_mile' => ['rate_cents' => 200]],
            'maximum_payout_cents' => 5000,
        ]);

        $ctx = CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => 100]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(5000, $result['earned_cents']);
    }

    public function test_pass_through_money_is_not_absorbed_by_the_minimum(): void
    {
        // The driver must receive the minimum PLUS their toll reimbursement, not
        // have the reimbursement counted toward satisfying the minimum.
        $rule = $this->rule([
            'components' => [
                'per_mile' => ['rate_cents' => 50],
                'tolls' => ['reimburse' => true],
            ],
            'minimum_payout_cents' => 1200,
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 2, 'tolls_cents' => 450,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(1200, $result['earned_cents']);
        $this->assertSame(450, $result['pass_through_cents']);
        $this->assertSame(1650, $result['driver_cents']);
    }

    public function test_maximum_payout_does_not_confiscate_tips(): void
    {
        $rule = $this->rule([
            'components' => [
                'per_mile' => ['rate_cents' => 200],
                'tips' => ['reimburse' => true],
            ],
            'maximum_payout_cents' => 5000,
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 100, 'tips_cents' => 800,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(5000, $result['earned_cents']);
        $this->assertSame(5800, $result['driver_cents']);
    }

    // ------------------------------------------------------------- cancellation

    public function test_cancellation_replaces_earning_components(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 500],
            'per_mile' => ['rate_cents' => 100],
            'cancellation' => ['amount_cents' => 350],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 12, 'is_cancelled' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(350, $result['earned_cents']);
        $this->assertFalse($result['breakdown']['lines'][0]['code'] === 'per_mile');
    }

    public function test_terminal_state_without_configuration_pays_nothing_and_says_so(): void
    {
        $rule = $this->rule(['components' => ['per_mile' => ['rate_cents' => 100]]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 12, 'is_cancelled' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        $this->assertSame(0, $result['earned_cents']);
        $codes = array_column($result['breakdown']['adjustments'], 'code');
        $this->assertContains('terminal_unconfigured', $codes);
    }

    public function test_failed_delivery_and_redelivery_are_distinct(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 500],
            'failed_delivery' => ['amount_cents' => 400],
            'redelivery' => ['amount_cents' => 600],
        ]]);

        $failed = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'is_failed_delivery' => true,
        ]));

        $redelivery = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'is_redelivery' => true,
        ]));

        $this->assertSame(400, $failed['earned_cents']);
        // Redelivery is not terminal: base still applies.
        $this->assertSame(1100, $redelivery['earned_cents']);
    }

    // ------------------------------------------------------------------ return

    public function test_return_trip_pays_flat_plus_mileage(): void
    {
        $rule = $this->rule(['components' => [
            'return_trip' => ['amount_cents' => 300, 'rate_cents_per_mile' => 55],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 8, 'is_return_trip' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // 300 + (8 x 55 = 440) = 740
        $this->assertSame(740, $result['earned_cents']);
    }

    public function test_return_components_do_not_apply_without_the_flag(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 500],
            'return_trip' => ['amount_cents' => 300],
            'return_pay' => ['amount_cents' => 200],
        ]]);

        $ctx = CompensationContext::fromArray(['work_type' => 'delivery']);

        $this->assertSame(500, $this->engine()->calculateWithRule($rule, $ctx)['earned_cents']);
    }

    // --------------------------------------------------------------- detention

    public function test_detention_respects_free_minutes_and_cap(): void
    {
        $rule = $this->rule(['components' => [
            'detention' => ['rate_cents_per_minute' => 50, 'free_minutes' => 120, 'max_cents' => 9000],
        ]]);

        // 300 minutes, 120 free -> 180 billable x 50 = 9000, exactly at the cap.
        $atCap = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'logistics', 'detention_minutes' => 300,
        ]));

        // 500 minutes -> 380 x 50 = 19000, capped to 9000.
        $overCap = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'logistics', 'detention_minutes' => 500,
        ]));

        // Inside the free window: nothing.
        $free = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'logistics', 'detention_minutes' => 90,
        ]));

        $this->assertSame(9000, $atCap['earned_cents']);
        $this->assertSame(9000, $overCap['earned_cents']);
        $this->assertSame(0, $free['earned_cents']);
    }

    // ---------------------------------------------------------------- deadhead

    public function test_deadhead_bills_only_beyond_the_free_allowance(): void
    {
        $rule = $this->rule(['components' => [
            'deadhead' => ['rate_cents_per_mile' => 45, 'free_miles' => 20],
        ]]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'logistics', 'deadhead_miles' => 75,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // (75-20) x 45 = 2475
        $this->assertSame(2475, $result['earned_cents']);
    }

    public function test_total_miles_basis_includes_deadhead(): void
    {
        $loaded = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 100, 'basis' => 'loaded_miles']]]),
            CompensationContext::fromArray(['work_type' => 'logistics', 'miles' => 90, 'loaded_miles' => 70, 'deadhead_miles' => 20])
        );

        $total = $this->engine()->calculateWithRule(
            $this->rule(['components' => ['per_mile' => ['rate_cents' => 100, 'basis' => 'total_miles']]]),
            CompensationContext::fromArray(['work_type' => 'logistics', 'miles' => 90, 'loaded_miles' => 70, 'deadhead_miles' => 20])
        );

        $this->assertSame(7000, $loaded['earned_cents']);
        $this->assertSame(11000, $total['earned_cents']);
    }

    // ----------------------------------------------------------- logistics mix

    public function test_logistics_load_with_vehicle_multiplier_layover_and_fuel(): void
    {
        $rule = $this->rule([
            'work_type' => 'logistics',
            'components' => [
                'per_mile' => ['rate_cents' => 200, 'basis' => 'loaded_miles'],
                'layover' => ['rate_cents_per_night' => 15000],
                'fuel_surcharge' => ['rate_cents_per_mile' => 30, 'basis' => 'loaded_miles'],
                'driver_assist' => ['amount_cents' => 5000],
                'vehicle_multiplier' => ['cargo_van' => 1.0, 'box_truck' => 1.25],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'logistics',
            'vehicle_type' => 'box_truck',
            'loaded_miles' => 100,
            'layover_nights' => 1,
            'driver_assist' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // 20000 + 15000 + 3000 + 5000 = 43000, x1.25 = 53750
        $this->assertSame(53750, $result['earned_cents']);

        $codes = array_column($result['breakdown']['adjustments'], 'code');
        $this->assertContains('vehicle_multiplier', $codes);
    }

    public function test_unknown_vehicle_type_defaults_to_no_multiplier(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 1000],
            'vehicle_multiplier' => ['box_truck' => 1.5],
        ]]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'vehicle_type' => 'reefer',
        ]));

        $this->assertSame(1000, $result['earned_cents']);
    }

    // -------------------------------------------------------------- peak/surge

    public function test_peak_surge_applies_only_when_flagged(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 1000],
            'peak_surge' => ['percent' => 20, 'amount_cents' => 100],
        ]]);

        $offPeak = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery',
        ]));

        $peak = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'is_peak' => true,
        ]));

        $this->assertSame(1000, $offPeak['earned_cents']);
        // 1000 + 20% (200) + 100 = 1300
        $this->assertSame(1300, $peak['earned_cents']);
    }

    // ------------------------------------------------------------ route payout

    public function test_dedicated_route_fixed_plus_per_package_with_completion_bonus(): void
    {
        $rule = $this->rule([
            'work_type' => 'route',
            'service_scope' => 'dedicated_route',
            'components' => [
                'fixed_route' => ['amount_cents' => 18000],
                'per_package' => ['rate_cents' => 40, 'basis' => 'delivered_packages'],
                'route_completion_bonus' => ['amount_cents' => 2500],
                'exception_pay' => ['amount_cents' => 0],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'route',
            'service_scope' => 'dedicated_route',
            'packages' => 120,
            'delivered_packages' => 118,
            'route_completed' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // 18000 + (118 x 40 = 4720) + 2500 = 25220
        $this->assertSame(25220, $result['earned_cents']);
    }

    public function test_route_completion_bonus_withheld_when_route_incomplete(): void
    {
        $rule = $this->rule([
            'work_type' => 'route',
            'components' => [
                'fixed_route' => ['amount_cents' => 18000],
                'route_completion_bonus' => ['amount_cents' => 2500],
            ],
        ]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'route', 'route_completed' => false,
        ]));

        $this->assertSame(18000, $result['earned_cents']);
    }

    // ----------------------------------------------------------- medical route

    public function test_medical_stat_route_stacks_premiums(): void
    {
        $rule = $this->rule([
            'work_type' => 'medical',
            'components' => [
                'base' => ['amount_cents' => 2000],
                'per_mile' => ['rate_cents' => 80],
                'stat' => ['amount_cents' => 1500, 'percent' => 10],
                'chain_of_custody' => ['amount_cents' => 1000],
                'temperature_control' => ['amount_cents' => 1200],
                'after_hours' => ['amount_cents' => 900],
                'wait_time' => ['rate_cents_per_minute' => 40, 'free_minutes' => 10],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'miles' => 15,
            'wait_minutes' => 25,
            'customer_charge_cents' => 10000,
            'is_stat' => true,
            'requires_chain_of_custody' => true,
            'requires_temperature_control' => true,
            'is_after_hours' => true,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);

        // base 2000 + miles 1200 + stat (1500 + 10% of 10000 = 1000) 2500
        // + custody 1000 + temp 1200 + after-hours 900 + wait (15 x 40) 600 = 9400
        $this->assertSame(9400, $result['earned_cents']);
    }

    public function test_medical_premiums_do_not_apply_without_their_flags(): void
    {
        $rule = $this->rule([
            'work_type' => 'medical',
            'components' => [
                'base' => ['amount_cents' => 2000],
                'stat' => ['amount_cents' => 1500],
                'chain_of_custody' => ['amount_cents' => 1000],
                'temperature_control' => ['amount_cents' => 1200],
                'return_specimen' => ['amount_cents' => 800],
            ],
        ]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'medical',
        ]));

        $this->assertSame(2000, $result['earned_cents']);
    }

    public function test_failed_handoff_is_terminal_for_medical(): void
    {
        $rule = $this->rule([
            'work_type' => 'medical',
            'components' => [
                'base' => ['amount_cents' => 2000],
                'per_mile' => ['rate_cents' => 80],
                'failed_handoff' => ['amount_cents' => 750],
            ],
        ]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'medical', 'miles' => 20, 'is_failed_handoff' => true,
        ]));

        $this->assertSame(750, $result['earned_cents']);
    }

    // ------------------------------------------------------- percentage splits

    public function test_percentage_splits_reconcile_to_the_basis_exactly(): void
    {
        $rule = $this->rule([
            'components' => ['percentage' => ['percent' => 70, 'basis' => 'customer_charge']],
            'splits' => [
                'basis' => 'customer_charge',
                'dispatcher' => ['percent' => 10],
                'creator' => ['percent' => 3],
                'tax' => ['percent' => 2],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'customer_charge_cents' => 10000,
        ]);

        $result = $this->engine()->calculateWithRule($rule, $ctx);
        $splits = $result['splits'];

        $this->assertSame(7000, $splits['driver_cents']);
        $this->assertSame(1000, $splits['dispatcher_cents']);
        $this->assertSame(300, $splits['creator_cents']);
        $this->assertSame(200, $splits['tax_cents']);
        $this->assertSame(1500, $splits['platform_cents']);
        $this->assertTrue($splits['reconciles']);
        $this->assertFalse($splits['is_deficit']);
        $this->assertSame(10000, $splits['reconciled_cents']);
    }

    public function test_overspending_rule_is_reported_as_a_deficit_not_silently_clamped(): void
    {
        $rule = $this->rule([
            'components' => ['flat' => ['amount_cents' => 9000]],
            'splits' => [
                'basis' => 'customer_charge',
                'dispatcher' => ['percent' => 20],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery', 'customer_charge_cents' => 10000,
        ]);

        $splits = $this->engine()->calculateWithRule($rule, $ctx)['splits'];

        // 9000 driver + 2000 dispatcher = 11000 against a 10000 basis.
        $this->assertSame(-1000, $splits['platform_cents']);
        $this->assertTrue($splits['is_deficit']);
        $this->assertTrue($splits['reconciles']);
    }

    public function test_fixed_split_amounts_are_supported(): void
    {
        $rule = $this->rule([
            'components' => ['flat' => ['amount_cents' => 5000]],
            'splits' => ['basis' => 'customer_charge', 'dispatcher' => ['fixed_cents' => 750]],
        ]);

        $splits = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'customer_charge_cents' => 10000,
        ]))['splits'];

        $this->assertSame(750, $splits['dispatcher_cents']);
        $this->assertSame(4250, $splits['platform_cents']);
    }

    // ---------------------------------------------- ledger compatibility (cents)

    public function test_allocation_never_loses_or_invents_cents(): void
    {
        // 100 cents across three equal parties cannot divide evenly.
        $allocated = Money::allocate(100, ['a' => 1, 'b' => 1, 'c' => 1]);

        $this->assertSame(100, array_sum($allocated));
        $this->assertSame([34, 33, 33], array_values($allocated));
    }

    public function test_allocation_handles_zero_and_uneven_weights(): void
    {
        $this->assertSame(0, array_sum(Money::allocate(0, ['a' => 1, 'b' => 2])));
        $this->assertSame(0, array_sum(Money::allocate(500, ['a' => 0, 'b' => 0])));
        $this->assertSame(999, array_sum(Money::allocate(999, ['a' => 1, 'b' => 7, 'c' => 3])));
    }

    public function test_decimal_and_cent_conversions_round_trip(): void
    {
        $this->assertSame(1234, Money::fromDecimal('12.34'));
        $this->assertSame('12.34', Money::toDecimal(1234));
        $this->assertSame('0.05', Money::toDecimal(5));
        $this->assertSame(1000, Money::fromDecimal(10));
    }

    // ------------------------------------------------------------- invalid input

    public function test_negative_context_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CompensationContext::fromArray(['work_type' => 'delivery', 'miles' => -5]);
    }

    public function test_negative_customer_charge_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CompensationContext::fromArray(['work_type' => 'delivery', 'customer_charge_cents' => -100]);
    }

    public function test_negative_component_rate_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $rule = $this->rule(['components' => ['per_mile' => ['rate_cents' => -50]]]);

        $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 5,
        ]));
    }

    public function test_negative_split_percentage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $rule = $this->rule([
            'components' => ['flat' => ['amount_cents' => 100]],
            'splits' => ['dispatcher' => ['percent' => -5]],
        ]);

        $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'customer_charge_cents' => 1000,
        ]));
    }

    public function test_unknown_component_is_rejected_rather_than_ignored(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $rule = $this->rule(['components' => ['per_mile_typo' => ['rate_cents' => 50]]]);

        $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 5,
        ]));
    }

    public function test_unknown_rounding_mode_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::round(1.5, 'banker_special');
    }

    // ------------------------------------------------------------- explanation

    public function test_every_calculation_produces_a_readable_explanation(): void
    {
        $rule = $this->rule(['components' => [
            'base' => ['amount_cents' => 500],
            'per_mile' => ['rate_cents' => 60],
        ]]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 10,
        ]));

        $this->assertStringContainsString('Base pay', $result['explanation']);
        $this->assertStringContainsString('Mileage', $result['explanation']);
        $this->assertStringContainsString('DRIVER TOTAL', $result['explanation']);
        $this->assertStringContainsString('test.rule', $result['explanation']);
    }

    public function test_breakdown_records_the_inputs_behind_each_line(): void
    {
        $rule = $this->rule(['components' => ['per_mile' => ['rate_cents' => 60, 'free_miles' => 2]]]);

        $result = $this->engine()->calculateWithRule($rule, CompensationContext::fromArray([
            'work_type' => 'delivery', 'miles' => 10,
        ]));

        $line = $result['breakdown']['lines'][0];

        $this->assertSame('per_mile', $line['code']);
        $this->assertSame(60, $line['inputs']['rate_cents']);
        $this->assertSame(8.0, $line['inputs']['billable_miles']);
        $this->assertSame('4.80', $line['amount']);
    }
}
