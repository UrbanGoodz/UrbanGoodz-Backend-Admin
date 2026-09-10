<?php

namespace App\Services\UrbanGoodz\Agent;

interface ToolAdapterInterface
{
    /**
     * Unique name of the execution adapter (e.g. 'native', 'polsia').
     */
    public function name(): string;

    /**
     * Whether this adapter has valid configuration and credentials.
     */
    public function isConfigured(): bool;

    /**
     * Execute an authorized tool action.
     *
     * @param string $toolName
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $context
     * @return array{
     *     success: bool,
     *     verified: bool,
     *     tool: string,
     *     adapter: string,
     *     message: string,
     *     previous_state?: mixed,
     *     new_state?: mixed,
     *     data?: array<string, mixed>,
     *     error_code?: string
     * }
     */
    public function execute(string $toolName, array $parameters, array $context = []): array;
}
