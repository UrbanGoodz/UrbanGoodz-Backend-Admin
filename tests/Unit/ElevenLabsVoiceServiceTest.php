<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\AI\DigitalHuman\ElevenLabsVoiceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElevenLabsVoiceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_base_url', 'https://api.elevenlabs.io/v1');
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_model_id', 'eleven_turbo_v2_5');
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_output_format', 'mp3_44100_128');
        config()->set('urban_goodz_personas.personas.concierge.presentation.digital_human.voice_settings', [
            'stability' => 0.35,
        ]);
    }

    public function test_missing_api_key_fails_closed_instead_of_fabricating_audio(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_api_key', '');
        config()->set('urban_goodz_personas.personas.concierge.presentation.digital_human.voice_id', '03vEurziQfq3V8WZhQvn');

        $service = new ElevenLabsVoiceService();

        $this->assertFalse($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('not_configured', $result['error_code']);
        Http::assertNothingSent();
    }

    public function test_missing_voice_id_fails_closed(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_api_key', 'fixture_not_real_key');
        config()->set('urban_goodz_personas.personas.concierge.presentation.digital_human.voice_id', '');

        $service = new ElevenLabsVoiceService();

        $this->assertFalse($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('not_configured', $result['error_code']);
    }

    public function test_configured_and_successful_response_returns_real_audio_bytes(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_api_key', 'fixture_not_real_key');
        config()->set('urban_goodz_personas.personas.concierge.presentation.digital_human.voice_id', '03vEurziQfq3V8WZhQvn');

        $fakeAudioBytes = "\xFF\xFB\x90\x00fake-mp3-bytes-for-test";

        Http::fake([
            'api.elevenlabs.io/*' => Http::response($fakeAudioBytes, 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $service = new ElevenLabsVoiceService();

        $this->assertTrue($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertTrue($result['success']);
        $this->assertSame($fakeAudioBytes, $result['audio']);
        $this->assertSame('audio/mpeg', $result['mime']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '03vEurziQfq3V8WZhQvn')
                && $request->hasHeader('xi-api-key', 'fixture_not_real_key')
                && $request['text'] === 'How you doin?';
        });
    }

    public function test_provider_error_response_fails_closed_instead_of_fabricating_success(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.elevenlabs_api_key', 'fixture_not_real_key');
        config()->set('urban_goodz_personas.personas.concierge.presentation.digital_human.voice_id', '03vEurziQfq3V8WZhQvn');

        Http::fake([
            'api.elevenlabs.io/*' => Http::response(['detail' => 'quota exceeded'], 429),
        ]);

        $service = new ElevenLabsVoiceService();
        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('provider_error', $result['error_code']);
    }
}
