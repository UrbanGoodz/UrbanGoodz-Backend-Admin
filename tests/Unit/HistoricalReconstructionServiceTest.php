<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzHistoricalReconstructionConfiguration;
use App\Models\UrbanGoodzHistoricalMonthlySnapshot;
use App\Models\UrbanGoodzHistoricalSourceRecord;
use App\Models\UrbanGoodzHistoricalReconstructionAuditTrail;
use App\Services\UrbanGoodz\HistoricalReconstructionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalReconstructionServiceTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalReconstructionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistoricalReconstructionService();
    }

    private function createDefaultConfig(array $overrides = []): UrbanGoodzHistoricalReconstructionConfiguration
    {
        return UrbanGoodzHistoricalReconstructionConfiguration::create(array_merge([
            'configuration_name' => 'Test Reconstruction',
            'reconstruction_start_date' => '2023-10-01',
            'reconstruction_end_date' => '2025-10-31',
            'owner_name' => 'D\'Andre Good',
            'owner_non_delivery_months' => [12, 1, 2],
            'baseline_monthly_orders' => 750,
            'baseline_average_order_value' => 29.00,
            'baseline_order_commission_pct' => 23.00,
            'baseline_delivery_fee' => 15.00,
            'baseline_platform_delivery_fee_pct' => 3.00,
            'baseline_active_drivers' => 13,
            'baseline_avg_monthly_net' => 5700.00,
            'orders_variation_pct' => 10.00,
            'aov_variation_pct' => 8.00,
            'delivery_fee_variation_pct' => 7.00,
            'driver_count_variation_pct' => 15.00,
            'operating_expense_ratio' => 25.00,
        ], $overrides));
    }

    public function test_create_configuration(): void
    {
        $config = $this->createDefaultConfig();

        $this->assertNotNull($config->id);
        $this->assertEquals('Test Reconstruction', $config->configuration_name);
        $this->assertEquals('2023-10-01', $config->reconstruction_start_date->format('Y-m-d'));
        $this->assertEquals('2025-10-31', $config->reconstruction_end_date->format('Y-m-d'));
        $this->assertEquals(750, $config->baseline_monthly_orders);
        $this->assertEquals([12, 1, 2], $config->owner_non_delivery_months);
    }

    public function test_generate_month_range(): void
    {
        $start = Carbon::parse('2023-10-01');
        $end = Carbon::parse('2025-10-31');
        $months = $this->service->generateMonthRange($start, $end);

        $this->assertCount(25, $months);
        $this->assertEquals('2023-10-01', $months[0]->format('Y-m-d'));
        $this->assertEquals('2025-10-01', $months[24]->format('Y-m-d'));
    }

    public function test_monthly_assumptions_vary(): void
    {
        $config = $this->createDefaultConfig();
        $months = $this->service->generateMonthRange(
            $config->reconstruction_start_date,
            $config->reconstruction_end_date
        );

        $assumptions = [];
        foreach ($months as $month) {
            $assumptions[] = $this->service->generateMonthlyAssumptions($config, $month);
        }

        $orders = array_column($assumptions, 'orders');
        $aovs = array_column($assumptions, 'average_order_value');

        $this->assertNotSame(min($orders), max($orders), 'Orders should vary month to month');
        $this->assertNotSame(min($aovs), max($aovs), 'AOV should vary month to month');

        foreach ($orders as $o) {
            $this->assertGreaterThan(0, $o);
        }
        foreach ($aovs as $a) {
            $this->assertGreaterThan(0, $a);
        }
    }

    public function test_run_full_reconstruction_creates_snapshots(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2023-10-01',
            'reconstruction_end_date' => '2024-03-31',
        ]);

        $snapshots = $this->service->runFullReconstruction($config->id);

        $this->assertCount(6, $snapshots);
        $this->assertDatabaseCount('urban_goodz_historical_monthly_snapshots', 6);
    }

    public function test_owner_delivery_zero_for_dec_jan_feb(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2023-11-01',
            'reconstruction_end_date' => '2024-02-29',
        ]);

        $snapshots = $this->service->runFullReconstruction($config->id);

        $nov = $snapshots->firstWhere('snapshot_month_number', 11);
        $dec = $snapshots->firstWhere('snapshot_month_number', 12);
        $jan = $snapshots->firstWhere('snapshot_month_number', 1);
        $feb = $snapshots->firstWhere('snapshot_month_number', 2);

        $this->assertGreaterThan(0, $nov->estimated_owner_deliveries, 'November should have owner deliveries');
        $this->assertEquals(0, $dec->estimated_owner_deliveries, 'December should have 0 owner deliveries');
        $this->assertEquals(0, $jan->estimated_owner_deliveries, 'January should have 0 owner deliveries');
        $this->assertEquals(0, $feb->estimated_owner_deliveries, 'February should have 0 owner deliveries');
    }

    public function test_revenue_calculation_correctness(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-06-01',
            'reconstruction_end_date' => '2024-06-30',
        ]);

        $snapshots = $this->service->runFullReconstruction($config->id);
        $snapshot = $snapshots->first();

        $expectedTotalOrderValue = round($snapshot->estimated_orders * $snapshot->estimated_average_order_value, 2);
        $this->assertEquals($expectedTotalOrderValue, $snapshot->estimated_total_order_value);

        $expectedCommission = round($expectedTotalOrderValue * 0.23, 2);
        $this->assertEquals($expectedCommission, $snapshot->estimated_order_commission_revenue);

        $expectedDeliveryRevenue = round($snapshot->estimated_orders * $snapshot->estimated_delivery_fee_per_order, 2);
        $this->assertEquals($expectedDeliveryRevenue, $snapshot->estimated_delivery_fee_revenue);

        $expectedPlatformDelivery = round($expectedDeliveryRevenue * 0.03, 2);
        $this->assertEquals($expectedPlatformDelivery, $snapshot->estimated_platform_delivery_fee_revenue);

        $expectedTotalRevenue = round($expectedCommission + $expectedPlatformDelivery, 2);
        $this->assertEquals($expectedTotalRevenue, $snapshot->estimated_total_platform_revenue);
    }

    public function test_reconciliation_against_baseline(): void
    {
        $config = $this->createDefaultConfig();

        $summary = [
            'average_monthly_orders' => 750,
            'average_order_value' => 29.00,
            'average_delivery_fee' => 15.00,
            'average_active_drivers' => 13,
            'average_monthly_net' => 5700.00,
        ];

        $reconciliation = $this->service->reconcileAgainstBaseline($summary, $config);

        $this->assertEquals('MATCH', $reconciliation['overall']);
        $this->assertEquals('MATCH', $reconciliation['orders']['status']);
        $this->assertEquals('MATCH', $reconciliation['net_income']['status']);
    }

    public function test_reconciliation_detects_mismatch(): void
    {
        $config = $this->createDefaultConfig();

        $summary = [
            'average_monthly_orders' => 500,
            'average_order_value' => 20.00,
            'average_delivery_fee' => 10.00,
            'average_active_drivers' => 8,
            'average_monthly_net' => 3000.00,
        ];

        $reconciliation = $this->service->reconcileAgainstBaseline($summary, $config);

        $this->assertNotEquals('MATCH', $reconciliation['overall']);
    }

    public function test_truck_purchase_timeline(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-01-01',
            'reconstruction_end_date' => '2025-12-31',
        ]);

        $this->service->runFullReconstruction($config->id);

        $timeline = $this->service->getTruckPurchaseTimeline($config->id);

        $this->assertEquals('2025-10-08', $timeline['truck_purchase_date']);
        $this->assertNotNull($timeline['pre_purchase_months']);
        $this->assertNotNull($timeline['post_purchase_months']);
    }

    public function test_source_record_import(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-06-01',
            'reconstruction_end_date' => '2024-06-30',
        ]);

        $this->service->runFullReconstruction($config->id);

        $snapshot = $config->snapshots()->first();

        $record = $this->service->importSourceRecord($config->id, [
            'source_type' => 'owner_recollection',
            'source_description' => 'D\'Andre recalls June 2024 orders',
            'source_date' => '2024-06-15',
            'source_data' => ['orders' => 800],
            'confidence_label' => 'estimated',
            'confidence_score' => 0.4,
            'notes' => 'Owner estimate',
            'overrides_reconstruction' => true,
        ], $snapshot->id);

        $this->assertNotNull($record->id);
        $this->assertEquals('owner_recollection', $record->source_type);
        $this->assertTrue($record->overrides_reconstruction);

        $snapshot->refresh();
        $this->assertEquals(800, $snapshot->estimated_orders);
    }

    public function test_audit_trail_created(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-06-01',
            'reconstruction_end_date' => '2024-06-30',
        ]);

        $this->service->runFullReconstruction($config->id);

        $auditCount = UrbanGoodzHistoricalReconstructionAuditTrail::where('configuration_id', $config->id)->count();
        $this->assertGreaterThan(0, $auditCount);
    }

    public function test_export_csv_contains_data(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-06-01',
            'reconstruction_end_date' => '2024-06-30',
        ]);

        $this->service->runFullReconstruction($config->id);

        $csv = $this->service->exportCsv($config->id);

        $this->assertStringContainsString('MONTH', $csv);
        $this->assertStringContainsString('ORDERS', $csv);
        $this->assertStringContainsString('Jun 2024', $csv);
        $this->assertStringContainsString('TOTALS', $csv);
    }

    public function test_export_json_contains_data(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-06-01',
            'reconstruction_end_date' => '2024-06-30',
        ]);

        $this->service->runFullReconstruction($config->id);

        $json = $this->service->exportJson($config->id);

        $this->assertArrayHasKey('configuration', $json);
        $this->assertArrayHasKey('snapshots', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('evidentiary_disclaimer', $json);
        $this->assertCount(1, $json['snapshots']);
    }

    public function test_month_count_attribute(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2023-10-01',
            'reconstruction_end_date' => '2025-10-31',
        ]);

        $this->assertEquals(25, $config->month_count);
    }

    public function test_non_delivery_months_configurable(): void
    {
        $config = $this->createDefaultConfig([
            'reconstruction_start_date' => '2024-01-01',
            'reconstruction_end_date' => '2024-06-30',
            'owner_non_delivery_months' => [6, 7, 8],
        ]);

        $snapshots = $this->service->runFullReconstruction($config->id);

        $jun = $snapshots->firstWhere('snapshot_month_number', 6);
        $may = $snapshots->firstWhere('snapshot_month_number', 5);

        $this->assertEquals(0, $jun->estimated_owner_deliveries, 'June should be 0 (configured non-delivery)');
        $this->assertGreaterThan(0, $may->estimated_owner_deliveries, 'May should have owner deliveries');
    }
}
