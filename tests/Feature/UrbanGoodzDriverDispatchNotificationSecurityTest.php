<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverDispatchNotificationSecurityTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverDispatchNotificationController.php'));
    }

    private function routeSource(): string
    {
        return file_get_contents(base_path('routes/api/v1/urban_goodz.php'));
    }

    public function test_dispatch_notification_routes_stay_under_delivery_man_driver_group(): void
    {
        $routes = $this->routeSource();

        $this->assertStringContainsString("'prefix' => 'urban-goodz/driver', 'middleware' => 'auth:delivery_man'", $routes);
        foreach ([
            "Route::get('dispatch-notifications',",
            "Route::get('dispatch-notifications/unread-count',",
            "Route::post('dispatch-notifications/read-all',",
            "Route::post('dispatch-notifications/{notificationId}/read',",
            "Route::post('dispatch-notifications/{notificationId}/dismiss',",
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

    public function test_notifications_are_scoped_to_authenticated_driver(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringContainsString("->where('delivery_man_id', \$driver->id)", $controller);
        $this->assertStringContainsString('abort(404)', $controller);
    }

    public function test_notification_id_is_validated_and_404s_when_inaccessible(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringContainsString("'id' => ['required', 'integer', 'min:1']", $controller);
        $this->assertStringContainsString('UserNotification::query()', $controller);
    }

    public function test_sensitive_fields_are_not_returned(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringNotContainsString('admin_notes', $controller);
        $this->assertStringNotContainsString('authorized_amount', $controller);
        $this->assertStringNotContainsString('final_amount', $controller);
        $this->assertStringNotContainsString('quote_amount', $controller);
        $this->assertStringNotContainsString('payout', $controller);
    }

    public function test_existing_p1_p2_p3_routes_preserved(): void
    {
        $routes = $this->routeSource();

        foreach ([
            "Route::get('business-jobs',",
            "Route::post('business-jobs/{jobId}/accept',",
            "Route::get('capability-profile',",
            "Route::get('job-discovery',",
            "Route::get('routes',",
            "Route::get('earnings',",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_no_migration_or_new_table_created(): void
    {
        $controller = $this->controllerSource();

        $this->assertStringNotContainsString('Schema::create', $controller);
        $this->assertStringNotContainsString('Schema::table', $controller);
    }
}
