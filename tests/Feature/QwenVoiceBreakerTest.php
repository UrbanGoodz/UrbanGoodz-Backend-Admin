<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AI\DigitalHuman\QwenVoiceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QwenVoiceBreakerTest extends TestCase
{
    public function test_after_a_server_error_it_skips_the_voice_machine_for_a_minute(): void
    {
        Cache::forget('qwen_tts_recently_down');
        config(['urban_goodz_personas.digital_human_global.qwen_base_url' => 'https://tts.example.test']);
        Http::fake(['*' => Http::response('down', 530)]);

        $svc = app(QwenVoiceService::class);
        $first = $svc->synthesize('concierge', 'hello');
        $second = $svc->synthesize('concierge', 'hello again');

        $this->assertFalse($first['success']);
        $this->assertSame('recently_down', $second['error_code']);
        Http::assertSentCount(1);
        Cache::forget('qwen_tts_recently_down');
    }
}
