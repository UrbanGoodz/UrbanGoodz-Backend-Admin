<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzLoadBoardAuditLog;
use App\Models\DeliveryMan;
use App\Models\Admin;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrbanGoodzLoadBoardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected UrbanGoodzLoadBoardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UrbanGoodzLoadBoardService();
    }

    // ─── P0: No "Workflow Pending" placeholder ──────────────────────────

    public function test_logistics_section_status_is_live(): void
    {
        $controller = new \App\Http\Controllers\Admin\UrbanGoodzAdminController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sections');
        $method->setAccessible(true);
        $sections = $method->invoke($controller);

        $this->assertEquals('Live', $sections['logistics']['status']);
        $this->assertStringContainsString('load-board', $sections['logistics']['url']);
    }

    public function test_medical_courier_section_status_is_live(): void
    {
        $controller = new \App\Http\Controllers\Admin\UrbanGoodzAdminController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sections');
        $method->setAccessible(true);
        $sections = $method->invoke($controller);

        $this->assertEquals('Live', $sections['medical-courier']['status']);
    }

    public function test_events_section_status_is_db_backed(): void
    {
        $controller = new \App\Http\Controllers\Admin\UrbanGoodzAdminController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sections');
        $method->setAccessible(true);
        $sections = $method->invoke($controller);

        $this->assertNotEquals('Admin Workflow Pending', $sections['events']['status']);
    }

    // ─── Status Workflow ────────────────────────────────────────────────

    public function test_status_workflow_transitions(): void
    {
        $this->assertTrue($this->service->canTransition('available', 'assigned'));
        $this->assertTrue($this->service->canTransition('available', 'cancelled'));
        $this->assertTrue($this->service->canTransition('sourced', 'under_review'));
        $this->assertTrue($this->service->canTransition('under_review', 'recommended'));
        $this->assertTrue($this->service->canTransition('recommended', 'offered'));
        $this->assertTrue($this->service->canTransition('offered', 'assigned'));
        $this->assertTrue($this->service->canTransition('assigned', 'in_transit'));
        $this->assertTrue($this->service->canTransition('in_transit', 'picked_up'));
        $this->assertTrue($this->service->canTransition('picked_up', 'delivered'));
        $this->assertTrue($this->service->canTransition('delivered', 'completed'));
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->assertFalse($this->service->canTransition('available', 'delivered'));
        $this->assertFalse($this->service->canTransition('cancelled', 'assigned'));
        $this->assertFalse($this->service->canTransition('completed', 'available'));
        $this->assertFalse($this->service->canTransition('delivered', 'in_transit'));
    }

    // ─── Load CRUD ──────────────────────────────────────────────────────

    public function test_create_load(): void
    {
        $load = $this->service->createLoad([
            'load_number' => 'UG-TEST-001',
            'origin_city' => 'Dallas',
            'origin_state' => 'TX',
            'destination_city' => 'Houston',
            'destination_state' => 'TX',
            'payout_amount' => 1500.00,
            'load_type' => 'FTL',
        ]);

        $this->assertNotNull($load);
        $this->assertEquals('UG-TEST-001', $load->load_number);
        $this->assertEquals('available', $load->status);
        $this->assertEquals(1500.00, $load->payout_amount);
    }

    public function test_create_load_generates_number_when_empty(): void
    {
        $load = $this->service->createLoad([
            'payout_amount' => 500.00,
        ]);

        $this->assertNotNull($load->load_number);
        $this->assertStringStartsWith('UG-', $load->load_number);
    }

    public function test_update_load(): void
    {
        $load = $this->service->createLoad([
            'payout_amount' => 1000.00,
            'origin_city' => 'Dallas',
        ]);

        $updated = $this->service->updateLoad($load->id, [
            'payout_amount' => 1200.00,
            'notes' => 'Updated test',
        ]);

        $this->assertNotNull($updated);
        $this->assertEquals(1200.00, $updated->payout_amount);
        $this->assertEquals('Updated test', $updated->notes);
    }

    public function test_delete_available_load(): void
    {
        $load = $this->service->createLoad(['payout_amount' => 500.00]);
        $this->assertTrue($this->service->deleteLoad($load->id));
        $this->assertNull(UrbanGoodzLoadBoardLoad::find($load->id));
    }

    public function test_delete_assigned_load_is_blocked(): void
    {
        $load = $this->service->createLoad(['payout_amount' => 500.00]);
        $load->update(['status' => 'assigned']);
        $this->assertFalse($this->service->deleteLoad($load->id));
    }

    // ─── Audit Logging ─────────────────────────────────────────────────

    public function test_audit_log_created_on_load_creation(): void
    {
        $load = $this->service->createLoad(['payout_amount' => 500.00]);
        $this->assertDatabaseHas('urban_goodz_load_board_audit_logs', [
            'load_id' => $load->id,
            'event_type' => 'created',
        ]);
    }

    public function test_audit_log_created_on_status_change(): void
    {
        $load = $this->service->createLoad(['payout_amount' => 500.00]);
        $this->service->updateStatus($load->id, 'cancelled');

        $this->assertDatabaseHas('urban_goodz_load_board_audit_logs', [
            'load_id' => $load->id,
            'event_type' => 'status_change',
            'old_value' => 'available',
            'new_value' => 'cancelled',
        ]);
    }

    // ─── Filters ────────────────────────────────────────────────────────

    public function test_list_with_filters(): void
    {
        $this->service->createLoad(['payout_amount' => 1000.00, 'origin_state' => 'TX']);
        $this->service->createLoad(['payout_amount' => 2000.00, 'origin_state' => 'CA']);

        $result = $this->service->listAvailable(['origin_state' => 'TX']);
        $this->assertCount(1, $result['loads']);
        $this->assertEquals('TX', $result['loads'][0]->origin_state);
    }

    public function test_list_search(): void
    {
        $this->service->createLoad(['payout_amount' => 1000.00, 'load_number' => 'SEARCH-ME']);
        $this->service->createLoad(['payout_amount' => 1000.00, 'load_number' => 'OTHER']);

        $result = $this->service->listAvailable(['search' => 'SEARCH-ME']);
        $this->assertCount(1, $result['loads']);
    }

    // ─── Stats ──────────────────────────────────────────────────────────

    public function test_get_stats(): void
    {
        $this->service->createLoad(['payout_amount' => 1000.00]);
        $this->service->createLoad(['payout_amount' => 2000.00]);

        $stats = $this->service->getStats();
        $this->assertArrayHasKey('total_available', $stats);
        $this->assertArrayHasKey('total_assigned', $stats);
        $this->assertArrayHasKey('by_status', $stats);
    }

    // ─── Model Accessors ────────────────────────────────────────────────

    public function test_model_accessors(): void
    {
        $load = new UrbanGoodzLoadBoardLoad([
            'origin_city' => 'Dallas',
            'origin_state' => 'TX',
            'destination_city' => 'Houston',
            'destination_state' => 'TX',
            'status' => 'available',
        ]);

        $this->assertEquals('Dallas, TX', $load->origin_full);
        $this->assertEquals('Houston, TX', $load->destination_full);
        $this->assertEquals('Available', $load->status_label);
        $this->assertEquals('badge-soft-success', $load->status_badge_class);
    }

    // ─── Financial Fields ──────────────────────────────────────────────

    public function test_financial_fields_persist(): void
    {
        $load = $this->service->createLoad([
            'payout_amount' => 1000.00,
            'customer_price' => 1500.00,
            'driver_payout_amount' => 900.00,
            'dispatcher_incentive' => 50.00,
            'platform_margin' => 550.00,
            'source_cost' => 800.00,
            'processing_fee' => 25.00,
            'accessorials' => 75.00,
        ]);

        $this->assertEquals(1500.00, $load->customer_price);
        $this->assertEquals(900.00, $load->driver_payout_amount);
        $this->assertEquals(50.00, $load->dispatcher_incentive);
        $this->assertEquals(550.00, $load->platform_margin);
    }

    public function test_effective_margin_calculation(): void
    {
        $load = new UrbanGoodzLoadBoardLoad([
            'customer_price' => 1500.00,
            'driver_payout_amount' => 900.00,
            'dispatcher_incentive' => 50.00,
            'processing_fee' => 25.00,
            'accessorials' => 75.00,
            'platform_margin' => 0,
        ]);

        $this->assertEquals(600.00, $load->effective_margin);
    }

    public function test_effective_driver_payout(): void
    {
        $load = new UrbanGoodzLoadBoardLoad([
            'driver_payout_amount' => 900.00,
            'payout_amount' => 1000.00,
        ]);

        $this->assertEquals(900.00, $load->effective_driver_payout);

        $load2 = new UrbanGoodzLoadBoardLoad([
            'driver_payout_amount' => null,
            'payout_amount' => 1000.00,
        ]);

        $this->assertEquals(1000.00, $load2->effective_driver_payout);
    }

    // ─── Route Access (Unauthenticated) ─────────────────────────────────

    public function test_admin_load_board_unauthenticated_redirect(): void
    {
        $response = $this->get(route('admin.urban-goodz.load-board.index'));
        $response->assertRedirect();
    }
}
