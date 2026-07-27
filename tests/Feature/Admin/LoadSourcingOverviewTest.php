<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ExternalLoad;
use App\Models\LoadSource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression cover for the Load Sourcing overview page.
 *
 * The page returned a 500 in production because `loadOverviewStats()` queried
 * `external_loads.rate_per_mile` and `external_loads.assigned_driver_id` —
 * both of which live on `urban_goodz_load_board_loads`, not on the sourcing
 * table (SQLSTATE[42S22] Column not found). These tests execute the real
 * queries so a reintroduced column-name error fails the suite instead of
 * production.
 */
class LoadSourcingOverviewTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/admin/urban-goodz/load-sourcing/overview';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admins', 'external_loads', 'load_sources', 'urban_goodz_load_board_loads'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Required table `{$table}` is not present in the test database.");
            }
        }
    }

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->orderBy('id')->first();

        if (!$admin) {
            $this->markTestSkipped('No Admin record available to authenticate against.');
        }

        // AdminMiddleware requires is_logged_in AND a matching session token.
        $admin->forceFill(['is_logged_in' => 1])->saveQuietly();
        $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);

        return $admin;
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get(self::URI);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login',
            (string) $response->headers->get('Location'),
            'An unauthenticated admin request must be redirected to a login URL.'
        );
    }

    public function test_authorized_admin_gets_200_and_the_expected_view(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertViewIs('admin-views.urban-goodz.load-sourcing.overview');
    }

    public function test_overview_exposes_every_required_view_data_key(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);
        $response->assertOk();

        $required = [
            'nav', 'stats', 'filters', 'statuses', 'sources', 'sourceHealth',
            'sourceSummary', 'lastSyncAt', 'nextScheduledSync', 'refreshMinutes',
            'settings', 'recentSearches', 'recentSyncRuns', 'syncFailures',
            'recentLoads', 'recentImports', 'duplicates', 'recommendations',
            'matchingDrivers', 'auditTrail', 'overviewError',
        ];

        foreach ($required as $key) {
            $response->assertViewHas($key);
        }
    }

    public function test_stats_use_the_real_external_loads_columns(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);
        $response->assertOk();

        $stats = $response->viewData('stats');

        foreach ([
            'total_loads', 'by_status', 'available', 'duplicates', 'total_payout',
            'avg_rate_per_mile', 'assigned_count', 'unassigned_count',
            'loads_by_origin_state', 'loads_by_equipment_type',
        ] as $key) {
            $this->assertArrayHasKey($key, $stats, "stats is missing `{$key}`.");
        }

        // Guards against the exact production regression.
        $this->assertTrue(
            Schema::hasColumn('external_loads', 'rate_per_loaded_mile'),
            'external_loads must expose rate_per_loaded_mile.'
        );
        $this->assertFalse(
            Schema::hasColumn('external_loads', 'rate_per_mile'),
            'external_loads has no rate_per_mile column; the controller must not query it.'
        );
        $this->assertFalse(
            Schema::hasColumn('external_loads', 'assigned_driver_id'),
            'external_loads has no assigned_driver_id column; the controller must not query it.'
        );
    }

    public function test_page_renders_with_zero_rows_and_shows_empty_states(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(0, ExternalLoad::count(), 'Expected an empty external_loads table for this assertion.');

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertSee('Load Sourcing Overview', false);
        $response->assertSee('No external loads have been sourced yet.', false);
        $response->assertSee('No load sources have been configured yet.', false);
    }

    public function test_filters_are_applied_and_echoed_back(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI . '?status=available&q=acme');

        $response->assertOk();
        $filters = $response->viewData('filters');

        $this->assertSame('available', $filters['status']);
        $this->assertSame('acme', $filters['q']);
    }

    public function test_credential_secrets_are_never_rendered(): void
    {
        $this->actingAsAdmin();

        $source = LoadSource::create([
            'source_key' => 'test_probe_source',
            'name' => 'Test Probe Source',
            'type' => 'api',
            'enabled' => true,
            'api_status' => 'configured',
        ]);
        $source->setCredential('api_key', 'super-secret-value-do-not-leak');

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertDontSee('super-secret-value-do-not-leak', false);
        $response->assertDontSee('encrypted_value', false);
        // The credential's existence and status are still surfaced.
        $response->assertSee('api_key', false);
    }
}
