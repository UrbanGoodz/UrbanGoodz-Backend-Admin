<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzAIConversation;
use App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Open (no-account) AI endpoints: spend is capped, chat keys are unguessable, old guest chats are pruned. */
class GuestAiLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private const SPEAK = '/api/v1/urban-goodz/cross-app/ai/digital-human/speak';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('urban_goodz_personas.digital_human_global.voice_provider', 'elevenlabs');
        // A provider that always succeeds, so only the budget decides the outcome.
        $this->app->instance(ElevenLabsVoiceService::class, new class extends ElevenLabsVoiceService {
            public int $calls = 0;

            public function synthesize(string $personaKey, string $text): array
            {
                $this->calls++;

                return ['success' => true, 'audio' => 'AUDIO', 'mime' => 'audio/mpeg', 'error_code' => null, 'message' => null];
            }
        });
    }

    private function speak(string $text)
    {
        return $this->postJson(self::SPEAK, ['persona' => 'concierge', 'text' => $text]);
    }

    public function test_guest_speech_stops_at_the_daily_budget_and_never_reaches_the_provider_after_that(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.guest_daily_char_budget', 50);

        $this->speak(str_repeat('a', 30))->assertOk();
        $this->speak(str_repeat('b', 30))->assertStatus(429)->assertJsonPath('error_code', 'guest_budget_exhausted');
        $this->speak(str_repeat('c', 20))->assertOk();          // exactly reaches the budget: a refused request spent nothing

        $this->assertSame(2, app(ElevenLabsVoiceService::class)->calls, 'the refused request must not call the paid provider');
    }

    public function test_a_failed_synthesis_spends_nothing_from_the_budget(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.guest_daily_char_budget', 40);
        $this->app->instance(ElevenLabsVoiceService::class, new class extends ElevenLabsVoiceService {
            public function synthesize(string $personaKey, string $text): array
            {
                return ['success' => false, 'audio' => null, 'mime' => null, 'error_code' => 'provider_error', 'message' => 'down'];
            }
        });

        $this->speak(str_repeat('a', 40))->assertStatus(502);
        $this->speak(str_repeat('a', 40))->assertStatus(502);   // would be 429 if the first failure had been charged
    }

    public function test_a_guest_chat_session_id_must_be_hard_to_guess(): void
    {
        foreach (['1', 'sess-1', 'abc', 'has space in it 12345', str_repeat('x', 15)] as $weak) {
            $this->postJson('/api/v1/urban-goodz/ai-concierge/query', ['query' => 'hi', 'session_id' => $weak])
                ->assertStatus(422);
        }
    }

    public function test_old_guest_chats_are_pruned_and_customer_chats_are_left_alone(): void
    {
        $mk = fn (?int $customer, int $daysOld) => tap(UrbanGoodzAIConversation::create([
            'customer_id' => $customer, 'session_id' => 'prune-'.uniqid(), 'query_text' => 'q', 'response_text' => 'r', 'status' => 'completed', 'source' => 'customer_guest',
        ]), fn ($c) => UrbanGoodzAIConversation::whereKey($c->id)->update(['created_at' => now()->subDays($daysOld)]));

        $oldGuest = $mk(null, 45);
        $newGuest = $mk(null, 5);
        $oldCustomer = $mk(424242, 45);

        Artisan::call('ai-concierge:prune-guests');

        $this->assertNull(UrbanGoodzAIConversation::find($oldGuest->id));
        $this->assertNotNull(UrbanGoodzAIConversation::find($newGuest->id));
        $this->assertNotNull(UrbanGoodzAIConversation::find($oldCustomer->id), 'only guest chats are pruned here');
    }
}
