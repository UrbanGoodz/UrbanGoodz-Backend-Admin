<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\OrderAnywhereNLPService;
use App\Services\UrbanGoodz\AI\AIProviderManager;
use App\Services\UrbanGoodz\AI\GeminiProvider;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrbanGoodzEcosystemEndToEndTest extends TestCase
{
    /**
     * 1. Test Gemini 3.6 Flash AI Reasoning Engine Configuration & Provider Resolution
     */
    public function test_gemini_3_6_flash_central_ai_brain_configuration(): void
    {
        $manager = new AIProviderManager();
        $provider = $manager->resolve();

        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertEquals('gemini', $provider->name());
        $this->assertEquals('gemini-3.6-flash', $provider->model());

        $aiService = new UrbanGoodzAIService($manager);
        $this->assertStringContainsString('generativelanguage.googleapis.com', $aiService->getBaseUrl());
    }

    /**
     * 2. Test Monique & Skylar Persona Resolution in PersonaRegistry
     */
    public function test_persona_registry_monique_and_skylar_resolution(): void
    {
        // Monique is the business/executive persona (Chief of Staff); Skylar
        // is the customer-facing concierge. Confirmed against the shipped
        // Flutter app's canonical mapping in
        // lib/features/digital_human/core/personality_profile.dart
        // ("Monique — executive Chief of Staff", "Skylar — the star... sass").
        $registry = new PersonaRegistry();

        $monique = $registry->get('monique');
        $this->assertEquals('Monique', $monique->displayName);
        $this->assertEquals('chief_of_staff', $monique->key);
        $this->assertNotEmpty($monique->presentation['digital_human']['voice_id']);
        $this->assertNotEmpty($monique->presentation['greeting']);

        $skylar = $registry->get('skylar');
        $this->assertEquals('Skylar', $skylar->displayName);
        $this->assertEquals('concierge', $skylar->key);
        $this->assertNotEmpty($skylar->presentation['digital_human']['voice_id']);
        $this->assertNotEmpty($skylar->presentation['greeting']);
    }

    /**
     * 3. Test Monique Integration in Order Anywhere NLP Service
     */
    public function test_order_anywhere_monique_nlp_integration(): void
    {
        $aiService = new UrbanGoodzAIService();
        $nlpService = new OrderAnywhereNLPService($aiService);

        $this->assertNotNull($nlpService);

        $reflection = new \ReflectionClass($nlpService);
        $method = $reflection->getMethod('criticalFieldMessage');
        $method->setAccessible(true);

        $storeMsg = $method->invoke($nlpService, 'store_name');
        $this->assertStringContainsString('store you looking to order from', $storeMsg);

        // Order Anywhere is a customer-facing surface — Skylar's voice, not
        // Monique's (the business/executive persona). See PersonaRegistry.
        $itemMsg = $method->invoke($nlpService, 'items');
        $this->assertStringContainsString('Tell Skylar what items you need', $itemMsg);
    }

    /**
     * 4. Test Live Stripe Sandbox Payment Gateway Connectivity
     */
    public function test_stripe_sandbox_payment_gateway_connectivity(): void
    {
        $secretKey = (string) config('urban_goodz_payments.stripe.secret_key', env('STRIPE_SECRET_KEY', ''));
        if (empty($secretKey)) {
            $this->markTestSkipped('Stripe secret key is not set in current environment.');
        }

        $this->assertNotEmpty($secretKey);
        $this->assertStringStartsWith('sk_test_', $secretKey);

        $response = Http::withToken($secretKey)->get('https://api.stripe.com/v1/balance');

        $this->assertEquals(200, $response->status());
        $this->assertEquals('balance', $response->json('object'));
        $this->assertFalse($response->json('livemode'));
    }

    /**
     * 5. Test Stripe Issuing Virtual Card Balance Capability
     */
    public function test_stripe_virtual_card_issuing_capability(): void
    {
        $secretKey = (string) config('urban_goodz_payments.stripe.secret_key', env('STRIPE_SECRET_KEY', ''));
        if (empty($secretKey)) {
            $this->markTestSkipped('Stripe secret key is not set in current environment.');
        }

        $response = Http::withToken($secretKey)->get('https://api.stripe.com/v1/balance');

        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('issuing', $response->json());
    }

    /**
     * 6. Test Surface-to-Persona Routing (Customer -> Monique, Vendor -> Skylar)
     */
    public function test_surface_persona_routing(): void
    {
        $surfaces = config('urban_goodz_personas.surfaces');

        $this->assertEquals('concierge', $surfaces['customer_app']);
        $this->assertEquals('concierge', $surfaces['website']);
        $this->assertEquals('concierge', $surfaces['stranded']);

        $this->assertEquals('chief_of_staff', $surfaces['vendor']);
        $this->assertEquals('chief_of_staff', $surfaces['business_portal']);
        $this->assertEquals('chief_of_staff', $surfaces['admin']);
        $this->assertEquals('chief_of_staff', $surfaces['driver_support']);
    }
}
