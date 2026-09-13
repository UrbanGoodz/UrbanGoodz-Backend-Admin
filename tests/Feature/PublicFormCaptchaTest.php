<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The custom captcha on the public forms.
 *
 * Exercised through contact-us because it has the smallest payload, but the
 * comparison it covers is shared by driver registration and rider
 * registration, which had the identical `!=` against session('six_captcha').
 *
 * The first test is the important one. PHP compares two numeric-looking
 * strings as numbers, so `"123" != "0123"` is false - they are considered
 * equal. Whenever CaptchaBuilder happened to emit an all-digit phrase, a
 * different string satisfied it.
 */
class PublicFormCaptchaTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @param array<string, mixed> $overrides
     */
    private function sendMessage(string $typedCaptcha, ?string $phrase, array $overrides = [])
    {
        $session = $phrase === null ? [] : ['six_captcha' => $phrase];

        return $this->withSession($session)->post('/send-message', array_merge([
            'name' => 'Captcha Test',
            'email' => 'captcha-test@urbangoodz.test',
            'subject' => 'Captcha regression',
            'message' => 'Checking the captcha gate.',
            'custome_recaptcha' => $typedCaptcha,
        ], $overrides));
    }

    private function messagesStored(): int
    {
        return \DB::table('contacts')
            ->where('email', 'captcha-test@urbangoodz.test')
            ->count();
    }

    public function test_a_numeric_phrase_cannot_be_satisfied_by_a_different_numeric_string(): void
    {
        // "0123" is not the phrase, but == would say it is.
        $this->sendMessage('0123', '123');

        self::assertSame(
            0,
            $this->messagesStored(),
            'A numeric-string captcha bypass got through - the comparison is loose again.'
        );
    }

    public function test_scientific_notation_cannot_satisfy_a_numeric_phrase(): void
    {
        // "1e2" == "100" under the old comparison.
        $this->sendMessage('1e2', '100');

        self::assertSame(0, $this->messagesStored());
    }

    public function test_a_wrong_phrase_is_rejected(): void
    {
        $this->sendMessage('zzzzzz', 'ab3f9k');

        self::assertSame(0, $this->messagesStored());
    }

    public function test_an_empty_phrase_is_rejected(): void
    {
        $this->sendMessage('', 'ab3f9k');

        self::assertSame(0, $this->messagesStored());
    }

    public function test_a_request_with_no_issued_phrase_is_rejected(): void
    {
        $this->sendMessage('ab3f9k', null);

        self::assertSame(0, $this->messagesStored());
    }

    public function test_the_matching_phrase_is_accepted(): void
    {
        $this->sendMessage('ab3f9k', 'ab3f9k');

        self::assertSame(1, $this->messagesStored(), 'A correct captcha was rejected.');
    }

    public function test_the_phrase_is_consumed_on_use(): void
    {
        $this->sendMessage('ab3f9k', 'ab3f9k');

        self::assertNull(session('six_captcha'), 'The phrase survived its own use.');
    }
}
