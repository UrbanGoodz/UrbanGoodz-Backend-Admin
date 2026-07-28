<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCompensationRule;
use App\Models\UrbanGoodzPaymentLedger;
use App\Services\UrbanGoodz\Compensation\CompensationWorkflowHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzCompensationLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private CompensationWorkflowHook $hook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hook = new CompensationWorkflowHook();

        UrbanGoodzCompensationRule::create([
            'rule_key' => 'ledger_test',
            'name' => 'Ledger Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'delivery',
            'priority' => 10,
            'components' => [
                'flat' => ['amount_cents' => 500],
                'per_mile' => ['rate_cents' => 150],
                'tips' => ['reimburse' => true],
            ],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 300,
            'maximum_payout_cents' => 5000,
        ]);
    }

    public function test_ledger_entry_created_on_finalization(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->first();

        $this->assertNotNull($ledger);
    }

    public function test_ledger_has_correct_fields(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->first();

        $this->assertEquals('outbound', $ledger->direction);
        $this->assertEquals('completed', $ledger->payment_status);
        $this->assertEquals('USD', $ledger->currency);
        $this->assertNotNull($ledger->ledger_number);
        $this->assertStringStartsWith('UGL-', $ledger->ledger_number);
        $this->assertEquals(10, $ledger->delivery_man_id);
    }

    public function test_ledger_metadata_contains_compensation_details(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->first();

        $metadata = $ledger->metadata;
        $this->assertArrayHasKey('compensation_result_id', $metadata);
        $this->assertArrayHasKey('rule_id', $metadata);
        $this->assertArrayHasKey('rule_key', $metadata);
        $this->assertArrayHasKey('rule_version', $metadata);
        $this->assertArrayHasKey('earned_cents', $metadata);
        $this->assertArrayHasKey('pass_through_cents', $metadata);
        $this->assertArrayHasKey('is_deficit', $metadata);
    }

    public function test_ledger_feature_field(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->first();

        $this->assertEquals('compensation_order', $ledger->feature);
    }

    public function test_ledger_feature_for_load(): void
    {
        UrbanGoodzCompensationRule::create([
            'rule_key' => 'ledger_load_test',
            'name' => 'Ledger Load Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'logistics',
            'priority' => 10,
            'components' => ['flat' => ['amount_cents' => 1000]],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 500,
            'maximum_payout_cents' => 10000,
        ]);

        $this->hook->atTerminalState(
            'logistics_load',
            'load',
            600,
            ['miles' => 10.0, 'customer_charge_cents' => 5000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 600)
            ->first();

        $this->assertEquals('compensation_logistics_load', $ledger->feature);
    }

    public function test_ledger_feature_for_medical(): void
    {
        UrbanGoodzCompensationRule::create([
            'rule_key' => 'ledger_medical_test',
            'name' => 'Ledger Medical Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'medical',
            'priority' => 10,
            'components' => ['flat' => ['amount_cents' => 800]],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 500,
            'maximum_payout_cents' => 8000,
        ]);

        $this->hook->atTerminalState(
            'medical_courier',
            'medical_job',
            700,
            ['miles' => 3.0, 'customer_charge_cents' => 2500],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 700)
            ->first();

        $this->assertEquals('compensation_medical_courier', $ledger->feature);
    }

    public function test_ledger_idempotent_prevents_duplicate(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $count1 = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->count();

        // Second call — idempotent
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $count2 = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->count();

        $this->assertEquals($count1, $count2);
    }

    public function test_ledger_not_created_for_estimate(): void
    {
        $this->hook->atAssignment('order', [
            'miles' => 5.0,
            'customer_charge_cents' => 2000,
        ]);

        $count = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_ledger_idempotency_key_format(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 500)
            ->first();

        $this->assertMatchesRegularExpression(
            '/^compensation:order:500:\d+$/',
            $ledger->idempotency_key
        );
    }

    public function test_ledger_driver_earning_created(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            10,
        );

        $earning = \App\Models\UrbanGoodzDriverEarning::query()
            ->where('delivery_man_id', 10)
            ->where('earning_type', 'marketplace_delivery')
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals('approved', $earning->status);
        $this->assertStringContainsString('rule ledger_test', $earning->description);
    }

    public function test_ledger_driver_earning_not_created_without_driver(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            500,
            ['miles' => 5.0, 'customer_charge_cents' => 2000],
            null,
        );

        $count = \App\Models\UrbanGoodzDriverEarning::query()
            ->where('earning_type', 'marketplace_delivery')
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_ledger_driver_earning_for_load(): void
    {
        UrbanGoodzCompensationRule::create([
            'rule_key' => 'ledger_load_earning_test',
            'name' => 'Ledger Load Earning Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'logistics',
            'priority' => 10,
            'components' => ['flat' => ['amount_cents' => 1000]],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 500,
            'maximum_payout_cents' => 10000,
        ]);

        $this->hook->atTerminalState(
            'logistics_load',
            'load',
            800,
            ['miles' => 10.0, 'customer_charge_cents' => 5000],
            10,
        );

        $earning = \App\Models\UrbanGoodzDriverEarning::query()
            ->where('delivery_man_id', 10)
            ->where('earning_type', 'logistics_loads')
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals('approved', $earning->status);
    }

    public function test_ledger_for_cancellation(): void
    {
        $this->hook->atTerminalState(
            'order',
            'order',
            501,
            [
                'miles' => 5.0,
                'customer_charge_cents' => 2000,
                'is_cancelled' => true,
            ],
            10,
        );

        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 501)
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals('outbound', $ledger->direction);
    }

    public function test_zero_driver_amount_no_earning(): void
    {
        UrbanGoodzCompensationRule::create([
            'rule_key' => 'zero_pay_test',
            'name' => 'Zero Pay Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'delivery',
            'priority' => 10,
            'components' => [],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 0,
            'maximum_payout_cents' => 0,
        ]);

        $this->hook->atTerminalState(
            'order',
            'order',
            502,
            ['miles' => 0, 'customer_charge_cents' => 0],
            10,
        );

        // Ledger is still created (for audit trail), but no driver earning
        $ledger = UrbanGoodzPaymentLedger::query()
            ->where('event_type', 'compensation_finalized')
            ->where('payable_id', 502)
            ->first();

        $this->assertNotNull($ledger);

        $earning = \App\Models\UrbanGoodzDriverEarning::query()
            ->where('delivery_man_id', 10)
            ->where('earning_type', 'marketplace_delivery')
            ->count();

        $this->assertEquals(0, $earning);
    }
}
