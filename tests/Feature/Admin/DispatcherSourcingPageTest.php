<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression cover for the P0 defect where
 * GET /admin/urban-goodz/dispatcher-sourcing served a raw JSON document
 * to a logged-in admin instead of the existing Blade dashboard.
 */
class DispatcherSourcingPageTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/admin/urban-goodz/dispatcher-sourcing';

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('role_id', 1)->first() ?? Admin::query()->first();

        if (!$admin) {
            $this->markTestSkipped('No admin row available in the test database.');
        }

        // AdminMiddleware requires BOTH of these to consider the session valid.
        $admin->forceFill([
            'is_logged_in' => 1,
            'login_remember_token' => 'dispatcher-sourcing-test-token',
        ])->saveQuietly();

        $this->withSession(['login_remember_token' => 'dispatcher-sourcing-test-token'])
            ->be($admin, 'admin');

        return $admin;
    }

    /** The index URL must be bound to the Blade action, not the JSON one. */
    public function test_index_route_is_bound_to_the_blade_action(): void
    {
        $route = Route::getRoutes()->getByName('admin.urban-goodz.dispatcher-sourcing.index');

        $this->assertNotNull($route, 'The dispatcher-sourcing index route is not registered.');
        $this->assertStringEndsWith(
            '@dashboardBlade',
            $route->getActionName(),
            'The dispatcher-sourcing index URL must render the Blade dashboard, not the JSON dashboard.'
        );
        $this->assertSame('dispatcher-sourcing', $route->uri() === 'admin/urban-goodz/dispatcher-sourcing' ? 'dispatcher-sourcing' : $route->uri());
    }

    /** No browser-facing GET in the group may point at a JSON-returning action. */
    public function test_no_web_get_route_returns_json(): void
    {
        $jsonOnlyActions = [
            'apiDashboard', 'bestLoads', 'bestForDriver', 'savedSearches',
            'searchAllSources', 'saveSearch', 'runSavedSearch', 'deleteSavedSearch',
            'assignLoadToDriver', 'openExternalLoad', 'confirmBooking',
        ];

        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/urban-goodz/dispatcher-sourcing')) {
                continue;
            }
            if (!in_array('GET', $route->methods(), true)) {
                continue;
            }

            $method = str_contains($route->getActionName(), '@')
                ? explode('@', $route->getActionName())[1]
                : '';

            $this->assertNotContains(
                $method,
                $jsonOnlyActions,
                "Browser-facing GET {$route->uri()} is bound to JSON action {$method}()."
            );
        }
    }

    public function test_authorized_admin_gets_html_dashboard_and_not_json(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertViewIs('admin-views.urban-goodz.dispatcher-sourcing.dashboard');

        $body = $response->getContent();

        $this->assertStringNotContainsString(
            '"eligible_drivers"',
            $body,
            'The page is still emitting the raw JSON dashboard payload.'
        );
        $this->assertNull(
            json_decode(trim($body)),
            'The dispatcher-sourcing page body parsed as JSON; it must be an HTML document.'
        );
        $this->assertStringContainsString('<html', $body);
    }

    public function test_dashboard_exposes_the_four_counters_and_key_controls(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);
        $response->assertOk();

        // The four figures that used to be the JSON payload.
        $response->assertViewHas('eligibleDrivers');
        $response->assertViewHas('availableLoads');
        $response->assertViewHas('savedSearchCount');
        $response->assertViewHas('topRecommendations');

        // Navigation into search / saved searches / assignments / driver matches.
        foreach (['search', 'saved-searches', 'best-loads', 'driver-matches'] as $name) {
            $response->assertSee(
                route('admin.urban-goodz.dispatcher-sourcing.' . $name),
                false
            );
        }
    }

    public function test_dashboard_renders_an_empty_state_when_there_is_no_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(self::URI);
        $response->assertOk();
        $response->assertSee('No recommendations available', false);
    }

    /** Every sibling page must also be HTML. */
    public function test_sibling_pages_render_html(): void
    {
        $this->actingAsAdmin();

        $pages = [
            '/dashboard' => 'admin-views.urban-goodz.dispatcher-sourcing.dashboard',
            '/search' => 'admin-views.urban-goodz.dispatcher-sourcing.search',
            '/saved-searches' => 'admin-views.urban-goodz.dispatcher-sourcing.saved-searches',
            '/assignments' => 'admin-views.urban-goodz.dispatcher-sourcing.assignments',
            '/best-loads' => 'admin-views.urban-goodz.dispatcher-sourcing.assignments',
            '/driver-matches' => 'admin-views.urban-goodz.dispatcher-sourcing.driver-matches',
        ];

        foreach ($pages as $suffix => $view) {
            $response = $this->get(self::URI . $suffix);

            $response->assertOk();
            $response->assertViewIs($view);
            $this->assertStringNotContainsString(
                'application/json',
                (string) $response->headers->get('content-type'),
                "{$suffix} returned JSON."
            );
        }
    }

    public function test_unauthenticated_visitor_is_redirected_away(): void
    {
        $response = $this->get(self::URI);

        $response->assertRedirect();
        $this->assertStringNotContainsString(
            '"eligible_drivers"',
            $response->getContent(),
            'Unauthenticated request leaked the dashboard payload.'
        );
    }
}
