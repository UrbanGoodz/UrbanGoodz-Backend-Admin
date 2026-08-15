<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real-time video avatar via Tavus CVI. This is a genuinely live, lip-synced
 * talking face -- distinct from the text/TTS-voice concierge in
 * UrbanGoodzAIConciergeService. Every call is gated by isConfigured(); until
 * a real Tavus account/face/PAL exist, this fails closed with an honest
 * "not available" rather than a fake success.
 */
class UrbanGoodzTavusService
{
    public function isConfigured(): bool
    {
        return strlen((string) config('urban_goodz_ai.tavus.api_key', '')) > 10
            && filled(config('urban_goodz_ai.tavus.face_id'))
            && filled(config('urban_goodz_ai.tavus.pal_id'));
    }

    /**
     * Starts a live conversation. Returns the joinable room URL the client
     * loads (Tavus provisions this on a Daily.co WebRTC room) -- never a
     * fabricated URL; only what Tavus itself returned.
     *
     * @return array{success: bool, conversation_id: ?string, conversation_url: ?string, error_code: ?string}
     */
    public function startConversation(string $conversationName): array
    {
        if (!$this->isConfigured()) {
            return $this->failure('not_configured');
        }

        try {
            $response = Http::withHeaders(['x-api-key' => config('urban_goodz_ai.tavus.api_key')])
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(rtrim((string) config('urban_goodz_ai.tavus.base_url'), '/').'/conversations', [
                    'face_id' => config('urban_goodz_ai.tavus.face_id'),
                    'pal_id' => config('urban_goodz_ai.tavus.pal_id'),
                    'conversation_name' => $conversationName,
                ]);

            if (!$response->successful()) {
                Log::warning('Tavus conversation create failed.', [
                    'status' => $response->status(),
                ]);

                return $this->failure('provider_error');
            }

            $json = $response->json();
            $url = $json['conversation_url'] ?? null;
            $id = $json['conversation_id'] ?? null;

            if (!$url || !$id) {
                return $this->failure('empty_provider_response');
            }

            return [
                'success' => true,
                'conversation_id' => $id,
                'conversation_url' => $url,
                'error_code' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Tavus conversation create is unavailable.', ['exception' => $e::class]);

            return $this->failure('provider_unavailable');
        }
    }

    /**
     * Routine cleanup once the customer leaves the call -- frees the room
     * rather than leaving it billing idle.
     */
    public function endConversation(string $conversationId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders(['x-api-key' => config('urban_goodz_ai.tavus.api_key')])
                ->timeout(10)
                ->post(rtrim((string) config('urban_goodz_ai.tavus.base_url'), '/')."/conversations/{$conversationId}/end");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Tavus conversation end failed.', ['exception' => $e::class]);

            return false;
        }
    }

    private function failure(string $errorCode): array
    {
        return [
            'success' => false,
            'conversation_id' => null,
            'conversation_url' => null,
            'error_code' => $errorCode,
        ];
    }
}
