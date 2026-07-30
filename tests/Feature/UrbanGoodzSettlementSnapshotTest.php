<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCommissionRule;
use App\Models\UrbanGoodzSettlementSnapshot;
use App\Services\UrbanGoodz\CommissionContext;
use App\Services\UrbanGoodz\UrbanGoodzCommissionResolver;
use App\Services\UrbanGoodz\UrbanGoodzSettlementRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * Every completed transaction must be reconstructable exactly as it settled.
 */
class UrbanGoodzSettlementSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCommissionResolver $resolver;
    private UrbanGoodzSettlementRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UrbanGoodzCommissionResolver();
        $this->recorder = new UrbanGoodzSettlementRecorder();
        UrbanGoodzCommissionRule::query()->forceDelete();
    }

    private function loadBoardRule(string $rate = '5.0000'): UrbanGoodzCommissionRule
    {
        return UrbanGoodzCommissionRule::create([
            'name' => 'load board',
            'transaction_type' => CommissionContext::TYPE_LOAD_BOARD,
            'basis' => 'booked_load_amount',
            'calculation_type' => UrbanGoodzCommissionRule::CALC_PERCENTAGE,
            'rate_percent' => $rate,
            'commission_enabled' => true,
            'is_active' => true,
            'version' => 3,
        ]);
    }

    private function context(int $cents = 100000): CommissionContext
    {
        return new CommissionContext(
            transactionType: CommissionContext::TYPE_LOAD_BOARD,
            qualifyingAmountCents: $cents,
            partnerType: 'carrier',
            partnerId: 77,
            subjectType: 'App\\Models\\UrbanGoodzLoadBoardLoad',
            subjectId: 1234,
        );
    }

    public function test_snapshot_captures_the_rule_that_produced_the_amount(): void
    {
        $rule = $this->loadBoardRule();
        $context = $this->context();
        $commission = $this->resolver->resolve($context);

        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad',
            1234,
            $context,
            $commission,
            inputs: ['loaded_miles' => 420],
            driver: [
                'method' => 'base_mileage',
                'gross_cents' => 52500,
                'admin_fee_cents' => 5250,
                'net_cents' => 47250,
            ],
        );

        $this->assertSame($rule->id, $snapshot->commission_rule_id);
        $this->assertSame(3, $snapshot->commission_rule_version);
        $this->assertSame('percentage', $snapshot->commission_calculation_type);
        $this->assertSame('booked_load_amount', $snapshot->commission_basis);
        $this->assertSame(100000, $snapshot->qualifying_amount_cents);
        $this->assertSame(5000, $snapshot->commission_amount_cents);
        $this->assertSame(95000, $snapshot->partner_net_cents);
        $this->assertSame(420, $snapshot->inputs['loaded_miles']);
        $this->assertSame('5.0000', $snapshot->rule_snapshot['rate_percent']);
    }

    public function test_both_sides_balance_in_integer_cents(): void
    {
        $this->loadBoardRule();
        $context = $this->context();
        $commission = $this->resolver->resolve($context);

        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $commission,
            driver: ['gross_cents' => 52500, 'admin_fee_cents' => 5250, 'net_cents' => 47250],
        );

        $this->assertTrue($snapshot->balances(), 'business side must balance');
        $this->assertTrue($snapshot->driverBalances(), 'driver side must balance');
    }

    /** The business commission and the driver admin fee are separate events. */
    public function test_driver_admin_fee_is_not_netted_against_the_business_commission(): void
    {
        $this->loadBoardRule();
        $context = $this->context();
        $commission = $this->resolver->resolve($context);

        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $commission,
            driver: ['gross_cents' => 52500, 'admin_fee_cents' => 5250, 'net_cents' => 47250],
        );

        $this->assertSame(5000, $snapshot->commission_amount_cents);
        $this->assertSame(5250, $snapshot->driver_admin_fee_cents);
        $this->assertNotSame(
            $snapshot->commission_amount_cents + $snapshot->driver_admin_fee_cents,
            $snapshot->partner_gross_cents - $snapshot->partner_net_cents,
            'the two revenue events must not be combined'
        );
    }

    public function test_duplicate_finalization_returns_the_same_snapshot(): void
    {
        $this->loadBoardRule();
        $context = $this->context();
        $commission = $this->resolver->resolve($context);

        $first = $this->recorder->record('App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $commission);
        $second = $this->recorder->record('App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $commission);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UrbanGoodzSettlementSnapshot::where('subject_id', 1234)->count());
    }

    public function test_a_snapshot_cannot_be_edited(): void
    {
        $this->loadBoardRule();
        $context = $this->context();
        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $this->resolver->resolve($context)
        );

        $this->expectException(RuntimeException::class);
        $snapshot->update(['commission_amount_cents' => 1]);
    }

    public function test_a_snapshot_cannot_be_deleted(): void
    {
        $this->loadBoardRule();
        $context = $this->context();
        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $this->resolver->resolve($context)
        );

        $this->expectException(RuntimeException::class);
        $snapshot->delete();
    }

    /**
     * A later rate change must not alter what an earlier transaction recorded —
     * this is what lets a refund honour the original terms.
     */
    public function test_a_later_rate_change_does_not_alter_a_recorded_settlement(): void
    {
        $rule = $this->loadBoardRule('5.0000');
        $context = $this->context();
        $snapshot = $this->recorder->record(
            'App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $context, $this->resolver->resolve($context)
        );

        $rule->update(['rate_percent' => '25.0000', 'version' => 4]);

        $reloaded = $snapshot->fresh();

        $this->assertSame(5000, $reloaded->commission_amount_cents);
        $this->assertSame(3, $reloaded->commission_rule_version);
        $this->assertSame('5.0000', $reloaded->rule_snapshot['rate_percent']);
    }

    public function test_distinct_subjects_get_distinct_snapshots(): void
    {
        $this->loadBoardRule();
        $a = $this->context();
        $b = new CommissionContext(
            transactionType: CommissionContext::TYPE_LOAD_BOARD,
            qualifyingAmountCents: 100000,
            partnerType: 'carrier',
            partnerId: 77,
            subjectType: 'App\\Models\\UrbanGoodzLoadBoardLoad',
            subjectId: 9999,
        );

        $first = $this->recorder->record('App\\Models\\UrbanGoodzLoadBoardLoad', 1234, $a, $this->resolver->resolve($a));
        $second = $this->recorder->record('App\\Models\\UrbanGoodzLoadBoardLoad', 9999, $b, $this->resolver->resolve($b));

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->idempotency_key, $second->idempotency_key);
    }
}
