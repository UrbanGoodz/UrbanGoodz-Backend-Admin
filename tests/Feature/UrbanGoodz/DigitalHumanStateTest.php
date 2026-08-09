<?php

namespace Tests\Feature\UrbanGoodz;

use Tests\TestCase;

class DigitalHumanStateTest extends TestCase
{
    /** @test */
    public function it_returns_valid_digital_human_state_payload_for_concierge(): void
    {
        $response = $this->json('POST', '/api/v1/urban-goodz/digital-human/state', [
            'persona' => 'monique',
            'text' => 'Hello, what food is good near me?',
            'domain' => 'food',
            'intent' => 'find_food',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.persona.key', 'concierge');
        $response->assertJsonPath('data.persona.display_name', 'Ebony');
        $response->assertJsonPath('data.digital_human.environment_key', 'houston_loft');
        $response->assertJsonPath('data.digital_human.mood', 'food_excited');
    }

    /** @test */
    public function it_returns_valid_digital_human_state_payload_for_chief_of_staff(): void
    {
        $response = $this->json('POST', '/api/v1/urban-goodz/digital-human/state', [
            'persona' => 'skylar',
            'text' => 'Route 42 traffic delay flagged',
            'domain' => 'logistics',
            'event_type' => 'risk_flagged',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.persona.key', 'chief_of_staff');
        $response->assertJsonPath('data.persona.display_name', 'Skylar');
        $response->assertJsonPath('data.digital_human.environment_key', 'executive_operations_center');
        $response->assertJsonPath('data.digital_human.mood', 'risk_alert');
    }
}
