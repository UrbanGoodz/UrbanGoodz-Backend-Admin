<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ExternalLoad;
use App\Models\LoadSource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression coverage for the Dispatcher Sourcing "Best Loads" Admin page.
 *
 * The Admin navigation linked to a controller method typed `: JsonResponse`
 * that returned `response()->json($loads)`. With no available loads the whole
 * Admin page rendered as the raw body `[]` in the browser JSON viewer, with no
 * Admin shell, navigation, filters or empty state.
 *
 * The page route now renders Blade. The JSON contract is preserved on a
 * separate `api-best-loads` route that the Admin navigation never points at.
 */
class AdminDispatcherBestLoadsPageTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGE_ROUTE = 'admin.urban-goodz.dispatcher-sourcing.best-loads';
    private const JSON_ROUTE = 'admin.urban-goodz.dispatcher-sourcing.api-best-loads';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::firstOrCreate(
            ['email' => 'best-loads-test-admin@urbangoodz.com'],
            [
                'f_name' => 'Best Loads',
                'l_name' => 'Test Admin',
                'phone' => '1230000098',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();
    }

    private function makeLoad(array $overrides = []): ExternalLoad
    {
        $source = LoadSource::firstOrCreate(
            ['source_key' => 'best-loads-test-source'],
            ['name' => 'Best Loads Test Source', 'type' => 'aggregator', 'enabled' => true]
        );

        return ExternalLoad::create(array_merge([
            'source_id' => $source->id,
            'external_id' => 'BL-TEST-001',
            'fingerprint' => 'bl-test-fingerprint-001',
            'origin_city' => 'Dallas',
            'origin_state' => 'TX',
            'destination_city' => 'Atlanta',
            'destination_state' => 'GA',
            'equipment_type' => 'Dry Van',
            'gross_rate' => 2450.00,
            'rate_per_loaded_mile' => 3.15,
            'distance_loaded' => 780.00,
            'distance_deadhead' => 42.00,
            'estimated_driver_net' => 1830.00,
            'status' => 'available',
            'is_duplicate' => false,
        ], $overrides));
    }

    public function test_best_loads_page_route_renders_html_not_json(): void
    {
        ExternalLoad::query()->update(['is_duplicate' => true]);

        $response = $this->actingAs($this->admin, 'admin')->get(route(self::PAGE_ROUTE));

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertNotSame('[]', trim($response->getContent()));
    }

    public function test_empty_collection_renders_the_approved_empty_state(): void
    {
        ExternalLoad::query()->update(['is_duplicate' => true]);

        $response = $this->actingAs($this->admin, 'admin')->get(route(self::PAGE_ROUTE));

        $response->assertOk();
        $response->assertSee('No recommended loads are currently available', false);
        // Admin shell and filter controls must remain usable on the empty state.
        $response->assertSee('Best Loads', false);
        $response->assertSee('Refresh', false);
    }

    public function test_available_loads_render_in_the_table(): void
    {
        $load = $this->makeLoad();

        $response = $this->actingAs($this->admin, 'admin')->get(route(self::PAGE_ROUTE));

        $response->assertOk();
        $response->assertSee($load->external_id, false);
        $response->assertSee('Dallas', false);
        $response->assertSee('Atlanta', false);
        $response->assertSee('Dry Van', false);
        $response->assertDontSee('No recommended loads are currently available', false);
    }

    public function test_page_never_returns_raw_debug_or_exception_output(): void
    {
        $this->makeLoad(['external_id' => 'BL-TEST-002', 'fingerprint' => 'bl-test-fingerprint-002']);

        $content = $this->actingAs($this->admin, 'admin')
            ->get(route(self::PAGE_ROUTE))
            ->assertOk()
            ->getContent();

        foreach (['SQLSTATE', 'Whoops', 'ErrorException', 'Stack trace', '<?php', '@endif', 'Undefined variable'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Response leaked '{$marker}'.");
        }
    }

    public function test_dedicated_json_endpoint_still_returns_json(): void
    {
        $this->makeLoad(['external_id' => 'BL-TEST-003', 'fingerprint' => 'bl-test-fingerprint-003']);

        $response = $this->actingAs($this->admin, 'admin')->get(route(self::JSON_ROUTE));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertIsArray($response->json());
    }

    public function test_unauthenticated_actor_is_denied_the_page(): void
    {
        $this->get(route(self::PAGE_ROUTE))->assertRedirect();
    }

    public function test_page_and_json_routes_are_registered_and_distinct(): void
    {
        $page = Route::getRoutes()->getByName(self::PAGE_ROUTE);
        $json = Route::getRoutes()->getByName(self::JSON_ROUTE);

        $this->assertNotNull($page, 'Admin page route is missing.');
        $this->assertNotNull($json, 'JSON endpoint route is missing.');
        $this->assertNotSame($page->uri(), $json->uri(), 'Page and JSON endpoint must not share a URI.');
        $this->assertSame('admin/urban-goodz/dispatcher-sourcing/best-loads', $page->uri());
        $this->assertStringContainsString('bestLoadsBlade', $page->getActionName());
        $this->assertStringContainsString('bestLoads', $json->getActionName());
    }

    public function test_dispatcher_sourcing_views_link_to_the_html_page_route(): void
    {
        $dir = dirname(__DIR__, 2).'/resources/views/admin-views/urban-goodz/dispatcher-sourcing';

        foreach (glob($dir.'/*.blade.php') as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString(
                self::JSON_ROUTE,
                $contents,
                basename($file).' links the Admin UI to the raw JSON endpoint.'
            );
        }
    }
}
