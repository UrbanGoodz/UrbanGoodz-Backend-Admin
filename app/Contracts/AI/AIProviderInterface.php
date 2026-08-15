<?php

namespace App\Contracts\AI;

interface AIProviderInterface
{
    public function name(): string;

    public function model(): string;

    public function isConfigured(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $history  Prior
     *         turns in this conversation, oldest first. role is 'user' or
     *         'assistant'.
     */
    public function chatResult(string $systemPrompt, string $userMessage, array $context = [], array $history = []): array;

    /**
     * Return operational status only. Credential values must never be included.
     */
    public function healthCheck(): array;
}
