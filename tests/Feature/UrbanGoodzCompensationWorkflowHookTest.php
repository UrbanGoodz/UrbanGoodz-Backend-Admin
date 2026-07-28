<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use App\Services\UrbanGoodz\Compensation\CompensationContext;
use App\Services\UrbanGoodz\Compensation\CompensationEngine;
use App\Services\UrbanGoodz\Compensation\CompensationWorkflowHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzCompensationWorkflowHookTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCompensationRule $rule;
    private CompensationWorkflowHook $hook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hook = new CompensationWorkflowHook();

        $this->rule = UrbanGoodzCompensationRule::create([
            'rule_key' => 'test_workflow',
            'name' => 'Test Workflow Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'delivery',
            'service_scope' => null,
            'priority' => 10,
            'components' => [
                'flat' => ['amount_cents' => 500],
                'per_mile' => ['rate_cents' => 150, 'basis' => 'miles'],
                'tips' => ['reimburse' => true],
            ],
            'splits' => [
                'basis' => 'customer_charge',
                'dispatcher' => ['percent' => 10],
            ],
            'rounding_mode' => 'half_up',
            'minimum_payout_cents' => 300,
            'maximum_payout_cents' => 5000,
        ]);
    }

    // ---------------------------------------------------------------
    // Work-type mapping
    // ---------------------------------------------------------------

    public function test_work_type_mapping_order(): void
    {
        $this->assertEquals('delivery', CompensationWorkflowHook::workTypeFor('order'));
        $this->assertEquals('delivery', CompensationWorkflowHook::workTypeFor('order_anywhere'));
        $this->assertEquals('delivery', CompensationWorkflowHook::workTypeFor('shopping'));
    }

    public function test_work_type_mapping_route(): void
    {
        $this->assertEquals('route', CompensationWorkflowHook::workTypeFor('dedicated_route'));
        $this->assertEquals('route', CompensationWorkflowHook::workTypeFor('scheduled_route'));
        $this->assertEquals('route', CompensationWorkflowHook::workTypeFor('recurring_route'));
        $this->assertEquals('route', CompensationWorkflowHook::workTypeFor('business_courier'));
    }

    public function test_work_type_mapping_logistics(): void
    {
        $this->assertEquals('logistics', CompensationWorkflowHook::workTypeFor('logistics_load'));
        $this->assertEquals('logistics', CompensationWorkflowHook::workTypeFor('full_truckload'));
    }

    public function test_work_type_mapping_medical(): void
    {
        $this->assertEquals('medical', CompensationWorkflowHook::workTypeFor('medical_courier'));
        $this->assertEquals('medical', CompensationWorkflowHook::workTypeFor('stat_medical'));
    }

    public function test_service_scope_mapping(): void
    {
        $this->assertEquals('marketplace_delivery', CompensationWorkflowHook::serviceScopeFor('order'));
        $this->assertEquals('order_anywhere', CompensationWorkflowHook::serviceScopeFor('order_anywhere'));
        $this->assertEquals('shopping_job', CompensationWorkflowHook::serviceScopeFor('shopping'));
        $this->assertEquals('dedicated_route', CompensationWorkflowHook::serviceScopeFor('dedicated_route'));
        $this->assertEquals('local_logistics', CompensationWorkflowHook::serviceScopeFor('logistics_load'));
        $this->assertEquals('medical_courier', CompensationWorkflowHook::serviceScopeFor('medical_courier'));
    }

    // ---------------------------------------------------------------
    // At Assignment (estimate)
    // ---------------------------------------------------------------

    public function test_at_assignment_returns_estimate(): void
    {
        $result = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $this->assertNotNull($result);
        $this->assertFalse($result->is_final);
        $this->assertNull($result->finalized_at);
        $this->assertEquals($this->rule->id, $result->rule_id);
        $this->assertEquals('test_workflow', $result->rule_key);
        $this->assertEquals(1, $result->rule_version);
    }

    public function test_at_assignment_estimates_driver_amount(): void
    {
        $result = $this->hook->atAssignment('order', [
            'miles' => 10.0,
            'customer_charge_cents' => 2500,
            'tips_cents' => 300,
        ]);

        $this->assertNotNull($result);
        // flat (500) + per_mile (150*10=1500) = 2000 earned, tips (300) passed through
        $this->assertEquals(2300, $result->driver_cents);
    }

    public function test_at_assignment_returns_null_when_no_rule(): void
    {
        $this->rule->update(['is_active' => false]);

        $result = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $this->assertNull($result);
    }

    public function test_at_assignment_preserves_context(): void
    {
        $result = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
            'vehicle_type' => 'cargo_van',
        ]);

        $this->assertNotNull($result);
        $context = $result->context;
        $this->assertEquals(5.0, $context['miles']);
        $this->assertEquals(1500, $context['customer_charge_cents']);
        $this->assertEquals('cargo_van', $context['vehicle_type']);
    }

    public function test_at_assignment_with_overridden_work_type(): void
    {
        $result = $this->hook->atAssignment('order', [
            'work_type' => 'delivery',
            'service_scope' => 'marketplace_delivery',
            'miles' => 3.0,
            'customer_charge_cents' => 1000,
        ]);

        $this->assertNotNull($result);
        $this->assertEquals('delivery', $result->context['work_type']);
    }

    // ---------------------------------------------------------------
    // At Acceptance
    // ---------------------------------------------------------------

    public function test_at_acceptance_preserves_estimate(): void
    {
        $estimate = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $accepted = $this->hook->atAcceptance($estimate, 42);

        $this->assertNotNull($accepted);
        $this->assertEquals($estimate->id, $accepted->id);
        $this->assertFalse($accepted->is_final);
    }

    public function test_at_acceptance_adds_acceptance_metadata(): void
    {
        $estimate = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $accepted = $this->hook->atAcceptance($estimate, 42);

        $breakdown = $accepted->breakdown;
        $this->assertArrayHasKey('accepted_at', $breakdown);
        $this->assertEquals(42, $breakdown['accepted_by']);
    }

    public function test_at_acceptance_on_finalized_is_noop(): void
    {
        $estimate = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        // Simulate finalization
        $estimate->update(['is_final' => true, 'finalized_at' => now()]);

        $result = $this->hook->atAcceptance($estimate, 42);

        $this->assertEquals($estimate->id, $result->id);
        $this->assertTrue($result->is_final);
    }

    public function test_recalc_at_acceptance_creates_new_record(): void
    {
        $original = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $recalc = $this->hook->recalcAtAcceptance('order', [
            'miles' => 8.0,
            'customer_charge_cents' => 2000,
        ], 42);

        $this->assertNotNull($recalc);
        $this->assertNotEquals($original->id, $recalc->id);
        $this->assertFalse($recalc->is_final);
    }

    public function test_recalc_at_acceptance_preserves_rule(): void
    {
        $original = $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 1500,
        ]);

        $recalc = $this->hook->recalcAtAcceptance('order', [
            'miles' => 8.0,
            'customer_charge_cents' => 2000,
        ], 42);

        $this->assertEquals($original->rule_id, $recalc->rule_id);
        $this->assertEquals($original->rule_key, $recalc->rule_key);
    }

    // ---------------------------------------------------------------
    // At Terminal State
    // ---------------------------------------------------------------

    public function test_at_terminal_state_finalizes_result(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertNotNull($result->finalized_at);
        $this->assertEquals('order', $result->subject_type);
        $this->assertEquals(100, $result->subject_id);
        $this->assertEquals(10, $result->driver_id);
    }

    public function test_at_terminal_state_is_idempotent(): void
    {
        $first = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $second = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertEquals($first->id, $second->id);
    }

    public function test_at_terminal_state_prevents_retroactive_mutation(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $this->expectException(\RuntimeException::class);
        $result->update(['driver_cents' => 99999]);
    }

    public function test_at_terminal_state_creates_ledger_instruction(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $ledger = \App\Models\UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 100)
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals('outbound', $ledger->direction);
        $this->assertEquals('completed', $ledger->payment_status);
        $this->assertNotNull($ledger->idempotency_key);
    }

    public function test_at_terminal_state_creates_driver_earning(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $earning = \App\Models\UrbanGoodzDriverEarning::query()
            ->where('delivery_man_id', 10)
            ->where('earning_type', 'marketplace_delivery')
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals('approved', $earning->status);
    }

    public function test_at_terminal_state_no_duplicate_ledger(): void
    {
        // First finalization
        $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $countBefore = \App\Models\UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 100)
            ->count();

        // Idempotent — should not create a second entry
        $this->hook->atTerminalState(
            'order',
            'order',
            100,
            ['miles' => 5.0, 'customer_charge_cents' => 1500],
            10,
        );

        $countAfter = \App\Models\UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 100)
            ->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_at_terminal_state_cancellation_flag(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            200,
            [
                'miles' => 5.0,
                'customer_charge_cents' => 1500,
                'is_cancelled' => true,
            ],
            10,
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        // Cancellation with a configured cancellation component replaces earning
        $this->assertTrue($result->context['is_cancelled']);
    }

    public function test_at_terminal_state_failed_delivery(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            300,
            [
                'miles' => 5.0,
                'customer_charge_cents' => 1500,
                'is_failed_delivery' => true,
            ],
            10,
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertTrue($result->context['is_failed_delivery']);
    }

    // ---------------------------------------------------------------
    // Workflow-specific convenience methods
    // ---------------------------------------------------------------

    private function makeOrder(array $attrs = []): \App\Models\Order
    {
        $defaults = [
            'order_status' => 'delivered',
            'delivery_man_id' => 10,
            'distance' => 5.0,
            'order_amount' => 15.00,
            'tip' => 3.00,
            'item_count' => 1,
        ];

        $attrs = array_merge($defaults, $attrs);

        $order = new \App\Models\Order();
        // Set attributes without mass assignment by using fill on non-guarded attrs
        foreach ($attrs as $key => $value) {
            $order->$key = $value;
        }

        return $order;
    }

    public function test_on_order_delivered(): void
    {
        $order = $this->makeOrder(['id' => 999, 'order_status' => 'delivered', 'item_count' => 2]);

        $result = $this->hook->onOrderDelivered($order);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertEquals('order', $result->subject_type);
        $this->assertEquals(999, $result->subject_id);
    }

    public function test_on_order_cancelled(): void
    {
        $order = $this->makeOrder(['id' => 998, 'order_status' => 'canceled']);

        $result = $this->hook->onOrderCancelled($order);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertTrue($result->context['is_cancelled']);
    }

    public function test_on_order_failed_delivery(): void
    {
        $order = $this->makeOrder(['id' => 997, 'order_status' => 'failed']);

        $result = $this->hook->onOrderFailedDelivery($order);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertTrue($result->context['is_failed_delivery']);
    }

    public function test_on_order_return(): void
    {
        $order = $this->makeOrder(['id' => 996, 'order_status' => 'returned']);

        $result = $this->hook->onOrderReturn($order);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertTrue($result->context['is_return_trip']);
    }

    public function test_on_order_redelivery(): void
    {
        $order = $this->makeOrder(['id' => 995, 'order_status' => 'delivered']);

        $result = $this->hook->onOrderRedelivery($order);

        $this->assertNotNull($result);
        $this->assertTrue($result->is_final);
        $this->assertTrue($result->context['is_redelivery']);
    }

    public function test_hook_calculates_correct_amounts(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            [
                'miles' => 10.0,
                'customer_charge_cents' => 3000,
                'tips_cents' => 200,
            ],
            10,
        );

        $this->assertNotNull($result);
        // flat (500) + per_mile (150*10=1500) = 2000 earned
        // tips (200) passed through
        // total = 2200
        $this->assertEquals(2200, $result->driver_cents);
    }

    public function test_hook_exposes_deficit_flag(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            [
                'miles' => 50.0,
                'customer_charge_cents' => 1000,
            ],
            10,
        );

        $this->assertNotNull($result);
        // flat(500) + per_mile(150*50=7500) = 8000 earned on 1000 basis = deficit
        $this->assertTrue($result->splits['is_deficit']);
    }

    public function test_hook_minimum_payout_clamp(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            [
                'miles' => 0.0,
                'customer_charge_cents' => 500,
            ],
            10,
        );

        $this->assertNotNull($result);
        // flat(500) + per_mile(0) = 500, but minimum is 300
        $this->assertEquals(500, $result->driver_cents);
    }

    public function test_hook_maximum_payout_clamp(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            [
                'miles' => 100.0,
                'customer_charge_cents' => 50000,
            ],
            10,
        );

        $this->assertNotNull($result);
        // flat(500) + per_mile(150*100=15000) = 15500 but max is 5000
        $this->assertEquals(5000, $result->driver_cents);
    }

    public function test_hook_tips_outside_clamp(): void
    {
        $result = $this->hook->atTerminalState(
            'order',
            'order',
            100,
            [
                'miles' => 0.0,
                'customer_charge_cents' => 100,
                'tips_cents' => 5000,
            ],
            10,
        );

        $this->assertNotNull($result);
        // flat(500) + per_mile(0) = 500 earned, clamped to min 300
        // tips(5000) pass through
        $this->assertEquals(5500, $result->driver_cents);
    }
}
