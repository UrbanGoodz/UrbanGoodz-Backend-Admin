<?php

namespace App\Services\UrbanGoodz\AI;

use App\Contracts\AI\AIProviderInterface;

/**
 * Single point of AI provider selection.
 *
 * Selection is explicit and comes from exactly one place: the configured
 * value `urban_goodz_ai.provider`, which is fed by the AI_PROVIDER env var.
 * A provider is NEVER inferred from the presence of an API key - holding a
 * GEMINI_API_KEY does not make Gemini live.
 */
class AIProviderManager
{
    /** Providers this platform will instantiate. Anything else fails closed. */
    public const SUPPORTED = ['openai', 'openrouter', 'gemini', 'disabled'];

    /** Selection sources reported by selectionDiagnostics(). */
    public const SOURCE_ARGUMENT = 'explicit_argument';

    public const SOURCE_CONFIG = 'config_ai_provider';

    public const SOURCE_FALLBACK = 'shipped_default';

    public function resolve(?string $provider = null): AIProviderInterface
    {
        $provider = strtolower(trim($provider ?? (string) config('urban_goodz_ai.provider', 'gemini')));

        return match ($provider) {
            'openai', 'openrouter' => new OpenAICompatibleProvider($provider),
            'gemini' => new GeminiProvider,
            'disabled' => new DisabledAIProvider,
            default => new DisabledAIProvider($provider),
        };
    }

    /**
     * The provider name the platform will actually use for the next call.
     */
    public function selectedProvider(?string $provider = null): string
    {
        return $this->normalize($provider ?? $this->configuredProvider());
    }

    /**
     * Machine-readable answer to "which provider is live, why, and what would
     * it take to change it?" - no secret values, only names and booleans.
     *
     * @return array{
     *     selected: string,
     *     source: string,
     *     supported: array<int, string>,
     *     is_supported: bool,
     *     shipped_default: string,
     *     is_shipped_default: bool,
     *     configured: bool,
     *     model: string,
     *     switch_requires: array<int, string>
     * }
     */
    public function selectionDiagnostics(?string $provider = null): array
    {
        $shippedDefault = $this->normalize((string) config('urban_goodz_ai.default_provider', 'openai'));
        $raw = $provider ?? config('urban_goodz_ai.provider');
        $selected = $this->normalize($provider ?? $this->configuredProvider());

        if ($provider !== null) {
            $source = self::SOURCE_ARGUMENT;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $source = self::SOURCE_CONFIG;
        } else {
            $source = self::SOURCE_FALLBACK;
        }

        $instance = $this->resolve($provider);

        return [
            'selected' => $selected,
            'source' => $source,
            'supported' => self::SUPPORTED,
            'is_supported' => in_array($selected, self::SUPPORTED, true),
            'shipped_default' => $shippedDefault,
            'is_shipped_default' => $selected === $shippedDefault,
            'configured' => $instance->isConfigured(),
            'model' => $instance->model(),
            'switch_requires' => [
                'set AI_PROVIDER=<provider> in .env',
                'set the matching credential env var for that provider',
                'run: php artisan config:clear',
            ],
        ];
    }

    private function configuredProvider(): string
    {
        $configured = config('urban_goodz_ai.provider');

        if (! is_string($configured) || trim($configured) === '') {
            return (string) config('urban_goodz_ai.default_provider', 'openai');
        }

        return $configured;
    }

    private function normalize(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
