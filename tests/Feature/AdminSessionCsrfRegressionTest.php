<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P0 admin HTTP 419 regression coverage.
 *
 * CSRF protection must stay enabled and effective; the production 419 comes
 * from the app not recognising that it sits behind a TLS-terminating proxy
 * (see bootstrap/app.php), not from CSRF being too strict.
 */
class AdminSessionCsrfRegressionTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        \Illuminate\Http\Middleware\TrustProxies::flushState();

        parent::tearDown();
    }

    private function probeRoute(): void
    {
        Route::get('/__proxy-probe', fn () => response()->json([
            'secure' => request()->isSecure(),
            'scheme' => request()->getScheme(),
            'ip' => request()->ip(),
            'login_url' => route('login', ['tab' => 'admin']),
        ]));
    }

    /**
     * Laravel's VerifyCsrfToken skips verification whenever the app env is
     * "testing". Flip it for the request so the real check runs.
     */
    private function enforceCsrfLikeProduction(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_csrf_protection_is_still_enforced_on_admin_login_submit(): void
    {
        $this->enforceCsrfLikeProduction();
        $this->post('/login_submit', [
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ])->assertStatus(419);
    }

    public function test_csrf_protection_is_still_enforced_with_a_wrong_token(): void
    {
        $this->enforceCsrfLikeProduction();
        $this->withSession(['_token' => 'real-token'])
            ->post('/login_submit', ['_token' => 'forged-token'])
            ->assertStatus(419);
    }

    public function test_default_configuration_trusts_no_proxy(): void
    {
        $this->probeRoute();

        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJsonPath('secure', false);
    }

    public function test_trusted_proxy_makes_https_visible_to_the_app(): void
    {
        putenv('TRUSTED_PROXIES=*');
        $_ENV['TRUSTED_PROXIES'] = '*';
        $_SERVER['TRUSTED_PROXIES'] = '*';
        $this->refreshApplication();
        $this->probeRoute();

        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.9',
        ])->get('/__proxy-probe')
            ->assertOk()
            ->assertJsonPath('secure', true)
            ->assertJsonPath('scheme', 'https')
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_trusted_proxy_list_is_honoured_and_untrusted_sources_are_ignored(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.0/8');
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8';
        $_SERVER['TRUSTED_PROXIES'] = '10.0.0.0/8';
        $this->refreshApplication();
        $this->probeRoute();

        // Request arrives from 127.0.0.1, which is NOT in 10.0.0.0/8, so a
        // spoofed forwarded header must not be believed.
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJsonPath('secure', false);

        // A request that does come from inside the trusted range is honoured.
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/__proxy-probe')
            ->assertOk()
            ->assertJsonPath('secure', true);
    }
}
