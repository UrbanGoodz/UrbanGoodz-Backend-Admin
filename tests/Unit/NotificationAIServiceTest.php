<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\NotificationAIService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NotificationAIServiceTest extends TestCase
{
    private NotificationAIService $service;
    private UrbanGoodzAIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('urban_goodz.ai_model', 'gpt-4o');
        Config::set('openai.api_key', 'test-key');
        
        $this->aiService = $this->createMock(UrbanGoodzAIService::class);
        $this->service = new NotificationAIService($this->aiService);
    }

    public function test_generate_smart_notification_uses_ai_when_available(): void
    {
        $context = [
            'recipient_type' => 'customer',
            'event_type' => 'order_delivered',
            'title' => 'Test Delivery',
            'body' => 'Your package has been delivered.',
        ];

        $aiResponse = json_encode([
            'title' => 'Delivered!',
            'body' => 'Your Urban Goodz order has arrived at your door.',
            'priority' => 'normal',
            'channels' => ['push', 'sms'],
            'personalization' => 'friendly',
            'sentiment' => 'positive',
            'action_suggestion' => 'Open app to rate your delivery',
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->generateSmartNotification($context);

        $this->assertEquals('Delivered!', $result['title']);
        $this->assertEquals('Your Urban Goodz order has arrived at your door.', $result['body']);
        $this->assertEquals('normal', $result['priority']);
        $this->assertContains('push', $result['channels']);
        $this->assertEquals('ai_personalized', $result['source']);
    }

    public function test_generate_smart_notification_fallback_when_ai_fails(): void
    {
        $context = [
            'recipient_type' => 'customer',
            'event_type' => 'order_delivered',
            'title' => 'Test Delivery',
            'body' => 'Your package has been delivered.',
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->generateSmartNotification($context);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals('Order Delivered', $result['title']);
    }

    public function test_generate_digest_uses_ai_when_available(): void
    {
        $notifications = [
            ['id' => 1, 'title' => 'Order Received', 'body' => 'Order #1001 was placed.'],
            ['id' => 2, 'title' => 'Driver Arrived', 'body' => 'Driver is at store.'],
        ];

        $recipientContext = ['role' => 'vendor'];

        $aiResponse = json_encode([
            'digest_title' => 'Daily Activity Briefing',
            'digest_body' => 'You have 2 pending orders.',
            'highlights' => ['Order #1001 is pending preparation'],
            'action_items' => ['Accept Order #1001'],
            'priority_notifications' => [1],
            'can_safely_batch' => true,
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->generateDigest($notifications, $recipientContext);

        $this->assertEquals('Daily Activity Briefing', $result['digest_title']);
        $this->assertEquals('You have 2 pending orders.', $result['digest_body']);
        $this->assertEquals('ai_digest', $result['source']);
    }

    public function test_generate_digest_fallback_when_ai_fails(): void
    {
        $notifications = [
            ['id' => 1, 'title' => 'Order Received'],
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->generateDigest($notifications, ['role' => 'vendor']);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEquals('Notifications', $result['digest_title']);
    }

    public function test_prioritize_notifications_uses_ai_when_available(): void
    {
        $notifications = [
            ['id' => 1, 'title' => 'Order Cancelled'],
        ];

        $aiResponse = json_encode([
            'prioritized' => [
                ['id' => 1, 'priority' => 'urgent', 'reason' => 'Cancellation needs instant dispatcher alert'],
            ],
        ]);

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willReturn($aiResponse);

        $result = $this->service->prioritizeNotifications($notifications);

        $this->assertEquals('ai_prioritization', $result['source']);
        $this->assertCount(1, $result['prioritized']);
        $this->assertEquals('urgent', $result['prioritized'][0]['priority']);
    }

    public function test_prioritize_notifications_fallback_when_ai_fails(): void
    {
        $notifications = [
            ['id' => 1, 'title' => 'Order Cancelled'],
        ];

        $this->aiService->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('AI service unavailable'));

        $result = $this->service->prioritizeNotifications($notifications);

        $this->assertEquals('fallback', $result['source']);
        $this->assertEmpty($result['prioritized']);
    }
}
