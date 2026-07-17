<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\DynamicPricingService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    private DynamicPricingService $service;
    private UrbanGoodzAIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('urban_goodz.ai_model', 'gpt-4o');
        Config::set('openai.api_key', 'test-key');
        
        $this->aiService = $this->createMock(UrbanGoodzAIService::class);
        $this->service = new DynamicPricingService($this->aiService);
    }

    public function test_calculate_dynamic_price_uses_ai_when_available(): void
    {
        $params = [
            'base_price' => 100.00,
            'demand_level' => 'high',
            'category' => 'food',
            'time_of_day' => 'lunch',
        ];

        $aiResponse = json_encode([
            'dynamic_multiplier' => 1.2,
            'factors' => [
                ['name' => 'demand_level', 'impact' => 'positive', 'weight' => 0.6],
                ['name' => 'time_of_day', 'impact' => 'positive', 'weight' => 0.3],
            ],
            'recommendation' => 'Increase price for high lunch demand',
            'explanation' => 'High demand during lunch hours justifies a 1.2x multiplier',
            'confidence' => 0.85,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->calculateDynamicPrice($params);

        $this->assertEquals(100.00, $result['base_price']);
        $this->assertEquals(1.2, $result['dynamic_multiplier']);
        $this->assertEquals(120.00, $result['final_price']);
        $this->assertEquals('ai_pricing', $result['source']);
        $this->assertEquals(0.85, $result['confidence']);
        $this->assertCount(2, $result['factors']);
    }

    public function test_calculate_dynamic_price_clamps_multiplier_to_bounds(): void
    {
        $params = [
            'base_price' => 100.00,
            'demand_level' => 'peak',
        ];

        $aiResponse = json_encode([
            'dynamic_multiplier' => 5.0,
            'factors' => [],
            'recommendation' => 'Test',
            'explanation' => 'Test',
            'confidence' => 0.9,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->calculateDynamicPrice($params);

        $this->assertEquals(3.0, $result['dynamic_multiplier']);
        $this->assertEquals(300.00, $result['final_price']);
    }

    public function test_calculate_dynamic_price_fallback_when_ai_fails(): void
    {
        $params = [
            'base_price' => 100.00,
            'demand_level' => 'low',
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->calculateDynamicPrice($params);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(0.95, $result['dynamic_multiplier']);
        $this->assertEquals(95.00, $result['final_price']);
        $this->assertEquals(0.3, $result['confidence']);
    }

    public function test_calculate_dynamic_price_fallback_when_ai_returns_invalid_json(): void
    {
        $params = [
            'base_price' => 100.00,
            'demand_level' => 'high',
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn('invalid json response');

        $result = $this->service->calculateDynamicPrice($params);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(1.15, $result['dynamic_multiplier']);
        $this->assertEquals(115.00, $result['final_price']);
    }

    public function test_fallback_pricing_by_demand_level(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('fallbackPricing');
        $method->setAccessible(true);

        $basePrice = 100.00;

        $resultLow = $method->invoke($this->service, ['base_price' => $basePrice, 'demand_level' => 'low']);
        $this->assertEquals(0.95, $resultLow['dynamic_multiplier']);
        $this->assertEquals(95.00, $resultLow['final_price']);

        $resultMedium = $method->invoke($this->service, ['base_price' => $basePrice, 'demand_level' => 'medium']);
        $this->assertEquals(1.0, $resultMedium['dynamic_multiplier']);
        $this->assertEquals(100.00, $resultMedium['final_price']);

        $resultHigh = $method->invoke($this->service, ['base_price' => $basePrice, 'demand_level' => 'high']);
        $this->assertEquals(1.15, $resultHigh['dynamic_multiplier']);
        $this->assertEquals(115.00, $resultHigh['final_price']);

        $resultPeak = $method->invoke($this->service, ['base_price' => $basePrice, 'demand_level' => 'peak']);
        $this->assertEquals(1.25, $resultPeak['dynamic_multiplier']);
        $this->assertEquals(125.00, $resultPeak['final_price']);
    }

    public function test_simulate_price_change_uses_ai_when_available(): void
    {
        $params = [
            'current_price' => 100.00,
            'proposed_price' => 120.00,
            'category' => 'food',
        ];

        $aiResponse = json_encode([
            'projected_demand_change_percent' => -10.5,
            'projected_revenue_change_percent' => 8.2,
            'risk_level' => 'medium',
            'factors' => ['price elasticity', 'competitor pricing'],
            'recommendation' => 'Proceed with caution',
            'confidence' => 0.75,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->simulatePriceChange($params);

        $this->assertEquals(-10.5, $result['projected_demand_change_percent']);
        $this->assertEquals(8.2, $result['projected_revenue_change_percent']);
        $this->assertEquals('medium', $result['risk_level']);
        $this->assertEquals('ai_simulation', $result['source']);
        $this->assertEquals(0.75, $result['confidence']);
    }

    public function test_simulate_price_change_fallback_when_ai_fails(): void
    {
        $params = [
            'current_price' => 100.00,
            'proposed_price' => 120.00,
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->simulatePriceChange($params);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(0, $result['projected_demand_change_percent']);
        $this->assertEquals(0, $result['projected_revenue_change_percent']);
        $this->assertEquals('medium', $result['risk_level']);
        $this->assertEquals('Simulation unavailable', $result['recommendation']);
        $this->assertEquals(0, $result['confidence']);
    }
}