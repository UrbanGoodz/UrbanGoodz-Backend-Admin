<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AIProviderSelectionSourceTest extends TestCase
{
    public function test_gemini_default_is_the_latest_flash_alias_everywhere(): void
    {
        $env = (string) file_get_contents(__DIR__.'/../../.env.example');
        $config = (string) file_get_contents(__DIR__.'/../../config/urban_goodz_ai.php');
        $provider = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodz/AI/GeminiProvider.php'
        );

        $this->assertSame(1, substr_count($env, "\nGEMINI_API_KEY="));
        $this->assertSame(1, substr_count($env, "\nGEMINI_MODEL=gemini-flash-latest"));
        $this->assertStringContainsString(
            "env('GEMINI_MODEL', 'gemini-flash-latest')",
            $config
        );
        $this->assertStringContainsString(
            "public const DEFAULT_MODEL = 'gemini-flash-latest';",
            $provider
        );
    }

    public function test_provider_selection_has_one_explicit_environment_authority(): void
    {
        $env = (string) file_get_contents(__DIR__.'/../../.env.example');
        $config = (string) file_get_contents(__DIR__.'/../../config/urban_goodz_ai.php');
        $manager = (string) file_get_contents(
            __DIR__.'/../../app/Services/UrbanGoodz/AI/AIProviderManager.php'
        );

        $this->assertSame(1, substr_count($env, "\nAI_PROVIDER="));
        $this->assertStringContainsString(
            "'provider' => env('AI_PROVIDER', 'openai')",
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
