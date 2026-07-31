<?php

namespace Tests\Unit;

use App\Services\UrbanGoodzNotificationService;
use PHPUnit\Framework\TestCase;

class NotificationChannelPolicyTest extends TestCase
{
    public function test_only_push_channels_require_firebase_delivery(): void
    {
        $this->assertTrue(UrbanGoodzNotificationService::isPushChannel('push'));
        $this->assertTrue(UrbanGoodzNotificationService::isPushChannel('FIREBASE_PUSH'));

        foreach (['in_app', 'database', 'websocket', 'admin_alert', 'email', 'sms', 'webhook', null] as $channel) {
            $this->assertFalse(
                UrbanGoodzNotificationService::isPushChannel($channel),
                (string) $channel.' must not depend on an FCM token.'
            );
        }
    }

    public function test_persistent_in_app_channels_are_classified_separately(): void
    {
        foreach (['in_app', 'database', 'websocket', 'admin_alert'] as $channel) {
            $this->assertTrue(UrbanGoodzNotificationService::isInAppChannel($channel));
        }

        foreach (['push', 'firebase_push', 'email', 'sms', 'webhook', null] as $channel) {
            $this->assertFalse(UrbanGoodzNotificationService::isInAppChannel($channel));
        }
    }

    public function test_delivery_source_marks_in_app_rows_delivered_before_token_lookup(): void
    {
        $source = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodzNotificationService.php'
        );

        $inAppBranch = strpos($source, 'if (self::isInAppChannel($notification->channel))');
        $tokenLookup = strpos($source, '$token = $this->resolveFirebaseToken(');

        $this->assertNotFalse($inAppBranch);
        $this->assertNotFalse($tokenLookup);
        $this->assertLessThan(
            $tokenLookup,
            $inAppBranch,
            'In-app delivery must complete before any FCM token lookup.'
        );
        $this->assertStringContainsString(
            "\$notification->update(['status' => 'delivered']);",
            $source
        );
    }
}
