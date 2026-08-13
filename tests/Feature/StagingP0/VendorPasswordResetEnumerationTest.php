<?php

namespace Tests\Feature\StagingP0;

use App\Http\Middleware\ActivationCheckMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\StagingP0\Concerns\CreatesP0Fixtures;
use Tests\TestCase;

/**
 * P0: vendor account recovery must not disclose which email addresses own a
 * vendor account.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) plus CreatesP0Fixtures so
 * the deterministic fixture rows are (re)created every run rather than
 * depending on one hand-curated external database.
 */
class VendorPasswordResetEnumerationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesP0Fixtures;

    private const KNOWN_EMAIL   = 'staging.vendor.approved@fixture.invalid';
    private const UNKNOWN_EMAIL = 'staging.definitely.not.a.vendor@fixture.invalid';

    private const FORGOT = '/api/v1/auth/vendor/forgot-password';
    private const VERIFY = '/api/v1/auth/vendor/verify-token';
    private const RESET  = '/api/v1/auth/vendor/reset-password';

    protected function setUp(): void
    {
        parent::setUp();

        // Activation licensing and the 5/min throttle are orthogonal to the
        // enumeration property under test; the throttle is asserted
        // separately in test_rate_limiting_is_still_declared_on_the_routes().
        $this->withoutMiddleware([ActivationCheckMiddleware::class, ThrottleRequests::class]);
        $this->ensureP0Fixtures();

        $this->assertDatabaseHas('vendors', ['email' => self::KNOWN_EMAIL]);
        $this->assertDatabaseMissing('vendors', ['email' => self::UNKNOWN_EMAIL]);
    }

    public function test_forgot_password_is_indistinguishable_for_known_and_unknown_email(): void
    {
        $known = $this->postJson(self::FORGOT, ['email' => self::KNOWN_EMAIL]);
        $unknown = $this->postJson(self::FORGOT, ['email' => self::UNKNOWN_EMAIL]);

        $known->assertStatus(200);
        $unknown->assertStatus(200);

        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'Reset-request response body differs between a registered and an unregistered email.'
        );
    }

    public function test_forgot_password_issues_no_token_for_an_unknown_account(): void
    {
        $this->postJson(self::FORGOT, ['email' => self::UNKNOWN_EMAIL])->assertStatus(200);

        $this->assertSame(
            0,
            DB::table('password_resets')->where('email', self::UNKNOWN_EMAIL)->count(),
            'A password reset row was created for an address that owns no vendor account.'
        );
    }

    public function test_forgot_password_issues_exactly_one_vendor_scoped_token_for_a_real_account(): void
    {
        DB::table('password_resets')->where('email', self::KNOWN_EMAIL)->delete();

        $this->postJson(self::FORGOT, ['email' => self::KNOWN_EMAIL])->assertStatus(200);
        // Repeat: the token must rotate in place, not accumulate rows.
        $this->postJson(self::FORGOT, ['email' => self::KNOWN_EMAIL])->assertStatus(200);

        $rows = DB::table('password_resets')->where('email', self::KNOWN_EMAIL)->get();

        $this->assertCount(1, $rows, 'Repeated reset requests accumulated password_resets rows.');
        $this->assertSame('vendor', $rows->first()->created_by, 'Reset token was not scoped to the vendor role.');
    }

    public function test_verify_token_is_indistinguishable_for_known_and_unknown_email(): void
    {
        DB::table('password_resets')->where('email', self::KNOWN_EMAIL)->delete();
        $this->postJson(self::FORGOT, ['email' => self::KNOWN_EMAIL])->assertStatus(200);

        $known = $this->postJson(self::VERIFY, [
            'email' => self::KNOWN_EMAIL, 'reset_token' => '000000',
        ]);
        $unknown = $this->postJson(self::VERIFY, [
            'email' => self::UNKNOWN_EMAIL, 'reset_token' => '000000',
        ]);

        $this->assertSame(
            $known->status(),
            $unknown->status(),
            'verify-token status code reveals whether the email owns a vendor account.'
        );
        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'verify-token body reveals whether the email owns a vendor account.'
        );
    }

    public function test_verify_token_creates_no_record_for_an_unknown_email(): void
    {
        $this->postJson(self::VERIFY, [
            'email' => self::UNKNOWN_EMAIL, 'reset_token' => '000000',
        ]);

        $this->assertSame(
            0,
            DB::table('password_resets')->where('email', self::UNKNOWN_EMAIL)->count(),
            'A failed OTP attempt created a password_resets row for an unknown address.'
        );
    }

    public function test_reset_password_is_indistinguishable_for_known_and_unknown_email(): void
    {
        $payload = [
            'reset_token'      => '000000',
            'password'         => 'Str0ng!Passw0rd#'.bin2hex(random_bytes(4)),
        ];
        $payload['confirm_password'] = $payload['password'];

        $known = $this->putJson(self::RESET, $payload + ['email' => self::KNOWN_EMAIL]);
        $unknown = $this->putJson(self::RESET, $payload + ['email' => self::UNKNOWN_EMAIL]);

        $this->assertSame(
            $known->status(),
            $unknown->status(),
            'reset-password status code reveals whether the email owns a vendor account.'
        );
        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'reset-password body reveals whether the email owns a vendor account.'
        );
    }

    /**
     * password_resets is shared by the customer, delivery-man and vendor
     * flows. A token issued to a non-vendor account must not reset a vendor
     * password that merely shares the address.
     */
    public function test_a_non_vendor_token_cannot_reset_a_vendor_password(): void
    {
        $before = DB::table('vendors')->where('email', self::KNOWN_EMAIL)->value('password');

        DB::table('password_resets')->where('email', self::KNOWN_EMAIL)->delete();
        DB::table('password_resets')->insert([
            'email'      => self::KNOWN_EMAIL,
            'token'      => '424242',
            'created_at' => now(),
            'created_by' => 'user', // issued by the customer flow
        ]);

        $password = 'Str0ng!Passw0rd#'.bin2hex(random_bytes(4));

        $this->putJson(self::RESET, [
            'email'            => self::KNOWN_EMAIL,
            'reset_token'      => '424242',
            'password'         => $password,
            'confirm_password' => $password,
        ])->assertStatus(400);

        $this->assertSame(
            $before,
            DB::table('vendors')->where('email', self::KNOWN_EMAIL)->value('password'),
            'A customer-issued reset token changed a vendor password.'
        );
    }

    public function test_rate_limiting_is_still_declared_on_the_routes(): void
    {
        foreach ([self::FORGOT, self::VERIFY, self::RESET] as $uri) {
            $route = collect(Route::getRoutes())->first(
                fn ($r) => '/'.ltrim($r->uri(), '/') === $uri
            );

            $this->assertNotNull($route, "Route {$uri} is missing.");
            $this->assertContains(
                'throttle:5,1',
                $route->gatherMiddleware(),
                "Rate limiting was dropped from {$uri}."
            );
        }
    }
}
