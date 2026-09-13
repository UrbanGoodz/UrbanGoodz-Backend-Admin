<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The captcha on vendor self-registration.
 *
 * This form posts over AJAX and answers with JSON, so a rejected captcha comes
 * back as an errors payload rather than a redirect. It had the same two flaws
 * as the other public forms: a loose `!=` that let a wrong numeric string
 * satisfy an all-digit phrase, and a phrase that outlived its own use.
 */
class VendorSignupCaptchaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // store() refuses everything up front unless registration is open, so
        // without this the captcha branch is never reached and these tests
        // would pass on any code at all.
        //
        // Set through config rather than the table: Helpers::get_business_settings
        // checks Config first, and behind that keeps a `static $allSettings`
        // that survives for the whole PHP process - so a row written here is
        // invisible once any earlier test in the run has populated it.
        // Shape matters: the helper reads $data['value'], so a bare string
        // here resolves to null and the request short-circuits before the
        // captcha is ever consulted.
        \Illuminate\Support\Facades\Config::set('toggle_store_registration_conf', ['value' => '1']);
        \Illuminate\Support\Facades\Config::set('recaptcha_conf', ['value' => json_encode(['status' => 0])]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function apply(string $typedCaptcha, ?string $phrase, array $overrides = [])
    {
        $session = $phrase === null ? [] : ['six_captcha' => $phrase];

        return $this->withSession($session)->postJson('/vendor/apply', array_merge([
            'f_name' => 'Captcha Probe',
            'custome_recaptcha' => $typedCaptcha,
        ], $overrides));
    }

    /** @param array<int, array<string, string>> $errors */
    private function hasCaptchaError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (($error['code'] ?? null) === 'ReCAPTCHA') {
                return true;
            }
        }

        return false;
    }

    public function test_a_numeric_phrase_cannot_be_satisfied_by_a_different_numeric_string(): void
    {
        $response = $this->apply('0123', '123');

        self::assertTrue(
            $this->hasCaptchaError($response->json('errors') ?? []),
            'A numeric-string captcha bypass got through on vendor signup. Body: '
                . substr((string) $response->getContent(), 0, 300)
        );
    }

    public function test_scientific_notation_cannot_satisfy_a_numeric_phrase(): void
    {
        $response = $this->apply('1e2', '100');

        self::assertTrue($this->hasCaptchaError($response->json('errors') ?? []));
    }

    public function test_a_wrong_phrase_is_rejected(): void
    {
        $response = $this->apply('zzzzzz', 'ab3f9k');

        self::assertTrue($this->hasCaptchaError($response->json('errors') ?? []));
    }

    public function test_a_request_with_no_issued_phrase_is_rejected(): void
    {
        $response = $this->apply('ab3f9k', null);

        self::assertTrue($this->hasCaptchaError($response->json('errors') ?? []));
    }

    public function test_the_matching_phrase_clears_the_captcha_gate(): void
    {
        // The payload is deliberately incomplete, so the request still fails -
        // but on the missing store fields, never on the captcha.
        $response = $this->apply('ab3f9k', 'ab3f9k');

        self::assertFalse(
            $this->hasCaptchaError($response->json('errors') ?? []),
            'A correct captcha was rejected on vendor signup.'
        );
    }

    public function test_the_phrase_is_consumed_on_use(): void
    {
        $this->apply('ab3f9k', 'ab3f9k');

        self::assertNull(session('six_captcha'), 'The phrase survived its own use.');
    }
}
