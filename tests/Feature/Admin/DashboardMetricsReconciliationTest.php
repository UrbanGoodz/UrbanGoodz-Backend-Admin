<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DashboardController;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Reconciles the Urban Goodz Command Center metric tiles against the data the
 * controller actually produces.
 *
 * Why this exists: several tiles in dashboard.blade.php read $ugData keys that
 * urban_goodz_dashboard_data() never returned (driver_pricing_count,
 * service_requests_count, notifications_count, load_sourcing_count). Because
 * the Blade uses `?? 0`, those tiles rendered a hardcoded 0 forever and looked
 * like real "no data" readings instead of a wiring bug. Two other tiles were
 * wired to the wrong key entirely (Load Sourcing showed the Load Board count;
 * Dispatcher showed the dedicated-routes count).
 *
 * These checks are structural and DB-free so they run in any environment.
 */
class DashboardMetricsReconciliationTest extends TestCase
{
    protected string $bladePath;
    protected string $controllerPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bladePath = resource_path('views/admin-views/dashboard.blade.php');
        $this->controllerPath = app_path('Http/Controllers/Admin/DashboardController.php');
    }

    /** Keys the controller advertises in urban_goodz_dashboard_data(). */
    protected function producedKeys(): array
    {
        $source = file_get_contents($this->controllerPath);

        $start = strpos($source, 'public static function urban_goodz_dashboard_data');
        $this->assertNotFalse($start, 'urban_goodz_dashboard_data() not found.');

        $body = substr($source, $start, 8000);
        preg_match_all("/^\s*'([a-z0-9_]+)'\s*=>/mi", $body, $m);

        return array_values(array_unique($m[1]));
    }

    /** Keys the Command Center Blade actually reads from $ugData. */
    protected function consumedKeys(): array
    {
        $blade = file_get_contents($this->bladePath);
        preg_match_all("/\\\$ugData\[\s*'([a-z0-9_]+)'\s*\]/i", $blade, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * The core reconciliation: no tile may read a key the controller does not
     * produce, otherwise it silently renders 0.
     */
    public function test_every_dashboard_tile_key_is_produced_by_the_controller(): void
    {
        $produced = $this->producedKeys();
        $consumed = $this->consumedKeys();

        $this->assertNotEmpty($consumed, 'Expected the dashboard Blade to read $ugData keys.');

        $missing = array_values(array_diff($consumed, $produced));

        $this->assertSame(
            [],
            $missing,
            'dashboard.blade.php reads $ugData key(s) that urban_goodz_dashboard_data() never returns, '
            .'so those tiles always display 0: '.implode(', ', $missing)
        );
    }

    /**
     * Keys that regressed before and must stay wired.
     */
    public function test_previously_missing_metric_keys_are_now_produced(): void
    {
        $produced = $this->producedKeys();

        foreach ([
            'load_sourcing_count',
            'driver_pricing_count',
            'service_requests_count',
            'notifications_count',
            'dispatcher_count',
        ] as $key) {
            $this->assertContains(
                $key,
                $produced,
                "Metric key [{$key}] must be produced by urban_goodz_dashboard_data()."
            );
        }
    }

    /**
     * Load Sourcing and Load Board are distinct datasets and must not share a key.
     */
    public function test_load_sourcing_tile_does_not_reuse_the_load_board_count(): void
    {
        $blade = file_get_contents($this->bladePath);

        $this->assertMatchesRegularExpression(
            "/Load Sourcing.*?\\\$ugData\['load_sourcing_count'\]/s",
            $blade,
            'The Load Sourcing tile must read load_sourcing_count, not the Load Board count.'
        );
    }

    /**
     * Dispatcher is not the same concept as dedicated routes.
     */
    public function test_dispatcher_tile_does_not_reuse_dedicated_routes_count(): void
    {
        $blade = file_get_contents($this->bladePath);

        $this->assertMatchesRegularExpression(
            "/Dispatcher.*?\\\$ugData\['dispatcher_count'\]/s",
            $blade,
            'The Dispatcher tile must read dispatcher_count, not dedicated_routes_count.'
        );
    }

    /**
     * Ledger revenue must net off refunds rather than summing captures alone.
     */
    public function test_ledger_revenue_is_net_of_refunds_and_currency_scoped(): void
    {
        $this->assertTrue(
            method_exists(DashboardController::class, 'ug_ledger_net_revenue'),
            'Expected a dedicated net-revenue aggregator.'
        );

        $source = $this->methodSource('ug_ledger_net_revenue');

        $this->assertStringContainsString("'capture'", $source);
        $this->assertStringContainsString("'refund'", $source, 'Net revenue must subtract refunds.');
        $this->assertStringContainsString('currency', $source, 'Revenue must be scoped to a single currency.');
    }

    /**
     * Marketplace revenue must count only money actually collected and must be
     * able to exclude demo/disabled-module fixtures.
     */
    public function test_marketplace_revenue_filters_payment_status_and_demo_records(): void
    {
        $this->assertTrue(
            method_exists(DashboardController::class, 'ug_marketplace_revenue'),
            'Expected a marketplace revenue aggregator distinct from the ledger.'
        );

        $source = $this->methodSource('ug_marketplace_revenue');

        $this->assertStringContainsString("'paid'", $source, 'Only paid orders count as revenue.');
        $this->assertStringContainsString('canceled', $source, 'Canceled orders must be excluded.');
        $this->assertStringContainsString('refunded', $source, 'Refunded orders must be excluded.');
        $this->assertStringContainsString('status', $source, 'Disabled (demo) modules must be excludable.');
    }

    /**
     * Ledger revenue and marketplace revenue are separate sources and must both
     * be surfaced, so an empty ledger is never read as "no sales".
     */
    public function test_ledger_and_marketplace_revenue_are_reported_separately(): void
    {
        $produced = $this->producedKeys();

        $this->assertContains('total_revenue', $produced);
        $this->assertContains('marketplace_revenue', $produced);
        $this->assertContains('marketplace_revenue_excluding_demo', $produced);

        $blade = file_get_contents($this->bladePath);
        $this->assertStringContainsString('Marketplace Revenue', $blade);
        $this->assertStringContainsString('Ledger Revenue', $blade);
    }

    /**
     * Every metric read must tolerate a missing table rather than fatally
     * erroring the dashboard.
     */
    public function test_metric_reads_are_guarded_by_schema_checks(): void
    {
        $source = $this->methodSource('urban_goodz_dashboard_data');

        $this->assertGreaterThan(
            15,
            substr_count($source, 'Schema::hasTable'),
            'Metric reads must be guarded with Schema::hasTable so a missing table cannot 500 the dashboard.'
        );
    }

    /**
     * Command Center data must never be exposed to an unauthorised admin.
     */
    public function test_dashboard_data_requires_authentication_and_permission(): void
    {
        $source = $this->methodSource('urban_goodz_dashboard_data');

        $this->assertStringContainsString("auth('admin')->check()", $source);
        $this->assertStringContainsString('module_permission_check', $source);
    }

    protected function methodSource(string $method): string
    {
        $ref = new ReflectionMethod(DashboardController::class, $method);
        $lines = file($this->controllerPath);

        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
    }
}
