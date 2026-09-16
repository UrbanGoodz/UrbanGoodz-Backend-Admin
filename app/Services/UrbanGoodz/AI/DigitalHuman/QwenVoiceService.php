<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Qwen3-TTS voice cloning gateway.
 *
 * Proxies speech synthesis to the self-hosted Urban Goodz TTS service
 * (Qwen3-TTS voice cloning). The TTS service API key never reaches
 * the client; this endpoint returns raw audio bytes only.
 *
 * Falls back to ElevenLabs when the Qwen service is unreachable or
 * misconfigured — see DigitalHumanController for routing logic.
 */
class QwenVoiceService
{
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    /**
     * Persona key -> TTS service voice name mapping.
     */
    private const VOICE_MAP = [
        'concierge'     => 'skylar',
        'chief_of_staff' => 'monique',
    ];

    /**
     * @return array{success: bool, audio?: string, mime?: string, error_code?: string, message?: string}
     */
    public function synthesize(string $personaKey, string $text): array
    {
        $baseUrl = $this->baseUrl();

        if ($baseUrl === '') {
            return [
                'success' => false,
                'error_code' => 'not_configured',
                'message' => 'Qwen TTS service URL is not configured.',
            ];
        }

        $voiceName = self::VOICE_MAP[$personaKey] ?? $personaKey;
        $apiKey = $this->apiKey();

        try {
            $http = Http::timeout(60)
                ->retry(1, 500, throw: false)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'audio/wav, audio/mpeg, application/json',
                    ...($apiKey !== '' ? ['X-API-Key' => $apiKey] : []),
                ])
                ->post(rtrim($baseUrl, '/') . '/v1/tts', [
                    'voice' => $voiceName,
                    'text' => $text,
                    'format' => 'wav',
                ]);

            $contentType = $http->headers()->get('content-type', '');

            if (! $http->successful() || ! str_contains($contentType, 'audio')) {
                Log::warning('Qwen TTS synthesis request failed.', [
                    'persona' => $personaKey,
                    'voice' => $voiceName,
                    'status' => $http->status(),
                    'content_type' => $contentType,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'provider_error',
                    'message' => 'Voice could not be generated right now. Please try again shortly.',
                ];
            }

            $mime = str_contains($contentType, 'mp3') ? 'audio/mpeg' : 'audio/wav';

            return [
                'success' => true,
                'audio' => $http->body(),
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::error('Qwen TTS synthesis threw an exception.', [
                'persona' => $personaKey,
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
        return trim((string) config('urban_goodz_personas.digital_human_global.qwen_base_url'));
    }

    private function apiKey(): string
    {
        return trim((string) config('urban_goodz_personas.digital_human_global.qwen_api_key'));
    }
}
