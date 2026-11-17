<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Last-resort voice: Cloudflare Workers AI text-to-speech (Deepgram Aura). It sits inside Cloudflare's free daily
 * allowance, so it costs nothing, but that allowance is small, so it is capped per day and only used after the main
 * voices fail. Needs CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_AI_TOKEN.
 */
class CloudflareVoiceService
{
    private const SPEAKERS = ['chief_of_staff' => 'athena', 'concierge' => 'luna'];

    public function isConfigured(): bool
    {
        return $this->accountId() !== '' && $this->token() !== '';
    }

    /** @return array{success: bool, audio?: string, mime?: string, error_code?: string, message?: string} */
    public function synthesize(string $personaKey, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error_code' => 'not_configured', 'message' => 'Cloudflare voice is not configured.'];
        }

        $chars = mb_strlen($text);
        $cap = (int) config('urban_goodz_personas.digital_human_global.cloudflare_daily_char_cap', 7000);
        $key = 'cf_tts_chars:' . now()->format('Ymd');
        if ((int) Cache::get($key, 0) + $chars > $cap) {
            return ['success' => false, 'error_code' => 'provider_error', 'message' => 'Daily free voice allowance used.'];
        }

        $model = (string) config('urban_goodz_personas.digital_human_global.cloudflare_tts_model', '@cf/deepgram/aura-1');
        $speaker = self::SPEAKERS[$personaKey] ?? 'luna';

        try {
            // HTTP/1.1: the host's old libcurl fails with an HTTP/2 PROTOCOL_ERROR against this endpoint.
            $response = Http::withToken($this->token())
                ->withOptions(['version' => 1.1])
                ->connectTimeout(4)->timeout(25)
                ->post("https://api.cloudflare.com/client/v4/accounts/{$this->accountId()}/ai/run/{$model}", [
                    'text' => $text,
                    'speaker' => $speaker,
                    'encoding' => 'mp3',
                ]);

            $type = (string) $response->header('Content-Type');
            $audio = null;
            if ($response->successful() && str_contains($type, 'audio')) {
                $audio = $response->body();
            } elseif ($response->successful() && is_string($b64 = $response->json('result.audio'))) {
                $audio = base64_decode($b64, true) ?: null;
            }

            if ($audio === null || $audio === '') {
                Log::warning('Cloudflare TTS request failed.', ['status' => $response->status(), 'content_type' => $type]);

                return ['success' => false, 'error_code' => 'provider_error', 'message' => 'Voice could not be generated right now.'];
            }

            Cache::add($key, 0, now()->addDay());
            Cache::increment($key, $chars);

            return ['success' => true, 'audio' => $audio, 'mime' => 'audio/mpeg'];
        } catch (\Throwable $e) {
            Log::warning('Cloudflare TTS threw.', ['exception' => $e->getMessage()]);

            return ['success' => false, 'error_code' => 'provider_exception', 'message' => 'Voice could not be generated right now.'];
        }
    }

    private function accountId(): string
    {
        return trim((string) config('urban_goodz_personas.digital_human_global.cloudflare_account_id'));
    }

    private function token(): string
    {
        return trim((string) config('urban_goodz_personas.digital_human_global.cloudflare_ai_token'));
    }
}
