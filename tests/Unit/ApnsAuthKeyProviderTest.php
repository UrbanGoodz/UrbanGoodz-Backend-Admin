<?php

namespace Tests\Unit;

use App\Services\Notifications\ApnsAuthKeyProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * APNs token auth (.p8) replaces the push certificates that expired 2021-12-18.
 *
 * No Apple credential exists in this repository or in CI. The key used here is
 * generated locally at runtime, exists only in a temp file for the duration of
 * the test, and has no relationship to any Apple Developer account.
 */
class ApnsAuthKeyProviderTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container;
        $container->instance('config', new ConfigRepository([
            'apns' => [
                'key_id' => null,
                'team_id' => null,
                'bundle_id' => null,
                'additional_bundle_ids' => [],
                'auth_key_path' => null,
                'auth_key_content' => null,
                'environment' => 'production',
                'endpoints' => [
                    'production' => 'https://api.push.apple.com/3/device/',
                    'sandbox' => 'https://api.sandbox.push.apple.com/3/device/',
                ],
                'token_ttl_seconds' => 3000,
                'direct_send_enabled' => false,
            ],
        ]));
        $container->instance('cache', new CacheRepository(new ArrayStore));
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ug_apns_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /** Locally generated throwaway P-256 key - never an Apple credential. */
    private function generateLocalEcPrivateKeyPem(): string
    {
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];
        $bundledWindowsConfig = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'
            .DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
        if (is_file($bundledWindowsConfig)) {
            $options['config'] = $bundledWindowsConfig;
        }

        $resource = openssl_pkey_new($options);

        $this->assertNotFalse($resource, 'OpenSSL EC key generation is required for this test.');

        $pem = '';
        $this->assertTrue(openssl_pkey_export($resource, $pem, null, $options));

        return $pem;
    }

    private function configureWithLocalKey(string $keyId = 'TESTKEYID1'): string
    {
        Cache::flush();
        $path = $this->writeTempFile($this->generateLocalEcPrivateKeyPem());

        config()->set('apns.key_id', $keyId);
        config()->set('apns.team_id', 'TESTTEAM01');
        config()->set('apns.bundle_id', 'com.urbangoodz.test.customer');
        config()->set('apns.additional_bundle_ids', ['com.urbangoodz.test.driver']);
        config()->set('apns.auth_key_path', $path);
        config()->set('apns.auth_key_content', null);
        config()->set('apns.environment', 'production');

        return $path;
    }

    public function test_unconfigured_state_reports_every_missing_piece_without_secrets(): void
    {
        config()->set('apns.key_id', null);
        config()->set('apns.team_id', null);
        config()->set('apns.bundle_id', null);
        config()->set('apns.auth_key_path', null);
        config()->set('apns.auth_key_content', null);

        $status = (new ApnsAuthKeyProvider)->status();

        $this->assertFalse($status['configured']);
        $this->assertSame(ApnsAuthKeyProvider::MODE_UNCONFIGURED, $status['mode']);
        $this->assertSame('p8_auth_key', $status['auth_type']);
        $this->assertSame([
            'apns_key_id_missing',
            'apns_team_id_missing',
            'apns_bundle_id_missing',
            'apns_auth_key_unreadable',
        ], $status['problems']);
    }

    public function test_configured_status_exposes_identifiers_but_never_the_key(): void
    {
        $path = $this->configureWithLocalKey();
        $keyMaterial = (string) file_get_contents($path);

        $status = (new ApnsAuthKeyProvider)->status();

        $this->assertTrue($status['configured']);
        $this->assertSame(ApnsAuthKeyProvider::MODE_AUTH_KEY, $status['mode']);
        $this->assertTrue($status['key_id_present']);
        $this->assertTrue($status['team_id_present']);
        $this->assertTrue($status['auth_key_readable']);
        $this->assertSame('production', $status['environment']);
        $this->assertSame('https://api.push.apple.com/3/device/', $status['endpoint']);
        $this->assertSame([], $status['problems']);
        $this->assertStringNotContainsString($keyMaterial, (string) json_encode($status));
    }

    public function test_sandbox_environment_selects_the_sandbox_endpoint(): void
    {
        $this->configureWithLocalKey('SANDBOXKEY');
        config()->set('apns.environment', 'sandbox');

        $this->assertSame(
            'https://api.sandbox.push.apple.com/3/device/',
            (new ApnsAuthKeyProvider)->endpoint()
        );
    }

    public function test_provider_token_is_a_valid_es256_jwt_with_kid_and_iss(): void
    {
        $this->configureWithLocalKey('JWTKEYID01');

        $token = (new ApnsAuthKeyProvider)->providerToken();
        $parts = explode('.', $token);

        $this->assertCount(3, $parts, 'A provider token must be header.claims.signature.');

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $claims = json_decode($this->base64UrlDecode($parts[1]), true);

        $this->assertSame('ES256', $header['alg']);
        $this->assertSame('JWTKEYID01', $header['kid']);
        $this->assertSame('TESTTEAM01', $claims['iss']);
        $this->assertIsInt($claims['iat']);
        $this->assertEqualsWithDelta(time(), $claims['iat'], 60);

        // ES256 requires the raw R||S form: 32 bytes each, not a DER sequence.
        $signature = $this->base64UrlDecode($parts[2]);
        $this->assertSame(64, strlen($signature));
        $this->assertStringNotContainsString('=', $parts[2]);
        $this->assertStringNotContainsString('+', $parts[2]);
        $this->assertStringNotContainsString('/', $parts[2]);
    }

    public function test_provider_token_is_cached_within_its_ttl(): void
    {
        $this->configureWithLocalKey('CACHEKEY01');
        $provider = new ApnsAuthKeyProvider;

        $this->assertSame($provider->providerToken(), $provider->providerToken());
    }

    public function test_a_certificate_is_rejected_instead_of_silently_accepted(): void
    {
        Cache::flush();
        $path = $this->writeTempFile("-----BEGIN CERTIFICATE-----\nnot-a-p8\n-----END CERTIFICATE-----\n");

        config()->set('apns.key_id', 'CERTKEYID1');
        config()->set('apns.team_id', 'TESTTEAM01');
        config()->set('apns.bundle_id', 'com.urbangoodz.test.customer');
        config()->set('apns.auth_key_content', null);
        config()->set('apns.auth_key_path', $path);

        $provider = new ApnsAuthKeyProvider;

        $this->assertFalse($provider->isConfigured());
        $this->assertContains('apns_auth_key_unreadable', $provider->configurationProblems());

        $this->expectException(RuntimeException::class);
        $provider->providerToken();
    }

    public function test_request_headers_only_allow_configured_bundle_ids(): void
    {
        $this->configureWithLocalKey('TOPICKEY01');
        $provider = new ApnsAuthKeyProvider;

        $headers = $provider->requestHeaders('com.urbangoodz.test.driver');

        $this->assertSame('com.urbangoodz.test.driver', $headers['apns-topic']);
        $this->assertStringStartsWith('bearer ', $headers['authorization']);
        $this->assertSame('alert', $headers['apns-push-type']);

        $this->assertTrue($provider->isAllowedTopic('com.urbangoodz.test.customer'));
        $this->assertFalse($provider->isAllowedTopic('com.someone.else'));

        $this->expectException(RuntimeException::class);
        $provider->requestHeaders('com.someone.else');
    }

    public function test_direct_send_is_off_by_default_because_ios_push_routes_through_fcm(): void
    {
        $this->assertFalse((bool) config('apns.direct_send_enabled'));
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
