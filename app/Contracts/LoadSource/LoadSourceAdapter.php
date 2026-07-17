<?php

namespace App\Contracts\LoadSource;

interface LoadSourceAdapter
{
    public function sourceKey(): string;

    public function isConfigured(): bool;

    public function search(array $criteria): array;

    public function getLoad(string $externalId): array;

    public function refreshStatus(string $externalId): array;

    public function supportsBidding(): bool;

    public function supportsBooking(): bool;
}
