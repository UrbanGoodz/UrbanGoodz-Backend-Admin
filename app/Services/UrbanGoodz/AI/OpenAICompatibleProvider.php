<?php

namespace App\Services\UrbanGoodz\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAICompatibleProvider extends AbstractAIProvider
{
    public function __construct(private readonly string $providerName = 'openai') {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(): string
    {
        $model = config("urban_goodz_ai.providers.{$this->providerName}.model");

        if ($this->providerName === 'openai' && empty($model)) {
            $model = config('urban_goodz.ai_model', 'gpt-4o-mini');
        }

        return (string) $model;
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

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($context !== []) {
            $messages[] = [
                'role' => 'system',
                'content' => "Relevant application data:\n".json_encode(
                    $context,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                ),
            ];
        }
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $request = Http::withToken($this->apiKey())
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->retry($this->attempts(), $this->retryDelay(), throw: false);

            if ($this->providerName === 'openrouter') {
                $request = $request->withHeaders(array_filter([
                    'HTTP-Referer' => config('urban_goodz_ai.providers.openrouter.site_url'),
                    'X-Title' => config('urban_goodz_ai.providers.openrouter.app_name'),
                ]));
            }

            $response = $request->post($this->baseUrl().'/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
                'temperature' => (float) config('urban_goodz_ai.temperature', 0.4),
                'max_tokens' => (int) config('urban_goodz_ai.max_tokens', 1500),
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

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

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
        $key = $this->providerName === 'openai'
            ? (config('openai.api_key') ?: config('urban_goodz_ai.providers.openai.api_key'))
            : config("urban_goodz_ai.providers.{$this->providerName}.api_key");

        return (string) $key;
    }

    private function baseUrl(): string
    {
        $baseUrl = $this->providerName === 'openai'
            ? config('openai.base_url', config('urban_goodz_ai.providers.openai.base_url'))
            : config("urban_goodz_ai.providers.{$this->providerName}.base_url");

        return rtrim((string) $baseUrl, '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('urban_goodz_ai.request_timeout', 30));
    }

    private function attempts(): int
    {
        return max(1, 1 + (int) config('urban_goodz_ai.max_retries', 1));
    }

    private function retryDelay(): int
    {
        return max(0, (int) config('urban_goodz_ai.retry_delay_ms', 250));
    }
}
