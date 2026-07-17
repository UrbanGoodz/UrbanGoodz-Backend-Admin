<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\FraudDetectionService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FraudDetectionServiceTest extends TestCase
{
    private FraudDetectionService $service;
    private UrbanGoodzAIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('urban_goodz.ai_model', 'gpt-4o');
        Config::set('openai.api_key', 'test-key');
        
        $this->aiService = $this->createMock(UrbanGoodzAIService::class);
        $this->service = new FraudDetectionService($this->aiService);
    }

    public function test_analyze_transaction_uses_ai_when_available(): void
    {
        $transactionData = [
            'amount' => 250.00,
            'customer_id' => 123,
            'payment_method' => 'credit_card',
            'customer_history' => ['avg_order' => 50, 'orders_count' => 10],
        ];

        $aiResponse = json_encode([
            'risk_score' => 35.0,
            'flags' => [
                ['type' => 'amount_anomaly', 'severity' => 'low', 'message' => 'Amount 5x customer average'],
            ],
            'recommended_action' => 'review',
            'explanation' => 'Transaction amount significantly exceeds customer average',
            'confidence' => 0.8,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->analyzeTransaction($transactionData);

        $this->assertEquals(35.0, $result['risk_score']);
        $this->assertEquals('low', $result['risk_level']);
        $this->assertCount(1, $result['flags']);
        $this->assertEquals('review', $result['recommended_action']);
        $this->assertEquals('ai_analysis', $result['source']);
        $this->assertEquals(0.8, $result['confidence']);
    }

    public function test_analyze_transaction_fallback_when_ai_fails(): void
    {
        $transactionData = [
            'amount' => 600.00,
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->analyzeTransaction($transactionData);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(25.0, $result['risk_score']);
        $this->assertEquals('low', $result['risk_level']);
        $this->assertEquals('review', $result['recommended_action']);
        $this->assertEquals(0.3, $result['confidence']);
        $this->assertCount(1, $result['flags']);
        $this->assertEquals('high_amount', $result['flags'][0]['type']);
    }

    public function test_analyze_transaction_fallback_below_threshold(): void
    {
        $transactionData = [
            'amount' => 100.00,
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->analyzeTransaction($transactionData);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(0.0, $result['risk_score']);
        $this->assertEquals('minimal', $result['risk_level']);
        $this->assertEquals('approve', $result['recommended_action']);
        $this->assertEmpty($result['flags']);
    }

    public function test_analyze_account_uses_ai_when_available(): void
    {
        $accountData = [
            'account_age_days' => 30,
            'transaction_velocity' => 50,
            'refund_frequency' => 0.3,
        ];

        $aiResponse = json_encode([
            'risk_score' => 65.0,
            'flags' => [
                ['type' => 'high_velocity', 'severity' => 'high', 'message' => 'Unusual transaction velocity'],
                ['type' => 'high_refund_rate', 'severity' => 'medium', 'message' => '30% refund rate'],
            ],
            'patterns' => ['velocity_spike', 'refund_abuse'],
            'recommended_action' => 'suspend',
            'explanation' => 'Account shows signs of fraudulent activity',
            'confidence' => 0.9,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->analyzeAccount($accountData);

        $this->assertEquals(65.0, $result['risk_score']);
        $this->assertEquals('high', $result['risk_level']);
        $this->assertCount(2, $result['flags']);
        $this->assertCount(2, $result['patterns']);
        $this->assertEquals('suspend', $result['recommended_action']);
        $this->assertEquals('ai_analysis', $result['source']);
        $this->assertEquals(0.9, $result['confidence']);
    }

    public function test_analyze_account_fallback_when_ai_fails(): void
    {
        $accountData = [
            'account_age_days' => 30,
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->analyzeAccount($accountData);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(0.0, $result['risk_score']);
        $this->assertEquals('low', $result['risk_level']);
        $this->assertEquals('review', $result['recommended_action']);
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertEmpty($result['flags']);
        $this->assertEmpty($result['patterns']);
    }

    public function test_analyze_receipt_uses_ai_when_available(): void
    {
        $receiptData = [
            'merchant' => 'Test Store',
            'amount' => 150.00,
            'items' => [['name' => 'Item 1', 'price' => 150.00]],
        ];

        $aiResponse = json_encode([
            'manipulation_score' => 20,
            'flags' => ['round_number_pattern'],
            'explanation' => 'Receipt shows round number pricing',
            'confidence' => 0.7,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->analyzeReceipt($receiptData);

        $this->assertEquals(20.0, $result['manipulation_score']);
        $this->assertCount(1, $result['flags']);
        $this->assertEquals('ai_receipt_analysis', $result['source']);
        $this->assertEquals(0.7, $result['confidence']);
    }

    public function test_analyze_receipt_fallback_when_ai_fails(): void
    {
        $receiptData = [
            'merchant' => 'Test Store',
            'amount' => 150.00,
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->analyzeReceipt($receiptData);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals(0, $result['manipulation_score']);
        $this->assertEquals('Analysis unavailable', $result['explanation']);
        $this->assertEquals(0, $result['confidence']);
        $this->assertEmpty($result['flags']);
    }

    public function test_classify_risk_levels(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('classifyRisk');
        $method->setAccessible(true);

        $this->assertEquals('minimal', $method->invoke($this->service, 10));
        $this->assertEquals('low', $method->invoke($this->service, 25));
        $this->assertEquals('medium', $method->invoke($this->service, 45));
        $this->assertEquals('high', $method->invoke($this->service, 65));
        $this->assertEquals('critical', $method->invoke($this->service, 85));
    }
}