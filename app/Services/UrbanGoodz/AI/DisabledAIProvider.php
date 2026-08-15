<?php

namespace App\Services\UrbanGoodz\AI;

class DisabledAIProvider extends AbstractAIProvider
{
    public function __construct(private readonly string $providerName = 'disabled') {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function chatResult(string $systemPrompt, string $userMessage, array $context = [], array $history = []): array
    {
        return $this->failure(
            'AI assistance is currently unavailable. No action was taken.',
            $this->providerName === 'disabled' ? 'provider_disabled' : 'unsupported_provider'
        );
    }

    public function healthCheck(): array
    {
        return $this->healthResult(
            false,
            $this->providerName === 'disabled' ? 'provider_disabled' : 'unsupported_provider'
        );
    }
}
