<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationEngine;
use App\Services\UrbanGoodz\Compensation\CompensationWorkflowHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzCompensationTerminalStateTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCompensationRule $deliveryRule;
    private UrbanGoodzCompensationRule $medicalRule;
    private UrbanGoodzCompensationRule $routeRule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRule = UrbanGoodzCompensationRule::create([
            'rule_key' => 'terminal_delivery',
            'name' => 'Terminal Delivery Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'delivery',
            'priority' => 10,
            'components' => [
                'flat' => ['amount_cents' => 500],
                'per_mile' => ['rate_cents' => 150],
                'cancellation' => ['amount_cents' => 250],
                'failed_delivery' => ['amount_cents' => 350],
                'tips' => ['reimburse' => true],
            ],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 200,
            'maximum_payout_cents' => 5000,
        ]);

        $this->medicalRule = UrbanGoodzCompensationRule::create([
            'rule_key' => 'terminal_medical',
            'name' => 'Terminal Medical Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'priority' => 10,
            'components' => [
                'flat' => ['amount_cents' => 800],
                'stat' => ['amount_cents' => 200, 'percent' => 10],
                'failed_handoff' => ['amount_cents' => 400],
                'chain_of_custody' => ['amount_cents' => 100],
                'temperature_control' => ['amount_cents' => 150],
                'wait_time' => ['rate_cents_per_minute' => 25, 'free_minutes' => 10],
                'return_specimen' => ['amount_cents' => 75],
                'tips' => ['reimburse' => true],
            ],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 500,
            'maximum_payout_cents' => 8000,
        ]);

        $this->routeRule = UrbanGoodzCompensationRule::create([
            'rule_key' => 'terminal_route',
            'name' => 'Terminal Route Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'route',
            'service_scope' => 'dedicated_route',
            'priority' => 10,
            'components' => [
                'fixed_route' => ['amount_cents' => 1000],
                'per_package' => ['rate_cents' => 100],
                'route_completion_bonus' => ['amount_cents' => 200],
                'exception_pay' => ['amount_cents' => 300],
                'return_pay' => ['amount_cents' => 150],
                'tips' => ['reimburse' => true],
            ],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 500,
            'maximum_payout_cents' => 10000,
        ]);
    }

    // ---------------------------------------------------------------
    // Cancellation terminal state
    // ---------------------------------------------------------------

    public function test_cancellation_replaces_earning(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // Cancellation component pays 250, not flat(500)+per_mile(150*10=1500)=2000
        $this->assertEquals(250, $calc['earned_cents']);
        $this->assertEquals(250, $calc['driver_cents']);
    }

    public function test_cancellation_with_tips(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'tips_cents' => 500,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // cancellation(250) + tips(500) = 750
        $this->assertEquals(750, $calc['driver_cents']);
    }

    public function test_cancellation_unconfigured_pays_zero_earned_but_minimum_clamps(): void
    {
        $this->deliveryRule->update([
            'components' => [
                'flat' => ['amount_cents' => 500],
                'per_mile' => ['rate_cents' => 150],
            ],
            'minimum_payout_cents' => 0,
            'maximum_payout_cents' => 0,
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        $this->assertEquals(0, $calc['earned_cents']);
        $this->assertEquals(0, $calc['driver_cents']);
    }

    // ---------------------------------------------------------------
    // Failed delivery terminal state
    // ---------------------------------------------------------------

    public function test_failed_delivery_replaces_earning(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'is_failed_delivery' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // failed_delivery pays 350, not the full earning
        $this->assertEquals(350, $calc['earned_cents']);
    }

    public function test_failed_delivery_with_tips(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'tips_cents' => 200,
            'is_failed_delivery' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // failed_delivery(350) + tips(200) = 550
        $this->assertEquals(550, $calc['driver_cents']);
    }

    // ---------------------------------------------------------------
    // Failed handoff terminal state (medical)
    // ---------------------------------------------------------------

    public function test_failed_handoff_medical(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'is_failed_handoff' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // failed_handoff pays 400, but minimum payout is 500, so clamp to 500
        $this->assertEquals(500, $calc['earned_cents']);
    }

    public function test_failed_handoff_with_chain_of_custody(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'requires_chain_of_custody' => true,
            'is_failed_handoff' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // failed_handoff(400) replaces normal earning, minimum clamp raises to 500
        // chain_of_custody is NOT a terminal — firstTerminal() fires failed_handoff first
        $this->assertEquals(500, $calc['earned_cents']);
    }

    // ---------------------------------------------------------------
    // Route exception / return / redelivery
    // ---------------------------------------------------------------

    public function test_route_exception_pay(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'route',
            'service_scope' => 'dedicated_route',
            'packages' => 10,
            'customer_charge_cents' => 5000,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // fixed_route(1000) + per_package(100*10=1000) + exception_pay(300) = 2300
        // route_completion_bonus requires routeCompleted=true, return_pay requires isReturnTrip=true
        $this->assertEquals(2300, $calc['earned_cents']);
    }

    public function test_route_return_pay(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'route',
            'service_scope' => 'dedicated_route',
            'packages' => 5,
            'customer_charge_cents' => 3000,
            'is_return_trip' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // fixed_route(1000) + per_package(100*5=500) + exception_pay(300) + return_pay(150) = 1950
        $this->assertEquals(1950, $calc['earned_cents']);
    }

    public function test_order_redelivery(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'is_redelivery' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // No redelivery component in the delivery rule, so normal earning
        // flat(500) + per_mile(150*5=750) = 1250
        $this->assertEquals(1250, $calc['earned_cents']);
    }

    // ---------------------------------------------------------------
    // Medical terminal states with special components
    // ---------------------------------------------------------------

    public function test_medical_stat_delivery(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'is_stat' => true,
            'requires_chain_of_custody' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // flat(800) + stat(200 + 10% of 2000=200 = 400) + chain_of_custody(100) = 1300
        $this->assertEquals(1300, $calc['earned_cents']);
    }

    public function test_medical_with_wait_time(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'wait_minutes' => 30,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // flat(800) + wait_time(25*(30-10)=500) = 1300
        $this->assertEquals(1300, $calc['earned_cents']);
    }

    public function test_medical_return_specimen(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'is_return_specimen' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // flat(800) + return_specimen(75) = 875
        $this->assertEquals(875, $calc['earned_cents']);
    }

    public function test_medical_temperature_control(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'medical',
            'service_scope' => 'medical_courier',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'requires_temperature_control' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // flat(800) + temperature_control(150) = 950
        $this->assertEquals(950, $calc['earned_cents']);
    }

    // ---------------------------------------------------------------
    // Min/max clamping on terminal states
    // ---------------------------------------------------------------

    public function test_terminal_state_respects_minimum(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 0,
            'customer_charge_cents' => 100,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // cancellation(250) > minimum(200), so 250
        $this->assertEquals(250, $calc['earned_cents']);
    }

    public function test_terminal_state_respects_maximum(): void
    {
        $this->deliveryRule->update([
            'components' => [
                'cancellation' => ['amount_cents' => 10000],
                'tips' => ['reimburse' => true],
            ],
        ]);

        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 0,
            'customer_charge_cents' => 100,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // cancellation(10000) clamped to max(5000)
        $this->assertEquals(5000, $calc['earned_cents']);
    }

    // ---------------------------------------------------------------
    // Explanation output
    // ---------------------------------------------------------------

    public function test_explanation_includes_terminal_state(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
            'is_cancelled' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        $this->assertStringContainsString('Cancellation pay', $calc['explanation']);
    }

    public function test_explanation_includes_normal_components(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 10.0,
            'customer_charge_cents' => 3000,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        $this->assertStringContainsString('Flat pay', $calc['explanation']);
        $this->assertStringContainsString('Mileage', $calc['explanation']);
    }

    // ---------------------------------------------------------------
    // Multiple terminal flags — first wins
    // ---------------------------------------------------------------

    public function test_multiple_terminal_flags_first_wins(): void
    {
        $ctx = CompensationContext::fromArray([
            'work_type' => 'delivery',
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
            'is_cancelled' => true,
            'is_failed_delivery' => true,
        ]);

        $engine = new CompensationEngine();
        $calc = $engine->calculate($ctx);

        // cancellation(250) fires first per TERMINAL order
        $this->assertEquals(250, $calc['earned_cents']);
    }
}
