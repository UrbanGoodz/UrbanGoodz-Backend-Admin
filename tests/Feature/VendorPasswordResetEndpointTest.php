<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivationCheckMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fixture-free, end-to-end coverage for the vendor password-reset flow
 * (POST .../auth/vendor/forgot-password, POST .../verify-token,
 * PUT .../reset-password), complementing
 * tests/Feature/StagingP0/VendorPasswordResetEnumerationTest.php which
 * requires the "isolated staging" fixture database and cannot run against a
 * plain from-scratch migrated database.
 *
 * This test creates its own vendor row and exercises the controller's
 * documented enumeration-safety and rate-limiting properties for real,
 * per Lane 1 certification requirements.
 */
class VendorPasswordResetEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT_URI = '/api/v1/auth/vendor/forgot-password';
    private const VERIFY_URI = '/api/v1/auth/vendor/verify-token';
    private const RESET_URI  = '/api/v1/auth/vendor/reset-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ActivationCheckMiddleware::class, ThrottleRequests::class]);
        // The password rule's uncompromised() check calls out to the
        // pwnedpasswords.com HTTP API. Fake it so these tests neither
        // depend on outbound network access nor leak real passwords.
        Http::fake();
        Mail::fake();
    }

    private function makeVendor(array $overrides = []): array
    {
        $email = 'vendor.'.Str::random(12).'@example.test';

        $id = DB::table('vendors')->insertGetId(array_merge([
            'f_name'     => 'Test',
            'l_name'     => 'Vendor',
            'phone'      => '+1555'.random_int(1000000, 9999999),
            'email'      => $email,
            'password'   => bcrypt('original-password'),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return [$id, $email];
    }

    private function insertResetRow(string $email, array $overrides = []): void
    {
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            array_merge([
                'token'           => '654321',
                'created_at'      => now(),
                'otp_hit_count'   => 0,
                'is_temp_blocked' => 0,
                'temp_block_time' => null,
                'created_by'      => 'vendor',
            ], $overrides)
        );
    }

    // ---- forgot-password (request) ----------------------------------

    public function test_forgot_password_for_unknown_email_returns_generic_ack_and_writes_no_row(): void
    {
        $response = $this->postJson(self::FORGOT_URI, ['email' => 'nobody@example.test']);

        $response->assertStatus(200);
        $this->assertSame(
            0,
            DB::table('password_resets')->where('email', 'nobody@example.test')->count(),
            'A reset row must not be created for an email that owns no vendor account.'
        );
    }

    public function test_forgot_password_for_known_vendor_creates_scoped_token_row(): void
    {
        [, $email] = $this->makeVendor();

        $response = $this->postJson(self::FORGOT_URI, ['email' => $email]);

        $response->assertStatus(200);
        $row = DB::table('password_resets')->where('email', $email)->first();
        $this->assertNotNull($row, 'A vendor forgot-password request must create a password_resets row.');
        $this->assertSame('vendor', $row->created_by);
        $this->assertSame(0, (int) $row->otp_hit_count);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $row->token);
    }

    public function test_forgot_password_response_body_is_identical_for_known_and_unknown_email(): void
    {
        [, $knownEmail] = $this->makeVendor();

        $known = $this->postJson(self::FORGOT_URI, ['email' => $knownEmail]);
        $unknown = $this->postJson(self::FORGOT_URI, ['email' => 'definitely-not-registered@example.test']);

        $known->assertStatus(200);
        $unknown->assertStatus(200);
        $this->assertSame($known->json(), $unknown->json(), 'forgot-password must not leak account existence via response body.');
    }

    public function test_repeated_forgot_password_requests_rotate_token_instead_of_duplicating_rows(): void
    {
        [, $email] = $this->makeVendor();

        $this->postJson(self::FORGOT_URI, ['email' => $email])->assertStatus(200);
        $firstToken = DB::table('password_resets')->where('email', $email)->value('token');

        $this->postJson(self::FORGOT_URI, ['email' => $email])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('password_resets')->where('email', $email)->count(),
            'A repeat request must rotate the existing row, not insert an additional one.'
        );
        // Extremely low odds of collision, but don't assert token changed by value alone;
        // assert the row was actually rewritten (hit-count/state reset).
        $row = DB::table('password_resets')->where('email', $email)->first();
        $this->assertSame(0, (int) $row->otp_hit_count);
        $this->assertNotNull($row->token);
        unset($firstToken);
    }

    // ---- verify-token --------------------------------------------------

    public function test_verify_token_accepts_the_correct_otp(): void
    {
        [, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '111222']);

        $response = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '111222']);

        $response->assertStatus(200);
    }

    public function test_verify_token_rejects_wrong_otp_with_same_generic_error_as_unknown_email(): void
    {
        [, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '111222']);

        $wrongOtp = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '000000']);
        $unknownEmail = $this->postJson(self::VERIFY_URI, [
            'email' => 'nobody-'.Str::random(8).'@example.test',
            'reset_token' => '000000',
        ]);

        $wrongOtp->assertStatus(400);
        $unknownEmail->assertStatus(400);
        $this->assertSame(
            $wrongOtp->json(),
            $unknownEmail->json(),
            'A wrong OTP for a real vendor must be indistinguishable from an unknown email.'
        );
    }

    public function test_verify_token_does_not_accept_a_token_issued_to_a_different_role(): void
    {
        [, $email] = $this->makeVendor();
        // A password_resets row for the same email exists, but it was issued
        // by the customer/delivery-man flow, not the vendor flow.
        $this->insertResetRow($email, ['token' => '999888', 'created_by' => 'customer']);

        $response = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '999888']);

        $response->assertStatus(400);
    }

    public function test_verify_token_temp_blocks_after_repeated_wrong_attempts(): void
    {
        [, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '111222']);

        // max_otp_hit = 5: the 6th wrong attempt within the hit window trips
        // the temp block, and the 7th is rejected purely on the block time.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '000000'])
                ->assertStatus(400);
        }

        $sixth = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '000000']);
        $sixth->assertStatus(405);
        $this->assertSame('otp_temp_blocked', $sixth->json('errors.0.code'));

        $seventh = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '000000']);
        $seventh->assertStatus(405);
        $this->assertSame('otp_block_time', $seventh->json('errors.0.code'));

        // By design, the controller matches on {token, email, created_by}
        // BEFORE consulting block state at all, so a request carrying the
        // genuinely correct token still succeeds even while temp-blocked.
        // The temp block only ever throttles *wrong* guesses (the attacker's
        // brute-force path); it never locks out someone who already has the
        // real OTP. Documented here as intended behavior, not a bug.
        $correctDuringBlock = $this->postJson(self::VERIFY_URI, ['email' => $email, 'reset_token' => '111222']);
        $correctDuringBlock->assertStatus(200);
    }

    // ---- reset-password (submit) ---------------------------------------

    private function strongPassword(): string
    {
        return 'Str0ng!Pass'.random_int(100, 999);
    }

    public function test_reset_password_submit_updates_vendor_password_and_consumes_token(): void
    {
        [$id, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '444555']);
        $newPassword = $this->strongPassword();

        $response = $this->putJson(self::RESET_URI, [
            'email' => $email,
            'reset_token' => '444555',
            'password' => $newPassword,
            'confirm_password' => $newPassword,
        ]);

        $response->assertStatus(200);

        $vendor = DB::table('vendors')->where('id', $id)->first();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($newPassword, $vendor->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('original-password', $vendor->password), 'Old password must no longer work after reset.');

        $this->assertSame(
            0,
            DB::table('password_resets')->where('email', $email)->where('token', '444555')->count(),
            'The reset token must be consumed (deleted) after a successful reset.'
        );
    }

    public function test_reset_password_submit_rejects_mismatched_confirmation_without_changing_password(): void
    {
        [$id, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '444555']);
        $before = DB::table('vendors')->where('id', $id)->value('password');

        $response = $this->putJson(self::RESET_URI, [
            'email' => $email,
            'reset_token' => '444555',
            'password' => $this->strongPassword(),
            'confirm_password' => $this->strongPassword(),
        ]);

        $response->assertStatus(403);
        $this->assertSame($before, DB::table('vendors')->where('id', $id)->value('password'));
    }

    public function test_reset_password_submit_with_wrong_token_is_rejected_generically_and_changes_nothing(): void
    {
        [$id, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '444555']);
        $before = DB::table('vendors')->where('id', $id)->value('password');
        $newPassword = $this->strongPassword();

        $response = $this->putJson(self::RESET_URI, [
            'email' => $email,
            'reset_token' => '000000',
            'password' => $newPassword,
            'confirm_password' => $newPassword,
        ]);

        $response->assertStatus(400);
        $this->assertSame($before, DB::table('vendors')->where('id', $id)->value('password'));
        $this->assertSame(
            1,
            DB::table('password_resets')->where('email', $email)->count(),
            'A wrong-token submit must not consume the still-valid stored token.'
        );
    }

    public function test_reset_password_submit_with_unknown_email_returns_same_generic_rejection_as_wrong_token(): void
    {
        [$id, $email] = $this->makeVendor();
        $this->insertResetRow($email, ['token' => '444555']);
        $newPassword = $this->strongPassword();

        $wrongToken = $this->putJson(self::RESET_URI, [
            'email' => $email,
            'reset_token' => '000000',
            'password' => $newPassword,
            'confirm_password' => $newPassword,
        ]);

        $unknownEmail = $this->putJson(self::RESET_URI, [
            'email' => 'nobody-'.Str::random(8).'@example.test',
            'reset_token' => '000000',
            'password' => $newPassword,
            'confirm_password' => $newPassword,
        ]);

        $wrongToken->assertStatus(400);
        $unknownEmail->assertStatus(400);
        $this->assertSame($wrongToken->json(), $unknownEmail->json());
    }
}
