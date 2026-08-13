<?php

namespace Tests\Feature;

use App\Exceptions\DriverCompensationConfigurationException;
use App\Models\UrbanGoodzDriverPricingPolicy;
use App\Services\UrbanGoodz\DriverCompensationContext;
use App\Services\UrbanGoodz\UrbanGoodzDriverCompensationResolver;
use App\Services\UrbanGoodz\UrbanGoodzDriverPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Driver pay is whatever the Master Admin configures, resolved through the
 * documented hierarchy — not "delivery charge minus admin fee".
 */
class UrbanGoodzDriverCompensationResolverTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzDriverCompensationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UrbanGoodzDriverCompensationResolver();
        UrbanGoodzDriverPricingPolicy::query()->delete();

        // urban_goodz_driver_pricing_policies.zone_id has a real FK to
        // zones. Other test classes in the same run use RefreshDatabase,
        // which re-migrates without reseeding zones, so this can't assume
        // zone id 2 survived from wherever it was expected to come from.
        if (!DB::table('zones')->where('id', 2)->exists()) {
            DB::table('zones')->updateOrInsert(['id' => 2], [
                'name' => 'Test Zone 2',
                'coordinates' => null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function policy(array $attributes): UrbanGoodzDriverPricingPolicy
    {
        return UrbanGoodzDriverPricingPolicy::create(array_merge([
            'policy_type' => 'business_routes',
            'name' => 'test policy',
            'payout_model' => 'fixed_payout',
            'fixed_amount' => 100.00,
            'is_active' => true,
            'priority' => 0,
            'version' => 1,
        ], $attributes));
    }

    private function context(array $overrides = []): DriverCompensationContext
    {
        return new DriverCompensationContext(
            policyType: $overrides['policyType'] ?? 'business_routes',
            zoneId: $overrides['zoneId'] ?? null,
            market: $overrides['market'] ?? null,
            moduleId: $overrides['moduleId'] ?? null,
            businessClientId: $overrides['businessClientId'] ?? null,
            contractId: $overrides['contractId'] ?? null,
            routeId: $overrides['routeId'] ?? null,
            routeScope: $overrides['routeScope'] ?? null,
            serviceType: $overrides['serviceType'] ?? null,
            vehicleTypeId: $overrides['vehicleTypeId'] ?? null,
            loadType: $overrides['loadType'] ?? null,
            medicalType: $overrides['medicalType'] ?? null,
            subjectType: $overrides['subjectType'] ?? null,
            subjectId: $overrides['subjectId'] ?? null,
            at: $overrides['at'] ?? null,
        );
    }

    // ---------- hierarchy ----------

    public function test_assignment_rate_beats_every_other_tier(): void
    {
        $this->policy(['name' => 'global']);
        $this->policy(['name' => 'business', 'business_client_id' => 7]);
        $this->policy(['name' => 'contract', 'contract_id' => 3]);
        $this->policy([
            'name' => 'assignment',
            'subject_type' => 'App\\Models\\UrbanGoodzDedicatedRoute',
            'subject_id' => 42,
        ]);

        $policy = $this->resolver->resolve($this->context([
            'businessClientId' => 7,
            'contractId' => 3,
            'subjectType' => 'App\\Models\\UrbanGoodzDedicatedRoute',
            'subjectId' => 42,
        ]));

        $this->assertSame('assignment', $policy->name);
    }

    public function test_contract_beats_business_and_zone(): void
    {
        $this->policy(['name' => 'zone', 'zone_id' => 2]);
        $this->policy(['name' => 'business', 'business_client_id' => 7]);
        $this->policy(['name' => 'contract', 'contract_id' => 3]);

        $policy = $this->resolver->resolve($this->context([
            'zoneId' => 2, 'businessClientId' => 7, 'contractId' => 3,
        ]));

        $this->assertSame('contract', $policy->name);
    }

    public function test_dedicated_route_beats_recurring_route(): void
    {
        $this->policy(['name' => 'recurring', 'route_id' => 9, 'route_scope' => 'recurring']);
        $this->policy(['name' => 'dedicated', 'route_id' => 9, 'route_scope' => 'dedicated']);

        $dedicated = $this->resolver->resolve($this->context([
            'routeId' => 9, 'routeScope' => 'dedicated',
        ]));
        $recurring = $this->resolver->resolve($this->context([
            'routeId' => 9, 'routeScope' => 'recurring',
        ]));

        $this->assertSame('dedicated', $dedicated->name);
        $this->assertSame('recurring', $recurring->name);
    }

    public function test_business_beats_service_vehicle_and_zone(): void
    {
        $this->policy(['name' => 'zone', 'zone_id' => 2]);
        $this->policy(['name' => 'vehicle', 'vehicle_type_id' => 5]);
        $this->policy(['name' => 'service', 'service_type' => 'stat']);
        $this->policy(['name' => 'business', 'business_client_id' => 7]);

        $policy = $this->resolver->resolve($this->context([
            'zoneId' => 2, 'vehicleTypeId' => 5, 'serviceType' => 'stat', 'businessClientId' => 7,
        ]));

        $this->assertSame('business', $policy->name);
    }

    public function test_vehicle_type_beats_load_type_and_medical_type(): void
    {
        $this->policy(['name' => 'medical', 'medical_type' => 'specimen']);
        $this->policy(['name' => 'load', 'load_type' => 'reefer']);
        $this->policy(['name' => 'vehicle', 'vehicle_type_id' => 5]);

        $policy = $this->resolver->resolve($this->context([
            'medicalType' => 'specimen', 'loadType' => 'reefer', 'vehicleTypeId' => 5,
        ]));

        $this->assertSame('vehicle', $policy->name);
    }

    public function test_module_default_beats_the_global_fallback(): void
    {
        $this->policy(['name' => 'global']);
        $this->policy(['name' => 'module', 'module_id' => 4]);

        $policy = $this->resolver->resolve($this->context(['moduleId' => 4]));

        $this->assertSame('module', $policy->name);
    }

    public function test_a_general_policy_never_displaces_a_specific_one(): void
    {
        $this->policy(['name' => 'specific', 'business_client_id' => 7, 'priority' => 0]);
        $this->policy(['name' => 'broad', 'priority' => 999]);

        $policy = $this->resolver->resolve($this->context(['businessClientId' => 7]));

        $this->assertSame('specific', $policy->name);
    }

    public function test_priority_breaks_ties_within_a_tier(): void
    {
        $this->policy(['name' => 'low', 'zone_id' => 2, 'priority' => 1]);
        $this->policy(['name' => 'high', 'zone_id' => 2, 'priority' => 8]);

        $policy = $this->resolver->resolve($this->context(['zoneId' => 2]));

        $this->assertSame('high', $policy->name);
    }

    public function test_a_business_policy_does_not_leak_to_another_business(): void
    {
        $this->policy(['name' => 'global']);
        $this->policy(['name' => 'business 7', 'business_client_id' => 7]);

        $policy = $this->resolver->resolve($this->context(['businessClientId' => 99]));

        $this->assertSame('global', $policy->name);
    }

    public function test_policies_for_another_job_family_are_not_considered(): void
    {
        $this->policy(['name' => 'medical global', 'policy_type' => 'medical_courier']);

        $this->assertNull($this->resolver->resolve($this->context(['policyType' => 'business_routes'])));
    }

    public function test_the_business_multi_stop_alias_still_resolves(): void
    {
        $this->policy(['name' => 'legacy alias', 'policy_type' => 'business_multi_stop']);

        $policy = $this->resolver->resolve($this->context(['policyType' => 'business_routes']));

        $this->assertSame('legacy alias', $policy->name);
    }

    // ---------- effective dating ----------

    public function test_a_rate_change_does_not_apply_to_an_earlier_assignment(): void
    {
        $this->policy([
            'name' => 'old', 'zone_id' => 2, 'fixed_amount' => 100.00,
            'effective_from' => '2026-07-01 00:00:00',
            'effective_to' => '2026-07-30 12:00:00',
        ]);
        $this->policy([
            'name' => 'new', 'zone_id' => 2, 'fixed_amount' => 150.00,
            'effective_from' => '2026-07-30 12:00:00',
        ]);

        $before = $this->resolver->resolve($this->context([
            'zoneId' => 2, 'at' => new \DateTimeImmutable('2026-07-30 09:00:00'),
        ]));
        $after = $this->resolver->resolve($this->context([
            'zoneId' => 2, 'at' => new \DateTimeImmutable('2026-07-30 15:00:00'),
        ]));

        $this->assertSame('old', $before->name);
        $this->assertSame('new', $after->name);
    }

    public function test_inactive_policies_are_ignored(): void
    {
        $this->policy(['name' => 'global']);
        $this->policy(['name' => 'disabled', 'business_client_id' => 7, 'is_active' => false]);

        $policy = $this->resolver->resolve($this->context(['businessClientId' => 7]));

        $this->assertSame('global', $policy->name);
    }

    // ---------- safe failure ----------

    public function test_missing_configuration_returns_null_by_default(): void
    {
        $this->assertNull($this->resolver->resolve($this->context()));
    }

    public function test_missing_configuration_halts_in_strict_mode(): void
    {
        try {
            $this->resolver->resolveOrFail($this->context(['policyType' => 'medical_courier']));
            $this->fail('Expected compensation resolution to halt.');
        } catch (DriverCompensationConfigurationException $exception) {
            $this->assertSame('missing_configuration', $exception->reason);
            $this->assertStringContainsString('medical_courier', $exception->getMessage());
        }
    }

    // ---------- backwards compatibility ----------

    public function test_the_existing_service_signature_still_resolves_zone_then_global(): void
    {
        $this->policy(['name' => 'global']);
        $this->policy(['name' => 'zone 2', 'zone_id' => 2]);

        $service = app(UrbanGoodzDriverPricingService::class);

        $this->assertSame('zone 2', $service->resolvePolicy('business_routes', 2)->name);
        $this->assertSame('global', $service->resolvePolicy('business_routes', null)->name);
    }

    /**
     * The payout methods the specification lists are already implemented by
     * calculatePayout(); this pins that the deeper resolver feeds them.
     */
    public function test_resolved_policy_drives_the_configured_payout_model(): void
    {
        $this->policy([
            'name' => 'per package', 'business_client_id' => 7,
            'payout_model' => 'per_package', 'rate_per_package' => 2.00,
        ]);

        $service = app(UrbanGoodzDriverPricingService::class);
        $policy = $service->resolvePolicyFor($this->context(['businessClientId' => 7]));

        $this->assertSame('per_package', $policy->payout_model);
        $this->assertEquals(2.00, (float) $policy->rate_per_package);

        // 50 packages at $2.00 = $100.00, per the specification's worked example.
        $result = $service->calculatePayout('business_routes', [
            'packages' => 50,
            'business_client_id' => 7,
        ]);

        $this->assertEqualsWithDelta(100.00, (float) $result['payout'], 0.001);
    }
}
