<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Qwen3-TTS (self-hosted) text-to-speech gateway.
 *
 * Mirrors ElevenLabsVoiceService against the self-hosted Qwen TTS service
 * (see ug-tts-service/app.py: POST /v1/tts {voice, text}, X-API-Key header,
 * returns raw audio bytes). The service host/API key never reach the client;
 * the mobile/admin clients send text and a persona slug here and receive
 * synthesized audio. If the provider is not configured or the request fails,
 * callers get a clear failure instead of silence dressed up as success.
 */
class QwenVoiceService
{
    /**
     * Map the app's persona keys to the Qwen TTS service voice profiles.
     * 'concierge' = Skylar, 'chief_of_staff' = Monique. These must match the
     * profile directories in the Qwen service's voice_profiles/ dir.
     */
    private function profileForVoice(string $personaKey): string
    {
        return $personaKey === 'chief_of_staff' ? 'monique' : 'skylar';
    }

    public function isConfigured(string $personaKey): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /**
     * @return array{success: bool, audio?: string, mime?: string, error_code?: string, message?: string}
     */
    public function synthesize(string $personaKey, string $text): array
    {
        $baseUrl = $this->baseUrl();
        $apiKey = $this->apiKey();
        $voice = $this->profileForVoice($personaKey);

        if ($baseUrl === '' || $apiKey === '') {
            return [
                'success' => false,
                'error_code' => 'not_configured',
                'message' => 'Voice is not configured for this persona yet.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/wav, audio/mpeg, application/json',
            ])
                ->timeout(20)
                ->retry(2, 250, throw: false)
                ->post(rtrim($baseUrl, '/') . '/v1/tts', [
                    'voice' => $voice,
                    'text' => $text,
                    'format' => 'wav',
                ]);

            $contentType = $response->header('Content-Type', '');

            if (! $response->successful() || ! str_contains($contentType, 'audio')) {
                Log::warning('Qwen voice synthesis request failed.', [
                    'persona' => $personaKey,
                    'voice' => $voice,
                    'status' => $response->status(),
                    'content_type' => $contentType,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'provider_error',
                    'message' => 'Voice could not be generated right now. Please try again shortly.',
                ];
            }

            return [
                'success' => true,
                'audio' => $response->body(),
                'mime' => $contentType,
            ];
        } catch (\Throwable $e) {
            Log::error('Qwen voice synthesis threw an exception.', [
                'persona' => $personaKey,
                'voice' => $voice,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'provider_exception',
                'message' => 'Voice could not be generated right now. Please try again shortly.',
            ];
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('urban_goodz_personas.digital_human_global.qwen_base_url'), '/');
    }

    private function apiKey(): string
    {
        return trim((string) config('urban_goodz_personas.digital_human_global.qwen_api_key'));
    }
}
