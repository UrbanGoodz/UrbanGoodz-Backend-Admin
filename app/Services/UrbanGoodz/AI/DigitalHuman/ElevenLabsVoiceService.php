<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side ElevenLabs text-to-speech gateway.
 *
 * The ElevenLabs API key never leaves the server: the mobile/admin clients
 * send text and a persona slug here, and this service returns synthesized
 * audio bytes. There is no local fallback voice — if the provider is not
 * configured or the request fails, callers get a clear failure instead of
 * silence dressed up as success.
 */
class ElevenLabsVoiceService
{
    public function isConfigured(string $personaKey): bool
    {
        return $this->apiKey() !== '' && $this->voiceId($personaKey) !== '';
    }

    /**
     * @return array{success: bool, audio?: string, mime?: string, error_code?: string, message?: string}
     */
    public function synthesize(string $personaKey, string $text): array
    {
        $apiKey = $this->apiKey();
        $voiceId = $this->voiceId($personaKey);

        if ($apiKey === '' || $voiceId === '') {
            return [
                'success' => false,
                'error_code' => 'not_configured',
                'message' => 'Voice is not configured for this persona yet.',
            ];
        }

        $baseUrl = rtrim((string) config('urban_goodz_personas.digital_human_global.elevenlabs_base_url'), '/');
        $modelId = (string) config('urban_goodz_personas.digital_human_global.elevenlabs_model_id');
        $outputFormat = (string) config('urban_goodz_personas.digital_human_global.elevenlabs_output_format');
        $voiceSettings = (array) config("urban_goodz_personas.personas.{$personaKey}.presentation.digital_human.voice_settings", []);

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg',
            ])
                ->timeout(20)
                ->retry(2, 250, throw: false)
                ->post("{$baseUrl}/text-to-speech/{$voiceId}?output_format={$outputFormat}", [
                    'text' => $text,
                    'model_id' => $modelId,
                    'voice_settings' => $voiceSettings,
                ]);

            if (! $response->successful()) {
                Log::warning('ElevenLabs voice synthesis request failed.', [
                    'persona' => $personaKey,
                    'status' => $response->status(),
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
                'mime' => 'audio/mpeg',
            ];
        } catch (\Throwable $e) {
            Log::error('ElevenLabs voice synthesis threw an exception.', [
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

    private function apiKey(): string
    {
        return trim((string) config('urban_goodz_personas.digital_human_global.elevenlabs_api_key'));
    }

    private function voiceId(string $personaKey): string
    {
        return trim((string) config("urban_goodz_personas.personas.{$personaKey}.presentation.digital_human.voice_id"));
    }
}
