<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AIProviderSelectionSourceTest extends TestCase
{
    public function test_gemini_default_is_the_verified_pinned_snapshot_everywhere(): void
    {
        // Superseded again by 5ec7f4d "fix(ai): default every AI path to
        // gemini-flash-lite-latest": measured 2026-09-17 against the live key,
        // gemini-flash-latest returned 429 on 3 of 3 calls (shared free-tier
        // quota exhausted) while flash-lite returned 200 on 3 of 3, from a
        // separate quota pool. Kept as a "-latest" alias so Google can repoint
        // it when a numbered snapshot retires.
        //
        // The point of this test is that the three places that name a default
        // agree; .env.example had been left on the old 3.6-flash pin.
        $env = (string) file_get_contents(__DIR__.'/../../.env.example');
        $config = (string) file_get_contents(__DIR__.'/../../config/urban_goodz_ai.php');
        $provider = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodz/AI/GeminiProvider.php'
        );

        $this->assertSame(1, substr_count($env, "\nGEMINI_API_KEY="));
        $this->assertSame(1, substr_count($env, "\nGEMINI_MODEL=gemini-flash-lite-latest"));
        $this->assertStringContainsString(
            "env('GEMINI_MODEL', 'gemini-flash-lite-latest')",
            $config
        );
        $this->assertStringContainsString(
            "public const DEFAULT_MODEL = 'gemini-flash-lite-latest';",
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
