<?php

namespace App\Services\UrbanGoodz\Agent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PolsiaAdapter implements ToolAdapterInterface
{
    public function name(): string
    {
        return 'polsia';
    }

    public function isConfigured(): bool
    {
        $key = $this->apiKey();
        return !empty($key) && strlen($key) > 5;
    }

    public function execute(string $toolName, array $parameters, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => $toolName,
                'adapter' => $this->name(),
                'error_code' => 'polsia_not_configured',
                'message' => 'Polsia execution adapter is not configured (missing POLSIA_API_KEY).',
            ];
        }

        $endpoint = rtrim(config('urban_goodz_ai.execution.polsia.endpoint', 'https://api.polsia.com/v1'), '/') . '/tasks/execute';
        $timeout = (int) config('urban_goodz_ai.execution.polsia.timeout', 30);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($this->apiKey())
                ->timeout($timeout)
                ->post($endpoint, [
                    'agent_persona' => 'monique_chief_of_staff',
                    'action' => $toolName,
                    'parameters' => $parameters,
                    'context' => [
                        'actor_id' => $context['admin_id'] ?? null,
                        'actor_role' => $context['actor_role'] ?? 'admin',
                        'environment' => config('app.env', 'production'),
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Polsia tool execution rejected by endpoint', [
                    'tool' => $toolName,
                    'status' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'verified' => false,
                    'tool' => $toolName,
                    'adapter' => $this->name(),
                    'error_code' => 'polsia_http_' . $response->status(),
                    'message' => 'Polsia provider returned HTTP ' . $response->status(),
                ];
            }

            $body = $response->json() ?? [];
            return [
                'success' => (bool) ($body['success'] ?? false),
                'verified' => (bool) ($body['verified'] ?? false),
                'tool' => $toolName,
                'adapter' => $this->name(),
                'message' => $body['message'] ?? 'Polsia task completed.',
                'previous_state' => $body['previous_state'] ?? null,
                'new_state' => $body['new_state'] ?? null,
                'data' => $body['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Polsia adapter connection error', [
                'tool' => $toolName,
                'exception' => $e::class,
            ]);

            return [
                'success' => false,
                'verified' => false,
                'tool' => $toolName,
                'adapter' => $this->name(),
                'error_code' => 'polsia_connection_error',
                'message' => 'Polsia service is currently unreachable.',
            ];
        }
    }

    private function apiKey(): string
    {
        return (string) config('urban_goodz_ai.execution.polsia.api_key', env('POLSIA_API_KEY', ''));
    }
}
