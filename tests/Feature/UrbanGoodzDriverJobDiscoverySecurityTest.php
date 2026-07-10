<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverJobDiscoverySecurityTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverJobDiscoveryController.php'));
    }

    private function routeSource(): string
    {
        return file_get_contents(base_path('routes/api/v1/urban_goodz.php'));
    }

    public function test_job_discovery_routes_stay_under_delivery_man_driver_group(): void
    {
        $routes = $this->routeSource();

        $this->assertStringContainsString("'prefix' => 'urban-goodz/driver', 'middleware' => 'auth:delivery_man'", $routes);
        foreach ([
            "Route::get('job-discovery',",
            "Route::get('job-discovery/summary',",
            "Route::get('job-discovery/{type}/{id}',",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_controller_uses_authenticated_driver_only(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringContainsString("\$request->user('delivery_man')", $controller);
        $this->assertStringContainsString("abort(401, 'Unauthenticated driver')", $controller);
        $this->assertStringNotContainsString('DeliveryMan::find(', $controller);
        $this->assertStringNotContainsString('whereKey($request', $controller);
        $this->assertStringNotContainsString('findOrFail($request', $controller);
        $this->assertStringNotContainsString('input(\'driver', $controller);
        $this->assertStringNotContainsString('request(\'driver', $controller);
    }

    public function test_job_type_is_allowlisted(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringContainsString("Rule::in(self::DISCOVERY_TYPES)", $controller);
        foreach (['business_courier', 'package_pool', 'dedicated_route'] as $type) {
            $this->assertStringContainsString("'{$type}'", $controller);
        }
    }

    public function test_inaccessible_job_detail_returns_404(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringContainsString('abort(404)', $controller);
        $this->assertStringContainsString("->whereNull('assigned_delivery_man_id')", $controller);
        $this->assertStringContainsString("->whereNull('dedicated_route_id')", $controller);
        $this->assertStringContainsString("->whereNull('assigned_driver_id')", $controller);
    }

    public function test_admin_notes_are_not_returned(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringNotContainsString('admin_notes', $controller);
    }

    public function test_existing_p1_and_p2_routes_preserved(): void
    {
        $routes = $this->routeSource();

        foreach ([
            "Route::get('business-jobs',",
            "Route::post('business-jobs/{jobId}/accept',",
            "Route::get('capability-profile',",
            "Route::post('capability-profile/vehicle',",
            "Route::get('routes',",
            "Route::get('earnings',",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_no_claim_endpoint_built(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringNotContainsString('function claim', $controller);
        $this->assertStringNotContainsString('DB::beginTransaction', $controller);
    }
}
