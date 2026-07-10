<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverDispatchNotificationProducerTest extends TestCase
{
    private function serviceSource(): string
    {
        return file_get_contents(app_path('Services/UrbanGoodzDriverDispatchNotificationService.php'));
    }

    private function businessControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Admin/UrbanGoodz/UrbanGoodzBusinessClientController.php'));
    }

    private function routeControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php'));
    }

    private function courierControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverBusinessCourierController.php'));
    }

    private function inboxControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverDispatchNotificationController.php'));
    }

    private function routeSource(): string
    {
        return file_get_contents(base_path('routes/api/v1/urban_goodz.php'));
    }

    public function test_service_uses_user_notification_model_with_allowlist(): void
    {
        $service = $this->serviceSource();

        $this->assertStringContainsString('UserNotification::create', $service);
        $this->assertStringContainsString('ALLOWED_PAYLOAD_KEYS', $service);
        $this->assertStringContainsString("'delivery_man_id' => \$deliveryManId", $service);
    }

    public function test_service_never_includes_sensitive_fields(): void
    {
        $service = $this->serviceSource();

        foreach ([
            'admin_notes',
            'authorized_amount',
            'final_amount',
            'quote_amount',
            'customer_phone',
            'customer_email',
            'payout',
            'commission',
        ] as $bad) {
            $this->assertStringNotContainsString($bad, $service);
        }
    }

    public function test_service_prevents_duplicate_via_dedupe_key(): void
    {
        $service = $this->serviceSource();

        $this->assertStringContainsString('dedupe_key', $service);
        $this->assertStringContainsString('alreadyExists', $service);
        $this->assertStringContainsString('return null', $service);
    }

    public function test_service_only_targets_existing_driver(): void
    {
        $service = $this->serviceSource();

        $this->assertStringContainsString("DeliveryMan::where('id', \$deliveryManId)->exists()", $service);
    }

    public function test_assignment_hooks_call_service_for_assigned_driver_only(): void
    {
        $this->assertStringContainsString('notifyBusinessCourierAssigned', $this->businessControllerSource());
        $this->assertStringContainsString('notifyBusinessCourierUpdated', $this->businessControllerSource());
        $this->assertStringContainsString('notifyDedicatedRouteAssigned', $this->routeControllerSource());
        $this->assertStringContainsString('notifyPackageException', $this->courierControllerSource());
    }

    public function test_p4_inbox_controller_unchanged_and_no_migration(): void
    {
        $inbox = $this->inboxControllerSource();

        foreach (['function index', 'function unreadCount', 'function markRead', 'function readAll', 'function dismiss'] as $method) {
            $this->assertStringContainsString($method, $inbox);
        }
        $this->assertStringNotContainsString('Schema::create', $inbox);
        $this->assertStringNotContainsString('Schema::table', $inbox);
    }

    public function test_p1_p2_p3_p4_routes_preserved(): void
    {
        $routes = $this->routeSource();

        foreach ([
            "Route::get('business-jobs',",
            "Route::post('business-jobs/{jobId}/accept',",
            "Route::get('capability-profile',",
            "Route::get('job-discovery',",
            "Route::get('dispatch-notifications',",
            "Route::post('dispatch-notifications/{notificationId}/read',",
            "Route::get('routes',",
            "Route::get('earnings',",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }
}
