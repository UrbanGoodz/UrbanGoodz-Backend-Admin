<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guard: no sender may reach the decommissioned legacy FCM
 * endpoint, and every sender must route its precedence decision through
 * FirebaseCredentialResolver so v1 stays the authority.
 *
 * Google shut the legacy HTTP API down on 2024-06-20.
 */
class FcmLegacyServerKeySourceTest extends TestCase
{
    private const LEGACY_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /** @return array<int, string> */
    private function senderFiles(): array
    {
        return [
            __DIR__.'/../../app/CentralLogics/Helpers.php',
            __DIR__.'/../../app/Traits/NotificationTrait.php',
            __DIR__.'/../../app/Library/Notification.php',
        ];
    }

    public function test_no_sender_posts_to_the_legacy_fcm_endpoint(): void
    {
        foreach ($this->senderFiles() as $file) {
            $this->assertStringNotContainsString(
                self::LEGACY_ENDPOINT,
                (string) file_get_contents($file),
                basename($file).' must never call the decommissioned legacy FCM endpoint.'
            );
        }
    }

    public function test_every_sender_routes_precedence_through_the_resolver(): void
    {
        foreach ($this->senderFiles() as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringContainsString(
                'FirebaseCredentialResolver::decide(',
                $source,
                basename($file).' must ask the resolver which credential mode is authoritative.'
            );
            $this->assertStringContainsString(
                "\$decision['can_send']",
                $source,
                basename($file).' must refuse to send when the resolver says it cannot.'
            );
            $this->assertStringContainsString(
                "\$decision['endpoint']",
                $source,
                basename($file).' must post to the resolver-supplied v1 endpoint.'
            );
        }
    }

    public function test_no_sender_hand_builds_the_v1_url_from_a_raw_setting(): void
    {
        foreach ($this->senderFiles() as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                "'https://fcm.googleapis.com/v1/projects/'.\$key['project_id']",
                $source
            );
            $this->assertStringNotContainsString(
                "'https://fcm.googleapis.com/v1/projects/' . \$key['project_id']",
                $source
            );
        }
    }

    /**
     * The legacy server-key admin field is commented out. If it is ever
     * re-enabled this test fails, forcing an explicit precedence decision
     * instead of a silent second credential.
     */
    public function test_legacy_server_key_admin_field_stays_disabled(): void
    {
        $blade = (string) file_get_contents(
            __DIR__.'/../../resources/views/admin-views/business-settings/fcm-config.blade.php'
        );

        // The field survives only inside a blade comment. Strip comments and it
        // must be gone; if someone un-comments it, this fails.
        $active = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

        $this->assertStringContainsString(
            'push_notification_key',
            $blade,
            'Expected the retired server-key field to still exist as a comment.'
        );
        $this->assertStringNotContainsString('name="push_notification_key"', $active);
        $this->assertStringNotContainsString('push_notification_key', $active);
    }
}
