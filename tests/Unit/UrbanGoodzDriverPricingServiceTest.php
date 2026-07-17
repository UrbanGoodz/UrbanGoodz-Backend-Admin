<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzDriverPricingPolicy;
use App\Services\UrbanGoodz\DynamicPricingService;
use App\Services\UrbanGoodz\UrbanGoodzDriverPricingService;
use Tests\TestCase;

class UrbanGoodzDriverPricingServiceTest extends TestCase
{
    private function makePolicy(array $attributes = []): UrbanGoodzDriverPricingPolicy
    {
        $policy = new UrbanGoodzDriverPricingPolicy();
        $policy->id = $attributes['id'] ?? 1;
        $policy->forceFill(array_merge([
            'policy_type' => 'marketplace_delivery',
            'name' => 'Test Policy',
            'payout_model' => 'fixed_payout',
            'fixed_amount' => 10,
            'base_fare' => 0,
            'rate_per_mile' => 0,
            'rate_per_minute' => 0,
            'rate_per_stop' => 0,
            'rate_per_package' => 0,
            'revenue_percentage' => 0,
            'dynamic_pricing_enabled' => false,
            'recommendation_only' => false,
            'auto_apply_within_limits' => false,
            'dispatcher_approval_required' => false,
            'admin_approval_required' => false,
            'live_pricing_enabled' => false,
            'sandbox_pricing_enabled' => true,
            'vehicle_multipliers' => null,
            'urgency_premium' => 0,
            'deadhead_pay_rate' => 0,
            'waiting_pay_rate' => 0,
            'return_pay_rate' => 0,
            'exception_pay_rate' => 0,
            'minimum_payout' => null,
            'maximum_payout' => null,
            'minimum_margin' => null,
            'is_active' => true,
        ], $attributes));

        return $policy;
    }

    public function test_calculate_payout_uses_fallback_when_policy_not_found(): void
    {
        $dynamicPricing = $this->createMock(DynamicPricingService::class);

        $service = $this->getMockBuilder(UrbanGoodzDriverPricingService::class)
            ->setConstructorArgs([$dynamicPricing])
            ->onlyMethods(['resolvePolicy'])
            ->getMock();

        $service->method('resolvePolicy')->willReturn(null);

        $result = $service->calculatePayout('marketplace_delivery', ['base_amount' => 8.75]);

        $this->assertSame(8.75, $result['payout']);
        $this->assertSame('fallback', $result['payout_model']);
        $this->assertNull($result['policy_id']);
    }

    public function test_calculate_payout_applies_operational_rates_and_limits(): void
    {
        $dynamicPricing = $this->createMock(DynamicPricingService::class);
        $policy = $this->makePolicy([
            'id' => 7,
            'payout_model' => 'base_mileage_time',
            'base_fare' => 5,
            'rate_per_mile' => 1,
            'rate_per_minute' => 0.5,
            'urgency_premium' => 2,
            'deadhead_pay_rate' => 0.4,
            'waiting_pay_rate' => 0.1,
            'return_pay_rate' => 1,
            'exception_pay_rate' => 1,
            'maximum_payout' => 20,
            'minimum_margin' => 20,
        ]);

        $service = $this->getMockBuilder(UrbanGoodzDriverPricingService::class)
            ->setConstructorArgs([$dynamicPricing])
            ->onlyMethods(['resolvePolicy'])
            ->getMock();

        $service->method('resolvePolicy')->willReturn($policy);

        $result = $service->calculatePayout('dedicated_routes', [
            'mileage' => 10,
            'duration' => 20,
            'deadhead_miles' => 5,
            'waiting_minutes' => 10,
            'is_urgent' => true,
            'is_returned' => true,
            'is_exception' => true,
            'revenue' => 40,
        ]);

        $this->assertSame(20.0, $result['payout']);
        $this->assertSame('base_mileage_time', $result['payout_model']);
        $this->assertSame(7, $result['policy_id']);
    }

    public function test_calculate_payout_supports_numeric_string_inputs(): void
    {
        $dynamicPricing = $this->createMock(DynamicPricingService::class);
        $policy = $this->makePolicy([
            'id' => 9,
            'payout_model' => 'per_package',
            'rate_per_package' => 2.5,
        ]);

        $service = $this->getMockBuilder(UrbanGoodzDriverPricingService::class)
            ->setConstructorArgs([$dynamicPricing])
            ->onlyMethods(['resolvePolicy'])
            ->getMock();

        $service->method('resolvePolicy')->willReturn($policy);

        $result = $service->calculatePayout('returns_exceptions', ['packages' => '4']);

        $this->assertSame(10.0, $result['payout']);
    }

    public function test_calculate_payout_uses_dynamic_pricing_service_for_dynamic_mode(): void
    {
        $dynamicPricing = $this->createMock(DynamicPricingService::class);
        $dynamicPricing->expects($this->once())
            ->method('calculateDynamicPrice')
            ->willReturn([
                'final_price' => 15.0,
                'dynamic_multiplier' => 1.5,
                'explanation' => 'high demand',
            ]);

        $policy = $this->makePolicy([
            'id' => 11,
            'payout_model' => 'dynamic_ai',
            'dynamic_pricing_enabled' => true,
            'base_fare' => 5,
        ]);

        $service = $this->getMockBuilder(UrbanGoodzDriverPricingService::class)
            ->setConstructorArgs([$dynamicPricing])
            ->onlyMethods(['resolvePolicy'])
            ->getMock();

        $service->method('resolvePolicy')->willReturn($policy);

        $result = $service->calculatePayout('logistics_loads', ['base_amount' => 10]);

        $this->assertSame(15.0, $result['payout']);
        $this->assertSame('dynamic_ai', $result['payout_model']);
        $this->assertSame(11, $result['policy_id']);
    }

    public function test_calculate_payout_enforces_minimum_margin(): void
    {
        $dynamicPricing = $this->createMock(DynamicPricingService::class);
        $policy = $this->makePolicy([
            'id' => 12,
            'payout_model' => 'fixed_payout',
            'fixed_amount' => 30,
            'minimum_margin' => 25,
        ]);

        $service = $this->getMockBuilder(UrbanGoodzDriverPricingService::class)
            ->setConstructorArgs([$dynamicPricing])
            ->onlyMethods(['resolvePolicy'])
            ->getMock();

        $service->method('resolvePolicy')->willReturn($policy);

        $result = $service->calculatePayout('marketplace_delivery', ['revenue' => 20]);

        $this->assertSame(15.0, $result['payout']);
        $this->assertArrayHasKey('minimum_margin_applied', $result['details']);
    }
}
