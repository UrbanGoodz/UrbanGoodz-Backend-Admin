<?php

namespace Tests\Unit;

use App\Services\UrbanGoodz\AI\AIProviderManager;
use App\Services\UrbanGoodz\AI\DisabledAIProvider;
use App\Services\UrbanGoodz\AI\GeminiProvider;
use App\Services\UrbanGoodz\AI\OpenAICompatibleProvider;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrbanGoodzAIProviderContractTest extends TestCase
{
    public function test_manager_selects_only_allowlisted_providers_and_fails_closed_for_unknown_value(): void
    {
        $manager = new AIProviderManager;

        $this->assertInstanceOf(GeminiProvider::class, $manager->resolve('gemini'));
        $this->assertInstanceOf(OpenAICompatibleProvider::class, $manager->resolve('openai'));
        $this->assertInstanceOf(OpenAICompatibleProvider::class, $manager->resolve('openrouter'));
        $this->assertInstanceOf(DisabledAIProvider::class, $manager->resolve('disabled'));

        $unknown = $manager->resolve('arbitrary-provider');
        $this->assertInstanceOf(DisabledAIProvider::class, $unknown);
        $this->assertSame('unsupported_provider', $unknown->healthCheck()['error_code']);
    }

    public function test_gemini_uses_generate_content_contract_and_returns_text(): void
    {
        config()->set('urban_goodz_ai.providers.gemini', [
            'api_key' => 'test-gemini-key-not-a-secret',
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
        config()->set('urban_goodz_ai.request_timeout', 10);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['text' => 'Grounded '],
                            ['text' => 'response'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $provider = new GeminiProvider;
        $result = $provider->chatResult(
            'Use only supplied application data.',
            'Summarize the route.',
            ['route_id' => 44]
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Grounded response', $result['response']);
        $this->assertSame('gemini', $result['provider']);
        $this->assertSame('gemini-2.5-flash', $result['model']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key-not-a-secret')
                && data_get($payload, 'systemInstruction.parts.0.text') === 'Use only supplied application data.'
                && str_contains((string) data_get($payload, 'contents.0.parts.0.text'), '"route_id": 44');
        });
    }

    public function test_gemini_failure_and_health_responses_are_sanitized(): void
    {
        $credential = 'test-gemini-key-never-return-this';
        config()->set('urban_goodz_ai.providers.gemini', [
            'api_key' => $credential,
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'provider diagnostic must not be returned'],
            ], 429),
        ]);

        $provider = new GeminiProvider;
        $chat = $provider->chatResult('System', 'User');
        $health = $provider->healthCheck();
        $serialized = json_encode([$chat, $health]);

        $this->assertFalse($chat['success']);
        $this->assertSame('provider_error', $chat['error_code']);
        $this->assertFalse($health['healthy']);
        $this->assertSame('provider_error', $health['error_code']);
        $this->assertStringNotContainsString($credential, $serialized);
        $this->assertStringNotContainsString('provider diagnostic', $serialized);
    }

    public function test_missing_gemini_credential_fails_closed_without_network_request(): void
    {
        config()->set('urban_goodz_ai.providers.gemini.api_key', '');
        Http::fake();

        $result = (new GeminiProvider)->chatResult('System', 'User');

        $this->assertFalse($result['success']);
        $this->assertSame('provider_not_configured', $result['error_code']);
        $this->assertStringContainsString('No action was taken', $result['response']);
        Http::assertNothingSent();
    }

    public function test_intent_classification_keeps_deterministic_fallback_when_provider_is_unavailable(): void
    {
        config()->set('urban_goodz_ai.provider', 'gemini');
        config()->set('urban_goodz_ai.providers.gemini.api_key', '');

        $result = (new UrbanGoodzAIService)->classifyIntent(
            'Order pizza for delivery to my office tomorrow for $35',
            [['slug' => 'order-anywhere', 'description' => 'Create an outside-store request']]
        );

        $this->assertSame('order-anywhere', $result['intent']);
        $this->assertSame(35.0, $result['entities']['budget']);
        $this->assertTrue($result['entities']['create']);
    }
}
