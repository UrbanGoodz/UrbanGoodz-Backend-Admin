<?php

namespace Tests\Unit;

use App\Services\Notifications\FirebaseCredentialResolver;
use PHPUnit\Framework\TestCase;

/**
 * FCM v1 must be the authority. A legacy server key must never be the path
 * that actually sends, and must never act as a fallback when v1 is configured.
 *
 * decide() is pure, so this suite needs no database and no HTTP.
 */
class FirebaseCredentialResolverTest extends TestCase
{
    /** @return array<string, string> */
    private function serviceAccount(array $overrides = []): array
    {
        return array_merge([
            'project_id' => 'urbangoodz-test-project',
            'client_email' => 'fcm-sender@urbangoodz-test-project.iam.gserviceaccount.com',
            // Not a key: a fixed placeholder string, never used to sign here.
            'private_key' => 'placeholder-not-a-real-key',
        ], $overrides);
    }

    public function test_v1_service_account_is_authoritative_when_present(): void
    {
        $decision = FirebaseCredentialResolver::decide($this->serviceAccount(), null);

        $this->assertSame(FirebaseCredentialResolver::MODE_V1, $decision['mode']);
        $this->assertTrue($decision['can_send']);
        $this->assertSame('urbangoodz-test-project', $decision['project_id']);
        $this->assertSame(
            'https://fcm.googleapis.com/v1/projects/urbangoodz-test-project/messages:send',
            $decision['endpoint']
        );
        $this->assertNull($decision['reason']);
    }

    public function test_legacy_server_key_is_ignored_when_v1_is_configured(): void
    {
        $decision = FirebaseCredentialResolver::decide(
            $this->serviceAccount(),
            'legacy-server-key-placeholder-value'
        );

        $this->assertSame(FirebaseCredentialResolver::MODE_V1, $decision['mode']);
        $this->assertTrue($decision['can_send']);
        $this->assertTrue($decision['legacy_server_key_present']);
        $this->assertTrue($decision['legacy_server_key_ignored']);
        $this->assertStringStartsWith('https://fcm.googleapis.com/v1/projects/', (string) $decision['endpoint']);
        $this->assertStringNotContainsString('/fcm/send', (string) $decision['endpoint']);
    }

    public function test_legacy_server_key_alone_cannot_send(): void
    {
        $decision = FirebaseCredentialResolver::decide(null, 'legacy-server-key-placeholder-value');

        $this->assertSame(FirebaseCredentialResolver::MODE_LEGACY_ONLY, $decision['mode']);
        $this->assertFalse($decision['can_send']);
        $this->assertNull($decision['endpoint']);
        $this->assertSame('legacy_server_key_is_not_a_send_path', $decision['reason']);
    }

    public function test_no_credentials_at_all_fails_closed(): void
    {
        $decision = FirebaseCredentialResolver::decide(null, null);

        $this->assertSame(FirebaseCredentialResolver::MODE_UNCONFIGURED, $decision['mode']);
        $this->assertFalse($decision['can_send']);
        $this->assertSame('fcm_v1_service_account_missing', $decision['reason']);
    }

    /**
     * A half-filled service account must not be treated as v1-ready: the old
     * code only checked project_id and would have attempted to sign with a
     * missing private key.
     */
    public function test_incomplete_service_account_is_not_treated_as_v1(): void
    {
        foreach (['project_id', 'client_email', 'private_key'] as $missingField) {
            $account = $this->serviceAccount([$missingField => '']);
            $decision = FirebaseCredentialResolver::decide($account, null);

            $this->assertSame(
                FirebaseCredentialResolver::MODE_UNCONFIGURED,
                $decision['mode'],
                "Service account missing {$missingField} must not be v1-ready."
            );
            $this->assertFalse($decision['can_send']);
        }
    }

    public function test_empty_string_legacy_key_counts_as_absent(): void
    {
        $decision = FirebaseCredentialResolver::decide(null, '   ');

        $this->assertSame(FirebaseCredentialResolver::MODE_UNCONFIGURED, $decision['mode']);
        $this->assertFalse($decision['legacy_server_key_present']);
    }

    public function test_decision_never_leaks_credential_values(): void
    {
        $privateKey = 'placeholder-private-key-must-not-be-returned';
        $legacyKey = 'placeholder-legacy-key-must-not-be-returned';

        $serialized = json_encode(FirebaseCredentialResolver::decide(
            $this->serviceAccount(['private_key' => $privateKey]),
            $legacyKey
        ));

        $this->assertStringNotContainsString($privateKey, (string) $serialized);
        $this->assertStringNotContainsString($legacyKey, (string) $serialized);
    }
}
