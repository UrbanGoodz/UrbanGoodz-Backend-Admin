<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AIProviderSelectionSourceTest extends TestCase
{
    public function test_gemini_default_is_the_verified_pinned_snapshot_everywhere(): void
    {
        // Superseded by 13ddfba "fix(ai): pin Gemini to 3.6-flash, verified
        // live against the production key" - gemini-flash-latest was the
        // original policy, but the numbered snapshot is what's actually
        // confirmed working, with the alias kept only as a documented
        // fallback if this snapshot is ever retired.
        $env = (string) file_get_contents(__DIR__.'/../../.env.example');
        $config = (string) file_get_contents(__DIR__.'/../../config/urban_goodz_ai.php');
        $provider = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodz/AI/GeminiProvider.php'
        );

        $this->assertSame(1, substr_count($env, "\nGEMINI_API_KEY="));
        $this->assertSame(1, substr_count($env, "\nGEMINI_MODEL=gemini-3.6-flash"));
        $this->assertStringContainsString(
            "env('GEMINI_MODEL', 'gemini-3.6-flash')",
            $config
        );
        $this->assertStringContainsString(
            "public const DEFAULT_MODEL = 'gemini-3.6-flash';",
            $provider
        );
    }

    public function test_provider_selection_has_one_explicit_environment_authority(): void
    {
        // Superseded by c57e447 "feat(ai): configure Gemini 3.6 Flash as
        // central canonical AI reasoning engine across Urban Goodz
        // ecosystem" - the config-level fallback (used whenever AI_PROVIDER
        // is unset) moved from openai to gemini; .env.example still pins
        // AI_PROVIDER=openai explicitly for this environment.
        $env = (string) file_get_contents(__DIR__.'/../../.env.example');
        $config = (string) file_get_contents(__DIR__.'/../../config/urban_goodz_ai.php');
        $manager = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodz/AI/AIProviderManager.php'
        );

        $this->assertSame(1, substr_count($env, "\nAI_PROVIDER="));
        $this->assertStringContainsString(
            "'provider' => env('AI_PROVIDER', 'gemini')",
            $config
        );
        $this->assertStringContainsString(
            "config('urban_goodz_ai.provider')",
            $manager
        );
        $this->assertStringNotContainsString("env('GEMINI_API_KEY'", $manager);
        $this->assertStringNotContainsString("env('OPENAI_API_KEY'", $manager);
        $this->assertStringNotContainsString("env('OPENROUTER_API_KEY'", $manager);
    }
}
