<?php

namespace App\Services\UrbanGoodz\AI;

use App\Contracts\AI\AIProviderInterface;

abstract class AbstractAIProvider implements AIProviderInterface
{
    protected function success(string $response): array
    {
        return [
            'success' => true,
            'response' => $response,
            'error_code' => null,
            'provider' => $this->name(),
            'model' => $this->model(),
        ];
    }

    protected function failure(string $message, string $errorCode): array
    {
        return [
            'success' => false,
            'response' => $message,
            'error_code' => $errorCode,
            'provider' => $this->name(),
            'model' => $this->model(),
        ];
    }

    protected function healthResult(bool $healthy, ?string $errorCode = null): array
    {
        return [
            'provider' => $this->name(),
            'model' => $this->model(),
            'configured' => $this->isConfigured(),
            'healthy' => $healthy,
            'error_code' => $errorCode,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
