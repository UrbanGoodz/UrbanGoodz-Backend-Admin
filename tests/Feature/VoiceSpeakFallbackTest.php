<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService;
use App\Services\UrbanGoodz\AI\DigitalHuman\QwenVoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The characters keep speaking when a provider is down, and nobody can run up the voice bill. */
class VoiceSpeakFallbackTest extends TestCase
{
    use DatabaseTransactions;

    private const SPEAK = '/api/v1/urban-goodz/cross-app/ai/digital-human/speak';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function fakeProviders(bool $qwenWorks, bool $elevenWorks): array
    {
        $qwen = new class($qwenWorks) extends QwenVoiceService {
            public int $calls = 0;
            public function __construct(private bool $ok) {}
            public function synthesize(string $personaKey, string $text): array
            {
                $this->calls++;

                return $this->ok
                    ? ['success' => true, 'audio' => 'QWEN-AUDIO', 'mime' => 'audio/wav', 'error_code' => null, 'message' => null]
                    : ['success' => false, 'audio' => null, 'mime' => null, 'error_code' => 'provider_error', 'message' => 'tunnel down'];
            }
        };
        $eleven = new class($elevenWorks) extends ElevenLabsVoiceService {
            public int $calls = 0;
            public function __construct(private bool $ok) {}
            public function synthesize(string $personaKey, string $text): array
            {
                $this->calls++;

                return $this->ok
                    ? ['success' => true, 'audio' => 'ELEVEN-AUDIO', 'mime' => 'audio/mpeg', 'error_code' => null, 'message' => null]
                    : ['success' => false, 'audio' => null, 'mime' => null, 'error_code' => 'provider_error', 'message' => 'payment required'];
            }
        };
        $this->app->instance(QwenVoiceService::class, $qwen);
        $this->app->instance(ElevenLabsVoiceService::class, $eleven);

        return [$qwen, $eleven];
    }

    private function speak(string $text = 'hello')
    {
        return $this->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => $text]);
    }

    public function test_when_the_voice_machine_is_down_the_backup_provider_speaks(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'qwen']);
        [$qwen, $eleven] = $this->fakeProviders(qwenWorks: false, elevenWorks: true);

        $r = $this->speak();

        $r->assertOk();
        $this->assertSame('ELEVEN-AUDIO', $r->getContent());
        $this->assertSame([1, 1], [$qwen->calls, $eleven->calls]);
    }

    public function test_when_billing_lapses_on_the_primary_the_other_provider_speaks(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'elevenlabs']);
        $this->fakeProviders(qwenWorks: true, elevenWorks: false);

        $this->speak()->assertOk();
        $this->assertSame('QWEN-AUDIO', $this->speak('again')->getContent());
    }

    public function test_the_primary_is_used_when_it_works_and_the_backup_is_never_called(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'qwen']);
        [$qwen, $eleven] = $this->fakeProviders(qwenWorks: true, elevenWorks: true);

        $this->speak()->assertOk();

        $this->assertSame([1, 0], [$qwen->calls, $eleven->calls], 'no paid fallback call when the primary succeeded');
    }

    public function test_when_both_fail_the_client_gets_a_clean_error_to_fall_back_to_the_phone_voice(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'qwen']);
        $this->fakeProviders(qwenWorks: false, elevenWorks: false);

        $this->speak()->assertStatus(503)->assertJsonPath('success', false);
    }

    public function test_fallback_can_be_switched_off(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'qwen', 'urban_goodz_personas.digital_human_global.voice_fallback' => false]);
        [, $eleven] = $this->fakeProviders(qwenWorks: false, elevenWorks: true);

        $this->speak()->assertStatus(503);
        $this->assertSame(0, $eleven->calls);
    }

    public function test_a_signed_in_user_has_their_own_daily_budget_that_failures_do_not_spend(): void
    {
        config(['urban_goodz_personas.digital_human_global.voice_provider' => 'qwen', 'urban_goodz_personas.digital_human_global.user_daily_char_budget' => 50]);
        [$qwen] = $this->fakeProviders(qwenWorks: true, elevenWorks: true);
        $user = User::create(['f_name' => 'V', 'l_name' => 'U', 'email' => 'v-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1]);
        $other = User::create(['f_name' => 'V', 'l_name' => 'O', 'email' => 'vo-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1]);

        $this->actingAs($user, 'api')->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => str_repeat('a', 30)])->assertOk();
        $this->actingAs($user, 'api')->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => str_repeat('b', 30)])->assertStatus(429)->assertJsonPath('error_code', 'user_budget_exhausted');
        $this->actingAs($user, 'api')->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => str_repeat('c', 20)])->assertOk();
        // Somebody else is unaffected, and the refused request never reached a provider.
        $this->actingAs($other, 'api')->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => str_repeat('d', 30)])->assertOk();
        $this->assertSame(3, $qwen->calls);
    }
}
