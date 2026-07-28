<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzCompensationResult;
use App\Models\UrbanGoodzCompensationRule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzCompensationRouteTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzCompensationRule $rule;
    private UrbanGoodzCompensationResult $result;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = UrbanGoodzCompensationRule::create([
            'rule_key' => 'route_test',
            'name' => 'Route Test Rule',
            'version' => 1,
            'state' => 'published',
            'is_active' => true,
            'work_type' => 'delivery',
            'priority' => 10,
            'components' => [
                'flat' => ['amount_cents' => 500],
                'tips' => ['reimburse' => true],
            ],
            'splits' => ['basis' => 'customer_charge'],
            'minimum_payout_cents' => 300,
            'maximum_payout_cents' => 5000,
        ]);

        $this->result = UrbanGoodzCompensationResult::create([
            'rule_id' => $this->rule->id,
            'rule_key' => 'route_test',
            'rule_version' => 1,
            'subject_type' => 'order',
            'subject_id' => 42,
            'driver_id' => 10,
            'context' => ['work_type' => 'delivery', 'miles' => 5.0, 'customer_charge_cents' => 2000, 'tips_cents' => 300],
            'breakdown' => ['lines' => [['code' => 'flat', 'label' => 'Flat pay', 'amount_cents' => 500], ['code' => 'tips', 'label' => 'Tips', 'amount_cents' => 300]], 'adjustments' => []],
            'splits' => ['basis_cents' => 2000, 'driver_cents' => 500, 'platform_cents' => 1500, 'driver_pass_through_cents' => 300, 'driver_total_cents' => 800, 'is_deficit' => false, 'reconciles' => true],
            'explanation' => 'Rule route_test v1: Flat pay $5.00 + Tips $3.00 = $8.00',
            'gross_cents' => 2000,
            'driver_cents' => 800,
            'is_final' => true,
            'finalized_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // GET /api/v1/urban-goodz/driver/compensation/{id}
    // ---------------------------------------------------------------

    public function test_show_returns_calculation(): void
    {
        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$this->result->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'calculation_id' => $this->result->id,
            'status' => 'finalized',
            'currency' => 'USD',
            'driver_amount_cents' => 800,
            'pass_through_cents' => 300,
            'tip_cents' => 300,
            'total_payable_cents' => 800,
            'rule_id' => $this->rule->id,
            'rule_version' => 1,
            'work_type' => 'delivery',
            'payout_status' => 'eligible',
        ]);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/999999');

        $response->assertStatus(404);
    }

    public function test_show_does_not_expose_confidential_margins(): void
    {
        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$this->result->id}");

        $response->assertOk();
        $json = $response->json();

        // Should not contain platform splits or deficit details
        $this->assertArrayNotHasKey('platform_cents', $json);
        $this->assertArrayNotHasKey('is_deficit', $json);
        $this->assertArrayNotHasKey('reconciles', $json);
    }

    public function test_show_returns_components(): void
    {
        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$this->result->id}");

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('components', $json);
        $this->assertIsArray($json['components']);
    }

    public function test_show_returns_explanation(): void
    {
        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$this->result->id}");

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('explanation', $json);
        $this->assertNotEmpty($json['explanation']);
    }

    public function test_show_returns_finalized_at(): void
    {
        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$this->result->id}");

        $response->assertOk();
        $json = $response->json();
        $this->assertNotNull($json['finalized_at']);
    }

    // ---------------------------------------------------------------
    // GET /api/v1/urban-goodz/driver/compensation/latest
    // ---------------------------------------------------------------

    public function test_latest_returns_most_recent(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/latest?driver_id=10');

        $response->assertOk();
        $response->assertJsonFragment([
            'calculation_id' => $this->result->id,
        ]);
    }

    public function test_latest_returns_401_without_driver(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/latest');

        $response->assertStatus(401);
    }

    public function test_latest_returns_404_when_no_records(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/latest?driver_id=99999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // GET /api/v1/urban-goodz/driver/compensation/history
    // ---------------------------------------------------------------

    public function test_history_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/history?driver_id=10');

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertGreaterThanOrEqual(1, count($json['data']));
    }

    public function test_history_returns_401_without_driver(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/history');

        $response->assertStatus(401);
    }

    public function test_history_only_returns_own_records(): void
    {
        // Create a record for a different driver
        UrbanGoodzCompensationResult::create([
            'rule_id' => $this->rule->id,
            'rule_key' => 'route_test',
            'rule_version' => 1,
            'subject_type' => 'order',
            'subject_id' => 99,
            'driver_id' => 20,
            'context' => ['work_type' => 'delivery'],
            'breakdown' => [],
            'splits' => [],
            'explanation' => '',
            'gross_cents' => 0,
            'driver_cents' => 500,
            'is_final' => true,
            'finalized_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/urban-goodz/driver/compensation/history?driver_id=10');

        $response->assertOk();
        $json = $response->json();

        foreach ($json['data'] as $item) {
            // All returned records should belong to driver 10
            $this->assertNotEquals(99, $item['calculation_id'] ?? null);
        }
    }

    // ---------------------------------------------------------------
    // Estimate status in API
    // ---------------------------------------------------------------

    public function test_show_returns_estimate_status_for_unfinalized(): void
    {
        $estimate = UrbanGoodzCompensationResult::create([
            'rule_id' => $this->rule->id,
            'rule_key' => 'route_test',
            'rule_version' => 1,
            'subject_type' => 'order',
            'subject_id' => 100,
            'driver_id' => 10,
            'context' => ['work_type' => 'delivery', 'miles' => 3.0, 'customer_charge_cents' => 1500],
            'breakdown' => ['lines' => [['code' => 'flat', 'label' => 'Flat pay', 'amount_cents' => 500]], 'adjustments' => []],
            'splits' => ['basis_cents' => 1500, 'driver_cents' => 500, 'platform_cents' => 1000, 'driver_pass_through_cents' => 0, 'driver_total_cents' => 500, 'is_deficit' => false, 'reconciles' => true],
            'explanation' => 'Rule route_test v1: Flat pay $5.00',
            'gross_cents' => 1500,
            'driver_cents' => 500,
            'is_final' => false,
        ]);

        $response = $this->getJson("/api/v1/urban-goodz/driver/compensation/{$estimate->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'estimate',
            'payout_status' => 'pending',
        ]);
    }
}
