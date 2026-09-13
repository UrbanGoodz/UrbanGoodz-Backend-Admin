<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivationCheckMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The admin login captcha gate.
 *
 * This exists because the gate used to have a hardcoded way past it: entering
 * the captcha as "9999" while logging in as one specific address let a failed
 * captcha through. The first test here is the one that matters - it fails if
 * that bypass, or anything like it, comes back.
 */
class AdminLoginCaptchaTest extends TestCase
{
    use DatabaseTransactions;

    private const PHRASE = 'ab3f9k';

    protected function setUp(): void
    {
        parent::setUp();

        // The activation middleware redirects away from the route entirely,
        // which would mask what the captcha branch did.
        $this->withoutMiddleware(ActivationCheckMiddleware::class);

        // Login attempts are rate limited per IP, and these tests submit
        // several in a row from the same one.
        RateLimiter::clear('login-attempts:127.0.0.1');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function submit(array $overrides = [], ?string $phrase = self::PHRASE)
    {
        $session = $phrase === null ? [] : ['six_captcha' => $phrase];

        return $this->withSession($session)->post('/login_submit', array_merge([
            'email' => 'someone@urbangoodz.test',
            'password' => 'whatever123',
            'role' => 'admin',
            // Force the custom-captcha branch rather than Google reCAPTCHA,
            // so the test does not depend on a business setting or the network.
            'set_default_captcha' => 1,
            'custome_recaptcha' => self::PHRASE,
        ], $overrides));
    }

    public function test_the_hardcoded_captcha_bypass_is_gone(): void
    {
        // The exact pair that used to walk straight through a failed captcha.
        $response = $this->submit([
            'custome_recaptcha' => '9999',
            'email' => 'eaturban2020@gmail.com',
        ], 'a-different-phrase');

        $response->assertSessionHasErrors();
        self::assertContains(
            'ReCAPTCHA Failed',
            session('errors')->all(),
            'The hardcoded 9999 + eaturban2020@gmail.com bypass is back.'
        );
    }

    public function test_9999_is_not_special_for_any_other_account_either(): void
    {
        $this->submit([
            'custome_recaptcha' => '9999',
            'email' => 'someone.else@urbangoodz.test',
        ], 'a-different-phrase')->assertSessionHasErrors();

        self::assertContains('ReCAPTCHA Failed', session('errors')->all());
    }

    public function test_a_wrong_captcha_is_rejected(): void
    {
        $this->submit(['custome_recaptcha' => 'wrong1'])->assertSessionHasErrors();

        self::assertContains('ReCAPTCHA Failed', session('errors')->all());
    }

    public function test_a_missing_captcha_is_rejected(): void
    {
        $this->submit(['custome_recaptcha' => ''])->assertSessionHasErrors();

        self::assertContains('ReCAPTCHA Failed', session('errors')->all());
    }

    public function test_a_captcha_is_rejected_when_the_session_holds_no_phrase(): void
    {
        // Posting straight to the route without ever loading the login page.
        $this->submit([], null)->assertSessionHasErrors();

        self::assertContains('ReCAPTCHA Failed', session('errors')->all());
    }

    public function test_the_matching_captcha_clears_the_gate(): void
    {
        // Credentials are deliberately bogus, so this still fails to log in -
        // but it must fail on the credentials, never on the captcha.
        $this->submit();

        $errors = session('errors') ? session('errors')->all() : [];
        self::assertNotContains('ReCAPTCHA Failed', $errors);
    }

    public function test_a_solved_phrase_cannot_be_replayed(): void
    {
        // One solved captcha used to cover an unlimited run of password
        // guesses, because the phrase sat in the session until the login page
        // was rendered again - which a script posting here never does.
        $this->withSession(['six_captcha' => self::PHRASE])
            ->post('/login_submit', [
                'email' => 'someone@urbangoodz.test',
                'password' => 'whatever123',
                'role' => 'admin',
                'set_default_captcha' => 1,
                'custome_recaptcha' => self::PHRASE,
            ]);

        self::assertNull(session('six_captcha'), 'The phrase survived its own use.');
    }
}
