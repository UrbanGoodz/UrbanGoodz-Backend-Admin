<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Module;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Portal redesign coverage: the warm "Urban Goodz Command Overview" admin
 * dashboard, the Skylar suggestions + live orders vendor dashboard, and the
 * auto-refresh JSON feed endpoints that back them.
 */
class UrbanGoodzPortalDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN_FEED_ROUTE = 'admin.dashboard-stats.ug-live-feed';
    private const VENDOR_FEED_ROUTE = 'vendor.dashboard.live-feed';

    private Admin $admin;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::firstOrCreate(
            ['email' => 'portal-test-admin@urbangoodz.test'],
            [
                'f_name' => 'Portal',
                'l_name' => 'Admin',
                'phone' => '1230000123',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_logged_in' => 1,
            ]
        );
        $this->admin->forceFill(['role_id' => 1, 'is_logged_in' => 1])->save();

        $module = Module::firstOrCreate(
            ['module_name' => 'Portal Test Module'],
            ['module_type' => 'food', 'status' => 1]
        );
        $zone = Zone::firstOrCreate(
            ['name' => 'Portal Test Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression(
                    "ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"
                ),
                'status' => 1,
            ]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'portal-test-owner@urbangoodz.test'],
            [
                'f_name' => 'Portal',
                'l_name' => 'Owner',
                'phone' => '1230000124',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );

        Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Portal Test Store',
                'phone' => '1230000125',
                'logo' => 'store.png',
                'address' => '1 Portal Way',
                'module_id' => $module->id,
                'zone_id' => $zone->id,
                'status' => 1,
            ]
        );
    }

    public function test_admin_dashboard_renders_warm_portal_command_overview(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('ug-portal', false);
        $response->assertSee('Urban Goodz Command Overview', false);
        $response->assertSee('Monique Insights', false);
        $response->assertSee('ug-count', false);
        $response->assertSee('ug-live-feed', false);
        $response->assertSee('/admin/dashboard-stats/ug-live-feed', false);
    }

    public function test_admin_dashboard_uses_real_insights_not_placeholder_copy(): void
    {
        $content = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Houston revenue +11%', $content);
    }

    public function test_admin_live_feed_endpoint_returns_json_contract(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route(self::ADMIN_FEED_ROUTE));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $json = $response->json();
        $this->assertArrayHasKey('server_time', $json);
        $this->assertArrayHasKey('ugData', $json);
        $this->assertArrayHasKey('insights', $json);
        $this->assertArrayHasKey('feed', $json);
        $this->assertIsArray($json['feed']);

        foreach ($json['feed'] as $item) {
            foreach (['tone', 'icon', 'title', 'meta', 'time', 'href'] as $key) {
                $this->assertArrayHasKey($key, $item, "Feed item missing '{$key}'.");
            }
        }
    }

    public function test_admin_dashboard_never_leaks_debug_or_exception_output(): void
    {
        $content = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        foreach (['SQLSTATE', 'Whoops', 'ErrorException', 'Stack trace', '<?php', '@endif', 'Undefined variable'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Response leaked '{$marker}'.");
        }
    }

    public function test_unauthenticated_admin_is_redirected_from_dashboard_and_feed(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->get(route(self::ADMIN_FEED_ROUTE))->assertRedirect();
    }

    public function test_vendor_dashboard_renders_warm_portal_suggestions_and_live_orders(): void
    {
        $response = $this->actingAs($this->vendor, 'vendor')->get(route('vendor.dashboard'));

        $response->assertOk();
        $response->assertSee('ug-portal', false);
        $response->assertSee('Your Store at a Glance', false);
        $response->assertSee('Monique Suggestions', false);
        $response->assertSee('Live Orders', false);
        $response->assertSee('/vendor-panel/dashboard/live-feed', false);
    }

    public function test_vendor_dashboard_never_leaks_debug_or_exception_output(): void
    {
        $content = $this->actingAs($this->vendor, 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->getContent();

        foreach (['SQLSTATE', 'Whoops', 'ErrorException', 'Stack trace', '<?php', '@endif', 'Undefined variable'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Response leaked '{$marker}'.");
        }
    }

    public function test_vendor_live_feed_endpoint_returns_json_contract(): void
    {
        $response = $this->actingAs($this->vendor, 'vendor')->get(route(self::VENDOR_FEED_ROUTE));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $json = $response->json();
        $this->assertArrayHasKey('server_time', $json);
        $this->assertArrayHasKey('feed', $json);
        $this->assertIsArray($json['feed']);

        foreach ($json['feed'] as $item) {
            foreach (['tone', 'icon', 'title', 'meta', 'time', 'href'] as $key) {
                $this->assertArrayHasKey($key, $item, "Feed item missing '{$key}'.");
            }
        }
    }

    public function test_unauthenticated_vendor_is_redirected_from_dashboard_and_feed(): void
    {
        $this->get(route('vendor.dashboard'))->assertRedirect();
        $this->get(route(self::VENDOR_FEED_ROUTE))->assertRedirect();
    }

    public function test_feed_routes_are_registered_with_expected_uris(): void
    {
        $adminFeed = Route::getRoutes()->getByName(self::ADMIN_FEED_ROUTE);
        $vendorFeed = Route::getRoutes()->getByName(self::VENDOR_FEED_ROUTE);

        $this->assertNotNull($adminFeed, 'Admin live-feed route is missing.');
        $this->assertNotNull($vendorFeed, 'Vendor live-feed route is missing.');
        $this->assertSame('admin/dashboard-stats/ug-live-feed', $adminFeed->uri());
        $this->assertSame('vendor-panel/dashboard/live-feed', $vendorFeed->uri());
        $this->assertStringContainsString('ug_live_feed', $adminFeed->getActionName());
        $this->assertStringContainsString('live_feed', $vendorFeed->getActionName());
    }
}
