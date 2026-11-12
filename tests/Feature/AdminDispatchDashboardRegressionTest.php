<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminDispatchDashboardRegressionTest extends TestCase
{
    public function test_dispatch_route_and_controller_define_module_type_before_view_selection(): void
    {
        $route = Route::getRoutes()->getByName('admin.dispatch.dashboard');
        $this->assertNotNull($route);
        $this->assertSame('admin/dispatch', $route->uri());
        $this->assertContains('admin', $route->gatherMiddleware());

        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Http/Controllers/Admin/DashboardController.php'
        );
        $start = strpos($source, 'public function dispatch_dashboard');
        $end = strpos($source, 'public static function urban_goodz_dashboard_data', $start);
        $method = substr($source, $start, $end - $start);
        $assignment = strpos($method, 'module_type = Config::get');
        $view = strpos($method, 'viewName = (empty');

        $this->assertNotFalse($assignment);
        $this->assertNotFalse($view);
        $this->assertLessThan($view, $assignment);
        $this->assertStringContainsString('admin-views.dashboard-dispatch', $method);
    }

    public function test_incomplete_dispatch_list_urls_redirect_to_the_module_dashboard(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Http/Controllers/Admin/OrderController.php'
        );
        $start = strpos($source, 'public function dispatch_list');
        $end = strpos($source, 'public function details', $start);
        $method = substr($source, $start, $end - $start);

        $this->assertStringContainsString('dispatch_list(Request', $method);
        $this->assertStringContainsString('is_numeric', $method);
        $this->assertStringContainsString('searching_for_deliverymen', $method);
        $this->assertStringContainsString('on_going', $method);
        $this->assertStringContainsString('admin.dispatch.dashboard', $method);
    }

    public function test_dashboard_tiles_link_to_real_lists_and_every_counted_status_has_one(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin-views/dashboard-dispatch.blade.php');
        $this->assertStringNotContainsString('class="order--card h-100" href="#"', $view, 'a dashboard tile is a dead link');
        foreach (['searching_for_deliverymen', 'accepted', 'picked_up'] as $status) {
            $this->assertStringContainsString("route('admin.dispatch.list', ['all', '{$status}'])", $view);
        }

        $admin = \App\Models\Admin::create([
            'f_name' => 'Disp', 'l_name' => 'Admin', 'email' => 'disp-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'disp-token',
        ]);
        foreach (['searching_for_deliverymen', 'accepted', 'picked_up', 'on_going'] as $status) {
            $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => 'disp-token'])
                ->get("/admin/dispatch/list/all/{$status}")->assertOk();
        }
        // Anything else still bounces back to the dashboard.
        $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => 'disp-token'])
            ->get('/admin/dispatch/list/all/bogus')->assertRedirect(route('admin.dispatch.dashboard'));
    }
}
