<?php

namespace App\Contracts\LoadBoard;

interface LoadBoardProviderInterface
{
    /**
     * Fetch available loads from the provider.
     *
     * @param array $filters Optional filters (origin_state, destination_state, equipment_type, etc.)
     * @param int $maxResults Maximum loads to fetch
     * @return array Normalized load data ready for syncFromProvider()
     */
    public function fetchLoads(array $filters = [], int $maxResults = 100): array;

    /**
     * Fetch a single load by external ID.
     */
    public function getLoad(string $externalId): ?array;

    /**
     * Provider slug used for the `provider` column.
     */
    public function getProviderSlug(): string;

    /**
     * Check if the provider is configured and credentials are present.
     */
    public function isConfigured(): bool;

    /**
     * Normalize a provider-specific load record into the Urban Goodz schema.
     * Input: raw provider response item
     * Output: array matching urban_goodz_load_board_loads columns
     */
    public function normalize(array $raw): array;
}
