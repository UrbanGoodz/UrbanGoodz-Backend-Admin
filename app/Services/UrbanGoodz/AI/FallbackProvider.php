<?php

namespace App\Services\UrbanGoodz\AI;

use App\Contracts\AI\AIProviderInterface;
use Illuminate\Support\Facades\Log;

class FallbackProvider extends AbstractAIProvider
{
    public function __construct(
        private readonly AIProviderInterface $primary,
        private readonly ?AIProviderInterface $fallback = null
    ) {}

    public function name(): string
    {
        return $this->primary->name();
    }

    public function primaryName(): string
    {
        return $this->primary->name();
    }

    public function fallbackName(): ?string
    {
        return $this->fallback?->name();
    }

    public function model(): string
    {
        return $this->primary->model();
    }

    public function isConfigured(): bool
    {
        return $this->primary->isConfigured() || ($this->fallback && $this->fallback->isConfigured());
    }

    public function chatResult(string $systemPrompt, string $userMessage, array $context = [], array $history = []): array
    {
        // 1. Attempt Primary Provider if configured
        if ($this->primary->isConfigured()) {
            $primaryResult = $this->primary->chatResult($systemPrompt, $userMessage, $context, $history);

            if (!empty($primaryResult['success'])) {
                return $primaryResult;
            }

            // Primary failed - log recoverable failure without credentials
            Log::warning('Primary AI provider failed. Initiating automatic fallback.', [
                'primary_provider' => $this->primary->name(),
                'primary_model' => $this->primary->model(),
                'error_code' => $primaryResult['error_code'] ?? 'unknown',
            ]);
        } else {
            Log::info('Primary AI provider is not configured. Delegating directly to fallback.', [
                'primary_provider' => $this->primary->name(),
            ]);
        }

        // 2. Attempt Fallback Provider if available and configured
        if ($this->fallback && $this->fallback->isConfigured()) {
            Log::info('Executing AI request via fallback provider.', [
                'fallback_provider' => $this->fallback->name(),
                'fallback_model' => $this->fallback->model(),
            ]);

            $fallbackResult = $this->fallback->chatResult($systemPrompt, $userMessage, $context, $history);

            if (!empty($fallbackResult['success'])) {
                $fallbackResult['fallback_engaged'] = true;
                $fallbackResult['primary_provider'] = $this->primary->name();
                return $fallbackResult;
            }

            Log::warning('Fallback AI provider also failed.', [
                'fallback_provider' => $this->fallback->name(),
                'fallback_model' => $this->fallback->model(),
                'error_code' => $fallbackResult['error_code'] ?? 'unknown',
            ]);
        }

        // 3. Both failed or unconfigured - Return controlled user-safe message
        return [
            'success' => false,
            'response' => 'I am currently experiencing higher than normal request volume. Please give me a moment, or submit your Order Anywhere request directly and our team will get right on it!',
            'error_code' => 'all_providers_unavailable',
            'provider' => $this->primary->name(),
            'model' => $this->primary->model(),
        ];
    }

    public function healthCheck(): array
    {
        $primaryHealth = $this->primary->healthCheck();
        $fallbackHealth = $this->fallback ? $this->fallback->healthCheck() : null;

        $overallHealthy = ($primaryHealth['healthy'] ?? false) || ($fallbackHealth['healthy'] ?? false);

        return [
            'provider' => $this->primary->name(),
            'model' => $this->primary->model(),
            'configured' => $this->isConfigured(),
            'healthy' => $overallHealthy,
            'error_code' => $overallHealthy ? null : ($primaryHealth['error_code'] ?? 'all_providers_unhealthy'),
            'checked_at' => now()->toIso8601String(),
            'primary' => $primaryHealth,
            'fallback' => $fallbackHealth,
        ];
    }
}
