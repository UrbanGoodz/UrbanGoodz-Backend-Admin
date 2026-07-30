<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPricingPolicy;
use App\Services\UrbanGoodz\DriverCompensationContext;
use App\Services\UrbanGoodz\UrbanGoodzDriverPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A driver earning must record which policy produced it, by which method, and
 * from which verified operational data — otherwise a later rate change makes
 * the figure unexplainable and a pay dispute cannot be settled.
 */
class UrbanGoodzDriverEarningSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzDriverPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UrbanGoodzDriverPricingService::class);
        UrbanGoodzDriverPricingPolicy::query()->delete();
    }

    private function perPackagePolicy(): UrbanGoodzDriverPricingPolicy
    {
        return UrbanGoodzDriverPricingPolicy::create([
            'policy_type' => 'business_routes',
            'name' => 'per package route',
            'payout_model' => 'per_package',
            'rate_per_package' => 2.00,
            'business_client_id' => 7,
            'is_active' => true,
            'version' => 5,
        ]);
    }

    public function test_earning_records_the_policy_method_and_inputs(): void
    {
        $policy = $this->perPackagePolicy();

        $earning = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => 100.00,
            'earning_type' => 'business_routes',
            'policy' => $policy,
            'gross_cents' => 10000,
            'admin_fee_cents' => 1000,
            'calculation_inputs' => ['packages_completed' => 50, 'eligible_miles' => 31.4],
            'idempotency_key' => 'route:900:driver:20',
            'bypass_wallet' => true,
        ]);

        $this->assertSame($policy->id, $earning->pricing_policy_id);
        $this->assertSame(5, $earning->pricing_policy_version);
        $this->assertSame('per_package', $earning->payout_model);
        $this->assertSame(10000, $earning->gross_cents);
        $this->assertSame(1000, $earning->admin_fee_cents);
        $this->assertSame(9000, $earning->net_cents, 'net = gross - admin fee');
        $this->assertSame(50, $earning->calculation_inputs['packages_completed']);
        $this->assertSame('per_package', $earning->policy_snapshot['payout_model']);
    }

    public function test_net_defaults_to_gross_when_no_admin_fee_applies(): void
    {
        $earning = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => 25.00,
            'earning_type' => 'marketplace_delivery',
            'idempotency_key' => 'order:100009:driver:20',
            'bypass_wallet' => true,
        ]);

        $this->assertSame(2500, $earning->gross_cents);
        $this->assertNull($earning->admin_fee_cents);
        $this->assertSame(2500, $earning->net_cents);
    }

    /** A replayed completion must not pay the driver twice. */
    public function test_recording_the_same_key_twice_returns_the_first_earning(): void
    {
        $first = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => 40.00,
            'earning_type' => 'business_routes',
            'idempotency_key' => 'route:901:driver:20',
            'bypass_wallet' => true,
        ]);

        $second = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => 40.00,
            'earning_type' => 'business_routes',
            'idempotency_key' => 'route:901:driver:20',
            'bypass_wallet' => true,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            UrbanGoodzDriverEarning::where('idempotency_key', 'route:901:driver:20')->count()
        );
    }

    public function test_a_later_policy_change_does_not_alter_a_recorded_earning(): void
    {
        $policy = $this->perPackagePolicy();

        $earning = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => 100.00,
            'earning_type' => 'business_routes',
            'policy' => $policy,
            'gross_cents' => 10000,
            'idempotency_key' => 'route:902:driver:20',
            'bypass_wallet' => true,
        ]);

        $policy->update(['rate_per_package' => 5.00, 'version' => 6]);

        $reloaded = $earning->fresh();

        $this->assertSame(10000, $reloaded->gross_cents);
        $this->assertSame(5, $reloaded->pricing_policy_version);
        $this->assertEquals(2.00, (float) $reloaded->policy_snapshot['rate_per_package']);
    }

    /**
     * The specification's worked example: 50 packages at $2.00 is $100.00 gross,
     * a $10.00 admin fee leaves $90.00 net — and the driver's pay comes from the
     * configured rule, not from the customer's delivery charge.
     */
    public function test_per_package_route_matches_the_specification_example(): void
    {
        $this->perPackagePolicy();

        $result = $this->service->calculatePayout('business_routes', [
            'packages' => 50,
            'business_client_id' => 7,
        ]);

        $grossCents = (int) round(((float) $result['payout']) * 100);
        $adminFeeCents = (int) round($grossCents * 0.10);

        $earning = $this->service->recordEarning([
            'delivery_man_id' => 20,
            'amount' => (float) $result['payout'],
            'earning_type' => 'business_routes',
            'policy' => $this->service->resolvePolicyFor(new DriverCompensationContext(
                policyType: 'business_routes',
                businessClientId: 7,
            )),
            'gross_cents' => $grossCents,
            'admin_fee_cents' => $adminFeeCents,
            'calculation_inputs' => ['packages_completed' => 50],
            'idempotency_key' => 'route:903:driver:20',
            'bypass_wallet' => true,
        ]);

        $this->assertSame(10000, $earning->gross_cents);
        $this->assertSame(1000, $earning->admin_fee_cents);
        $this->assertSame(9000, $earning->net_cents);
    }
}
