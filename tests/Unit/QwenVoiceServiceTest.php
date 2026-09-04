<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\AI\DigitalHuman\QwenVoiceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QwenVoiceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('urban_goodz_personas.digital_human_global.qwen_base_url', 'https://tts.example.internal');
        config()->set('urban_goodz_personas.digital_human_global.qwen_api_key', 'fixture_not_real_key');
    }

    public function test_missing_base_url_fails_closed_instead_of_fabricating_audio(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.qwen_base_url', '');
        config()->set('urban_goodz_personas.digital_human_global.qwen_api_key', 'fixture_not_real_key');

        $service = new QwenVoiceService();

        $this->assertFalse($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('not_configured', $result['error_code']);
        Http::assertNothingSent();
    }

    public function test_missing_api_key_fails_closed(): void
    {
        config()->set('urban_goodz_personas.digital_human_global.qwen_base_url', 'https://tts.example.internal');
        config()->set('urban_goodz_personas.digital_human_global.qwen_api_key', '');

        $service = new QwenVoiceService();

        $this->assertFalse($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('not_configured', $result['error_code']);
        Http::assertNothingSent();
    }

    public function test_configured_and_successful_response_returns_real_audio_bytes(): void
    {
        $fakeAudioBytes = "RIFF\x00\x00\x00\x00WAVEfake-wav-bytes-for-test";

        Http::fake([
            'tts.example.internal/*' => Http::response($fakeAudioBytes, 200, ['Content-Type' => 'audio/wav']),
        ]);

        $service = new QwenVoiceService();

        $this->assertTrue($service->isConfigured('concierge'));

        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertTrue($result['success']);
        $this->assertSame($fakeAudioBytes, $result['audio']);
        $this->assertSame('audio/wav', $result['mime']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tts.example.internal/v1/tts'
                && $request->hasHeader('X-API-Key', 'fixture_not_real_key')
                && $request['voice'] === 'skylar'
                && $request['text'] === 'How you doin?';
        });
    }

    public function test_chief_of_staff_maps_to_monique_profile(): void
    {
        $fakeAudioBytes = "RIFF\x00\x00\x00\x00WAVEfake-wav-bytes-for-test";

        Http::fake([
            'tts.example.internal/*' => Http::response($fakeAudioBytes, 200, ['Content-Type' => 'audio/wav']),
        ]);

        $service = new QwenVoiceService();

        $result = $service->synthesize('chief_of_staff', 'Show me today sum.');

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            return $request['voice'] === 'monique';
        });
    }

    public function test_json_error_response_fails_closed_instead_of_fabricating_success(): void
    {
        Http::fake([
            'tts.example.internal/*' => Http::response(['error_code' => 'provider_error'], 502, ['Content-Type' => 'application/json']),
        ]);

        $service = new QwenVoiceService();
        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('provider_error', $result['error_code']);
    }

    public function test_audio_missing_from_success_response_fails_closed(): void
    {
        Http::fake([
            'tts.example.internal/*' => Http::response('not audio', 200, ['Content-Type' => 'application/json']),
        ]);

        $service = new QwenVoiceService();
        $result = $service->synthesize('concierge', 'How you doin?');

        $this->assertFalse($result['success']);
        $this->assertSame('provider_error', $result['error_code']);
    }
}
