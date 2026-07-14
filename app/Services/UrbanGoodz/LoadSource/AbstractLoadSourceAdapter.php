<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Contracts\LoadSource\LoadSourceAdapter;
use Illuminate\Support\Facades\Log;

abstract class AbstractLoadSourceAdapter implements LoadSourceAdapter
{
    protected array $config;
    protected string $key;
    protected bool $bidding = false;
    protected bool $booking = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sourceKey(): string
    {
        return $this->key;
    }

    public function supportsBidding(): bool
    {
        return $this->bidding;
    }

    public function supportsBooking(): bool
    {
        return $this->booking;
    }

    protected function failClosed(string $reason): array
    {
        return [
            'success' => false,
            'source' => $this->sourceKey(),
            'loads' => [],
            'error' => $reason,
            'status' => 'adapter_not_configured',
        ];
    }

    protected function logError(string $method, string $message, array $context = []): void
    {
        Log::error("LoadSourceAdapter [{$this->sourceKey()}] {$method}: {$message}", $context);
    }
}
