<?php

namespace App\Services\UrbanGoodz\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider extends AbstractAIProvider
{
    /**
     * gemini-2.5-flash is closed to new API keys ("no longer available to new
     * users") and 404s on generateContent. 3.6-flash is verified live against
     * the production key; gemini-flash-latest is the fallback alias if a
     * specific version is ever retired again.
     */
    public const DEFAULT_MODEL = 'gemini-flash-latest';

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        $model = trim((string) config('urban_goodz_ai.providers.gemini.model', self::DEFAULT_MODEL));

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    public function isConfigured(): bool
    {
        return strlen($this->apiKey()) > 10
            && $this->model() !== ''
            && filter_var($this->baseUrl(), FILTER_VALIDATE_URL) !== false;
    }

    public function chatResult(string $systemPrompt, string $userMessage, array $context = [], array $history = []): array
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

        $contents = [];
        foreach ($history as $turn) {
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $contents[] = [
                'role' => ($turn['role'] ?? '') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $content]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userContent]]];

        $tools = [];
        if (config('urban_goodz_ai.providers.gemini.search_grounding', false)) {
            $tools[] = ['google_search' => (object)[]];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) config('urban_goodz_ai.temperature', 0.4),
                'maxOutputTokens' => max(1500, (int) config('urban_goodz_ai.max_tokens', 3000)),
            ],
        ];
        if (! empty($tools)) {
            $payload['tools'] = $tools;
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
                ->post($this->endpoint(), $payload);

            if (! $response->successful() && ! empty($tools) && $response->status() === 429) {
                Log::info('Gemini Search Grounding rate limit reached; retrying direct generation without search tool.');
                unset($payload['tools']);
                $response = Http::acceptJson()
                    ->asJson()
                    ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                    ->timeout(max(1, (int) config('urban_goodz_ai.request_timeout', 30)))
                    ->post($this->endpoint(), $payload);
            }

            if (! $response->successful()) {
                $status = $response->status();
                $errorCode = match ($status) {
                    429 => 'rate_limit_exceeded',
                    404 => 'model_not_found',
                    401, 403 => 'authentication_failure',
                    500, 502, 503, 504 => 'provider_unavailable',
                    default => 'provider_error',
                };

                Log::warning('UrbanGoodz AI provider request failed.', [
                    'provider' => $this->name(),
                    'status' => $status,
                    'error_code' => $errorCode,
                    'provider_request_id' => $response->header('x-request-id'),
                ]);

                return $this->failure(
                    'AI assistance could not process this request. No action was taken.',
                    $errorCode
                );
            }

            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            $content = trim(collect(is_array($parts) ? $parts : [])
                ->pluck('text')
                ->filter(fn ($part) => is_string($part))
                ->implode(''));

            if ($content === '') {
                return $this->failure(
                    'AI assistance returned no usable response. No action was taken.',
                    'empty_provider_response'
                );
            }

            $successResult = $this->success($content);

            // Extract Google Search citations if available
            $groundingMetadata = data_get($response->json(), 'candidates.0.groundingMetadata', []);
            $citations = [];
            if (! empty($groundingMetadata['groundingChunks'])) {
                foreach ($groundingMetadata['groundingChunks'] as $chunk) {
                    if (! empty($chunk['web']['uri'])) {
                        $citations[] = [
                            'title' => $chunk['web']['title'] ?? '',
                            'url' => $chunk['web']['uri'],
                        ];
                    }
                }
            }
            if (! empty($citations)) {
                $successResult['citations'] = $citations;
            }

            return $successResult;
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
            $result['success'] && str_contains(strtoupper($result['response'] ?? ''), 'OK'),
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
