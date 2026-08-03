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
}
