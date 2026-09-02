<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AI\DigitalHuman\DigitalHumanStateService;
use App\Services\UrbanGoodz\AI\DigitalHuman\EmotionEngine;
use App\Services\UrbanGoodz\AI\DigitalHuman\VoiceVisemeOrchestrator;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use Tests\TestCase;

class DigitalHumanPlatformTest extends TestCase
{
    public function test_emotion_engine_evaluates_monique_food_event(): void
    {
        $engine = new EmotionEngine();
        $state = $engine->evaluate(
            personaKey: 'concierge',
            domain: 'food',
            intent: 'find_food'
        );

        $this->assertEquals('food_excited', $state['mood']);
        $this->assertEquals('houston_loft', $state['environment']);
        $this->assertEquals('warm_golden_amber', $state['lighting_theme']);
    }

    public function test_emotion_engine_evaluates_skylar_risk_alert(): void
    {
        $engine = new EmotionEngine();
        $state = $engine->evaluate(
            personaKey: 'chief_of_staff',
            domain: 'logistics',
            eventType: 'risk_flagged'
        );

        $this->assertEquals('risk_alert', $state['mood']);
        $this->assertEquals('executive_operations_center', $state['environment']);
        $this->assertEquals('direct_urgent_executive', $state['voice_tone']);
    }

    public function test_voice_viseme_orchestrator_generates_timeline(): void
    {
        $orchestrator = new VoiceVisemeOrchestrator();
        $timeline = $orchestrator->generateVisemeTimeline('Hello whats GOOD', 'concierge');

        $this->assertGreaterThan(0, $timeline['total_duration_ms']);
        $this->assertNotEmpty($timeline['visemes']);
        $this->assertEquals('sil', $timeline['visemes'][0]['viseme']);
    }

    public function test_digital_human_state_service_builds_payload(): void
    {
        $registry = new PersonaRegistry();
        $engine = new EmotionEngine();
        $orchestrator = new VoiceVisemeOrchestrator();

        $service = new DigitalHumanStateService($registry, $engine, $orchestrator);
        $payload = $service->buildStatePayload(
            personaKey: 'concierge',
            text: 'Hello, how you doing? Whats GOOD',
            domain: 'general'
        );

        $this->assertTrue($payload['success']);
        // 'concierge' is the customer-facing persona — Skylar. 'chief_of_staff'
        // (Monique) is the business/executive-facing persona. See
        // app/Services/UrbanGoodz/AI/Persona/PersonaRegistry.php and the
        // canonical mapping in UrbanGoodz2026-Revised's
        // lib/features/digital_human/core/personality_profile.dart.
        $this->assertEquals('Skylar', $payload['data']['persona']['display_name']);
        $this->assertEquals('houston_loft', $payload['data']['digital_human']['environment_key']);
        $this->assertNotEmpty($payload['data']['playback']['viseme_timeline']);
    }
}
