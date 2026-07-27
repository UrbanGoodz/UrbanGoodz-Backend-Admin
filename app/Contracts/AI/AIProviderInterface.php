<?php

namespace App\Contracts\AI;

interface AIProviderInterface
{
    public function name(): string;

    public function model(): string;

    public function isConfigured(): bool;

    public function chatResult(string $systemPrompt, string $userMessage, array $context = []): array;

    /**
     * Return operational status only. Credential values must never be included.
     */
    public function healthCheck(): array;
}
