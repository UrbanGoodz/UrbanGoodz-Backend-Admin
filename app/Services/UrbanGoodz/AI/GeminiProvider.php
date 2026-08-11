<?php

namespace App\Services\UrbanGoodz\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider extends AbstractAIProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('urban_goodz_ai.providers.gemini.model', 'gemini-3.6-flash');
    }

    public function isConfigured(): bool
    {
        return strlen($this->apiKey()) > 10
            && $this->model() !== ''
            && filter_var($this->baseUrl(), FILTER_VALIDATE_URL) !== false;
    }

    public function chatResult(string $systemPrompt, string $userMessage, array $context = []): array
    {
        if (! $this->isConfigured()) {
            return $this->failure(
                'AI assistance is currently unavailable. No action was taken.',
                'provider_not_configured'
            );
        }

        $userContent = $userMessage;
        if ($context !== []) {
            $userContent = "Relevant application data:\n".json_encode(
                $context,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            )."\n\nRequest:\n".$userMessage;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->timeout(max(1, (int) config('urban_goodz_ai.request_timeout', 30)))
                ->retry(
                    max(1, 1 + (int) config('urban_goodz_ai.max_retries', 1)),
                    max(0, (int) config('urban_goodz_ai.retry_delay_ms', 250)),
                    throw: false
                )
                ->post($this->endpoint(), [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $userContent]],
                    ]],
                    'generationConfig' => [
                        'temperature' => (float) config('urban_goodz_ai.temperature', 0.4),
                        'maxOutputTokens' => (int) config('urban_goodz_ai.max_tokens', 1500),
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('UrbanGoodz AI provider request failed.', [
                    'provider' => $this->name(),
                    'status' => $response->status(),
                    'provider_request_id' => $response->header('x-request-id'),
                ]);

                return $this->failure(
                    'AI assistance could not process this request. No action was taken.',
                    'provider_error'
                );
            }

            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            $content = trim(collect(is_array($parts) ? $parts : [])
                ->pluck('text')
                ->filter(fn ($part) => is_string($part))
                ->implode(''));

            return $content !== ''
                ? $this->success($content)
                : $this->failure(
                    'AI assistance returned no usable response. No action was taken.',
                    'empty_provider_response'
                );
        } catch (\Throwable $exception) {
            Log::warning('UrbanGoodz AI provider is unavailable.', [
                'provider' => $this->name(),
                'exception' => $exception::class,
            ]);

            return $this->failure(
                'AI assistance is temporarily unavailable. No action was taken.',
                'provider_unavailable'
            );
        }
    }

    public function healthCheck(): array
    {
        if (! $this->isConfigured()) {
            return $this->healthResult(false, 'provider_not_configured');
        }

        $result = $this->chatResult(
            'This is a connection health check. Return exactly OK.',
            'OK'
        );

        return $this->healthResult(
            $result['success'] && trim(strtoupper($result['response'])) === 'OK',
            $result['success'] ? null : $result['error_code']
        );
    }

    private function apiKey(): string
    {
        return (string) config('urban_goodz_ai.providers.gemini.api_key', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config(
            'urban_goodz_ai.providers.gemini.base_url',
            'https://generativelanguage.googleapis.com/v1beta'
        ), '/');
    }

    private function endpoint(): string
    {
        return $this->baseUrl().'/models/'.rawurlencode($this->model()).':generateContent';
    }
}
