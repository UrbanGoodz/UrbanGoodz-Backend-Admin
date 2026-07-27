<?php

namespace App\Services\UrbanGoodz\AI;

use App\Contracts\AI\AIProviderInterface;

class AIProviderManager
{
    public function resolve(?string $provider = null): AIProviderInterface
    {
        $provider = strtolower(trim($provider ?? (string) config('urban_goodz_ai.provider', 'openai')));

        return match ($provider) {
            'openai', 'openrouter' => new OpenAICompatibleProvider($provider),
            'gemini' => new GeminiProvider,
            'disabled' => new DisabledAIProvider,
            default => new DisabledAIProvider($provider),
        };
    }
}
