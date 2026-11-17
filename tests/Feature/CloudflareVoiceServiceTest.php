<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AI\DigitalHuman\CloudflareVoiceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareVoiceServiceTest extends TestCase
{
    private function configure(): void
    {
        config([
            'urban_goodz_personas.digital_human_global.cloudflare_account_id' => 'acct',
            'urban_goodz_personas.digital_human_global.cloudflare_ai_token' => 'tok',
            'urban_goodz_personas.digital_human_global.cloudflare_daily_char_cap' => 20,
        ]);
        Cache::forget('cf_tts_chars:' . now()->format('Ymd'));
    }

    public function test_it_returns_audio_and_enforces_the_daily_cap(): void
    {
        $this->configure();
        Http::fake(['api.cloudflare.com/*' => Http::response('MP3DATA', 200, ['Content-Type' => 'audio/mpeg'])]);
        $svc = new CloudflareVoiceService;

        $this->assertTrue($svc->synthesize('concierge', str_repeat('a', 15))['success']);
        $second = $svc->synthesize('concierge', str_repeat('a', 15));
        $this->assertFalse($second['success']);
        Http::assertSentCount(1);
        Cache::forget('cf_tts_chars:' . now()->format('Ymd'));
    }

    public function test_it_is_not_configured_without_credentials(): void
    {
        config(['urban_goodz_personas.digital_human_global.cloudflare_ai_token' => '']);
        $this->assertSame('not_configured', (new CloudflareVoiceService)->synthesize('concierge', 'hi')['error_code']);
    }
}
