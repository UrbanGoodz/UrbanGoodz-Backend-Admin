<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression coverage for the Load Sourcing admin pages, which were all
 * throwing HTTP 500 in production except Overview:
 * - sources/saved-searches/recommendations/sync-runs/errors passed variables
 *   under the wrong key name (controller vs. blade mismatch).
 * - search/settings/sourced-loads' bulk actions referenced named routes that
 *   were never registered.
 */
class UrbanGoodzLoadSourcingAdminPagesTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::firstOrCreate(
            ['email' => 'load-sourcing-test-admin@urbangoodz.com'],
            [
                'f_name' => 'Load Sourcing',
                'l_name' => 'Test Admin',
                'phone' => '1230000099',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();
    }

    /** @dataProvider adminGetPages */
    public function test_page_loads_without_error(string $routeName)
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route($routeName));

        $response->assertOk();
    }

    public static function adminGetPages(): array
    {
        return [
            'overview' => ['admin.urban-goodz.load-sourcing.overview'],
            'sources' => ['admin.urban-goodz.load-sourcing.sources'],
            'search' => ['admin.urban-goodz.load-sourcing.search'],
            'saved-searches' => ['admin.urban-goodz.load-sourcing.saved-searches'],
            'sourced-loads' => ['admin.urban-goodz.load-sourcing.sourced-loads'],
            'recommendations' => ['admin.urban-goodz.load-sourcing.recommendations'],
            'sync-runs' => ['admin.urban-goodz.load-sourcing.sync-runs'],
            'errors' => ['admin.urban-goodz.load-sourcing.errors'],
            'settings' => ['admin.urban-goodz.load-sourcing.settings'],
        ];
    }

    /**
     * The page tests above run against empty tables, so every route() call that
     * lives inside an @foreach over loads/recommendations/saved searches is
     * never evaluated. In production those loops do run, and an unregistered
     * name throws RouteNotFoundException -> HTTP 500. This asserts statically
     * that every load-sourcing route referenced by the Blade views resolves.
     */
    public function test_every_route_referenced_by_load_sourcing_views_is_registered()
    {
        $viewPath = resource_path('views/admin-views/urban-goodz/load-sourcing');
        $referenced = [];

        foreach (glob($viewPath . '/*.blade.php') as $file) {
            preg_match_all(
                "/route\(\s*'(admin\.urban-goodz\.load-sourcing\.[a-zA-Z0-9._-]+)'/",
                file_get_contents($file),
                $matches
            );

            foreach ($matches[1] as $name) {
                $referenced[$name][] = basename($file);
            }
        }

        $this->assertNotEmpty($referenced, 'No load-sourcing route references found — check the view path.');

        $missing = [];
        foreach ($referenced as $name => $files) {
            if (!app('router')->has($name)) {
                $missing[] = $name . ' (' . implode(', ', array_unique($files)) . ')';
            }
        }

        $this->assertSame([], $missing, "Unregistered route names referenced by Blade views:\n" . implode("\n", $missing));
    }

    /**
     * Renders the list pages with real rows so the @foreach bodies — where the
     * per-row action routes live — actually execute. This is the condition
     * that produced the production 500s; empty tables hide it.
     */
    public function test_list_pages_render_with_rows_present()
    {
        [$load, $recommendation, $savedSearch] = $this->seedRow();

        foreach ([
            'admin.urban-goodz.load-sourcing.sourced-loads',
            'admin.urban-goodz.load-sourcing.recommendations',
            'admin.urban-goodz.load-sourcing.saved-searches',
            'admin.urban-goodz.load-sourcing.sources',
            'admin.urban-goodz.load-sourcing.search',
        ] as $routeName) {
            $response = $this->actingAs($this->admin, 'admin')->get(route($routeName));
            $response->assertOk();
        }

        $this->assertSame($load->id, $recommendation->load_id, 'load_id accessor must resolve to external_load_id');
        $this->assertNotNull($savedSearch->id);
    }

    public function test_load_detail_page_renders()
    {
        [$load] = $this->seedRow();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.load-sourcing.show-load', $load->id))
            ->assertOk()
            ->assertSee((string) $load->id, false);
    }

    public function test_saved_search_edit_page_renders()
    {
        [, , $savedSearch] = $this->seedRow();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.urban-goodz.load-sourcing.edit-saved-search', $savedSearch->id))
            ->assertOk()
            ->assertSee($savedSearch->name, false);
    }

    public function test_import_then_archive_moves_load_through_lifecycle()
    {
        [$load] = $this->seedRow(['status' => 'sourced']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.import-load', $load->id))
            ->assertRedirect();
        $this->assertSame('pending_review', $load->fresh()->status);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.archive-load', $load->id))
            ->assertRedirect();
        $this->assertSame('cancelled', $load->fresh()->status);
    }

    public function test_recommendation_can_be_approved_and_dismissed()
    {
        [, $recommendation] = $this->seedRow();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.approve-recommendation', $recommendation->id))
            ->assertRedirect();
        $this->assertSame('assigned', $recommendation->fresh()->status);

        // Already approved -> must not silently re-transition.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.dismiss-recommendation', $recommendation->id))
            ->assertSessionHasErrors('status');
        $this->assertSame('assigned', $recommendation->fresh()->status);
    }

    public function test_saved_search_can_be_created_and_deleted()
    {
        $before = \App\Models\DispatcherSavedSearch::count();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.save-search'), [
                'name' => 'UGQA Houston Reefer',
                'origin_city' => 'Houston',
                'origin_state' => 'TX',
                'equipment_type' => 'reefer',
            ])
            ->assertRedirect(route('admin.urban-goodz.load-sourcing.saved-searches'));

        $created = \App\Models\DispatcherSavedSearch::where('name', 'UGQA Houston Reefer')->firstOrFail();
        $this->assertSame('Houston', $created->criteria['origin_city']);
        $this->assertFalse((bool) $created->auto_alert);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.urban-goodz.load-sourcing.delete-saved-search', $created->id))
            ->assertRedirect();

        $this->assertSame($before, \App\Models\DispatcherSavedSearch::count());
    }

    public function test_scheduled_search_sets_auto_alert()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.schedule-search'), [
                'name' => 'UGQA Scheduled',
                'origin_state' => 'TX',
                'alert_threshold_score' => 85,
            ])
            ->assertRedirect();

        $created = \App\Models\DispatcherSavedSearch::where('name', 'UGQA Scheduled')->firstOrFail();
        $this->assertTrue((bool) $created->auto_alert);
        $this->assertSame(85, (int) $created->alert_threshold_score);
    }

    public function test_assign_dispatcher_without_target_redirects_to_load_detail()
    {
        [$load] = $this->seedRow();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.assign-dispatcher', $load->id))
            ->assertRedirect(route('admin.urban-goodz.load-sourcing.show-load', $load->id));
    }

    /**
     * @return array{0:\App\Models\ExternalLoad,1:\App\Models\LoadRecommendation,2:\App\Models\DispatcherSavedSearch}
     */
    private function seedRow(array $loadOverrides = []): array
    {
        $source = \App\Models\LoadSource::firstOrCreate(
            ['source_key' => 'ugqa_test_source'],
            ['name' => 'UGQA Test Source', 'is_active' => 1, 'api_status' => 'not_configured']
        );

        $load = \App\Models\ExternalLoad::create(array_merge([
            'source_id' => $source->id,
            'external_id' => 'UGQA-' . uniqid(),
            'fingerprint' => hash('sha256', uniqid('ugqa', true)),
            'origin_city' => 'Houston', 'origin_state' => 'TX',
            'destination_city' => 'Dallas', 'destination_state' => 'TX',
            'equipment_type' => 'dry_van',
            'gross_rate' => 1200.00,
            'distance_loaded' => 240,
            'status' => 'available',
            'is_duplicate' => false,
        ], $loadOverrides));

        $driver = \App\Models\DeliveryMan::first();

        $recommendation = \App\Models\LoadRecommendation::create([
            'external_load_id' => $load->id,
            'delivery_man_id' => $driver?->id,
            'score' => 88,
            'confidence_level' => 'high',
            'status' => 'pending',
        ]);

        $savedSearch = \App\Models\DispatcherSavedSearch::create([
            'name' => 'UGQA Seeded Search',
            'criteria' => ['origin_city' => 'Houston'],
            'auto_alert' => false,
        ]);

        return [$load, $recommendation, $savedSearch];
    }

    public function test_update_settings_route_is_registered()
    {
        $this->assertNotEmpty(route('admin.urban-goodz.load-sourcing.update-settings'));
    }

    public function test_bulk_action_route_is_registered()
    {
        $this->assertNotEmpty(route('admin.urban-goodz.load-sourcing.bulk-action'));
    }

    public function test_bulk_action_dispatches_to_approve()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.urban-goodz.load-sourcing.bulk-action'), [
                'bulk_action' => 'approve',
                'load_ids' => [999999], // no matching row; dispatcher must still run without error
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }
}
