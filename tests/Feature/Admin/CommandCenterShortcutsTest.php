<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the Urban Goodz Command Center shortcut grid on the admin dashboard.
 *
 * Why this exists: production served HTTP 500 on /admin because
 * dashboard.blade.php called route('admin.delivery-man.list') and
 * route('admin.report.item-wise-report'), neither of which is a registered
 * route name. A missing route name in a Blade `route()` call is a hard
 * RouteNotFoundException that takes down the whole dashboard, so it must be
 * caught in CI rather than by a user.
 *
 * These assertions are intentionally DB-free: they verify routing and template
 * wiring, which is where this class of defect actually lives.
 */
class CommandCenterShortcutsTest extends TestCase
{
    /** Blade file that renders the Command Center. */
    protected string $bladePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bladePath = resource_path('views/admin-views/dashboard.blade.php');
    }

    /**
     * Every shortcut label on the Command Center and the route name it targets.
     */
    public static function shortcutProvider(): array
    {
        return [
            'Business Clients' => ['admin.urban-goodz.business-clients.index'],
            'Command Center'   => ['admin.urban-goodz.index'],
            'Vendors'          => ['admin.store.list'],
            'Drivers'          => ['admin.users.delivery-man.list'],
            'Orders'           => ['admin.order.list'],
            'Dispatcher'       => ['admin.urban-goodz.dispatcher-sourcing.dashboard'],
            'Load Board'       => ['admin.urban-goodz.load-board.index'],
            'Load Sourcing'    => ['admin.urban-goodz.load-sourcing.index'],
            'Driver Pricing'   => ['admin.urban-goodz.driver-pricing.index'],
            'Payment Center'   => ['admin.urban-goodz.payments.index'],
            'Dedicated Routes' => ['admin.urban-goodz.dedicated-routes.index'],
            'Driver Payouts'   => ['admin.urban-goodz.driver-payouts.index'],
            'Fashion Fit'      => ['admin.urban-goodz.fashion-fit.index'],
            'Order Anywhere'   => ['admin.urban-goodz.order-anywhere.index'],
            'AI Concierge'     => ['admin.urban-goodz.ai-concierge.conversations'],
            'Creator Commerce' => ['admin.urban-goodz.creator.dashboard'],
            'Services'         => ['admin.urban-goodz.service-requests.index'],
            'Notifications'    => ['admin.notification.add-new'],
            'Reports'          => ['admin.transactions.report.item-wise-report'],
            'Settings'         => ['admin.business-settings.business-setup'],
        ];
    }

    /**
     * @dataProvider shortcutProvider
     */
    public function test_every_command_center_shortcut_route_is_registered(string $routeName): void
    {
        $this->assertTrue(
            Route::has($routeName),
            "Command Center shortcut targets route [{$routeName}], which is not registered. "
            ."A Blade route() call to a missing name throws RouteNotFoundException and 500s the entire dashboard."
        );
    }

    /**
     * Catches the exact production defect: a route() call in the Command Center
     * Blade that does not correspond to any registered route name.
     */
    public function test_no_route_call_in_dashboard_blade_references_an_unregistered_route(): void
    {
        $this->assertFileExists($this->bladePath);
        $blade = file_get_contents($this->bladePath);

        preg_match_all("/route\(\s*'([^']+)'/", $blade, $matches);
        $referenced = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($referenced, 'Expected the dashboard Blade to reference named routes.');

        $missing = array_values(array_filter(
            $referenced,
            fn (string $name) => !Route::has($name)
        ));

        $this->assertSame(
            [],
            $missing,
            'dashboard.blade.php references unregistered route name(s): '.implode(', ', $missing)
        );
    }

    /**
     * The Business Portal Login shortcut is a plain URL to a separate guard, not
     * an admin route. Assert it stays present so it cannot be dropped silently.
     */
    public function test_business_portal_login_shortcut_is_present(): void
    {
        $blade = file_get_contents($this->bladePath);
        $this->assertStringContainsString(
            "url('/business/login')",
            $blade,
            'The Business Portal Login shortcut is missing from the Command Center.'
        );
    }

    /**
     * Admin dashboard must be protected by the admin guard middleware, never
     * reachable anonymously.
     */
    public function test_admin_dashboard_is_protected_by_admin_middleware(): void
    {
        $this->assertTrue(Route::has('admin.dashboard'));

        $middleware = Route::getRoutes()->getByName('admin.dashboard')->gatherMiddleware();

        $this->assertContains(
            'admin',
            $middleware,
            'admin.dashboard must be guarded by the "admin" middleware.'
        );
    }

    /**
     * Unauthenticated access to the dashboard must redirect to login, never
     * render the page or return raw data.
     */
    public function test_dashboard_redirects_guests_to_login(): void
    {
        // This is the only assertion in this file that needs a live database
        // (the admin login-url lookup reads data_settings). Skip rather than
        // fail when no test database is reachable, so the DB-free routing
        // guarantees above still run everywhere.
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No test database reachable: '.$e->getMessage());
        }

        $response = $this->get('/admin');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login',
            (string) $response->headers->get('Location'),
            'Guests hitting /admin must be redirected to a login screen.'
        );
    }
}
