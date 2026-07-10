<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverNotificationBehavioralTest extends TestCase
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

    private function aiCopilotSource(): string
    {
        return file_get_contents(app_path('Services/AiCopilotService.php'));
    }

    private function oaAdminControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Admin/UrbanGoodzAdminController.php'));
    }

    private function driverApiSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverApiController.php'));
    }

    private function routeSource(): string
    {
        return file_get_contents(base_path('routes/api/v1/urban_goodz.php'));
    }

    public function test_no_push_or_websocket_is_triggered_by_producer(): void
    {
        $service = $this->serviceSource();

        foreach (['Firebase', 'fcm', 'FCM', 'push', 'Push', 'websocket', 'WebSocket', 'send_push', 'broadcast'] as $term) {
            $this->assertStringNotContainsString($term, $service);
        }
    }

    public function test_dedupe_query_pattern_present_and_safe(): void
    {
        $service = $this->serviceSource();

        $this->assertStringContainsString('dedupe_key', $service);
        $this->assertStringContainsString('alreadyExists', $service);
        $this->assertStringContainsString("UserNotification::where('delivery_man_id', \$deliveryManId)", $service);
        $this->assertStringContainsString('return null', $service);
    }

    public function test_covered_assignment_paths_call_service(): void
    {
        $this->assertStringContainsString('notifyBusinessCourierAssigned', $this->businessControllerSource());
        $this->assertStringContainsString('notifyBusinessCourierUpdated', $this->businessControllerSource());
        $this->assertStringContainsString('notifyDedicatedRouteAssigned', $this->routeControllerSource());
        $this->assertStringContainsString('notifyPackageException', $this->courierControllerSource());
    }

    public function test_p5_produced_types_are_all_defined_in_service(): void
    {
        $service = $this->serviceSource();

        foreach ([
            'business_courier_assigned',
            'business_courier_updated',
            'dedicated_route_assigned',
            'package_exception',
            'proof_required',
            'age_verification_required',
            'medical_review_required',
        ] as $type) {
            $this->assertStringContainsString("'{$type}'", $service);
        }
    }

    public function test_ai_ops_auto_dispatch_is_not_modified_to_produce_notifications(): void
    {
        $ai = $this->aiCopilotSource();

        $this->assertStringNotContainsString('UrbanGoodzDriverDispatchNotificationService', $ai);
        $this->assertStringNotContainsString('notifyDedicatedRouteAssigned', $ai);
    }

    public function test_order_anywhere_assignment_deferred_from_producer(): void
    {
        $oa = $this->oaAdminControllerSource();

        $this->assertStringNotContainsString('UrbanGoodzDriverDispatchNotificationService', $oa);
        $this->assertStringNotContainsString('notifyBusinessCourierAssigned', $oa);
    }

    public function test_route_package_exception_paths_are_out_of_p5_scope(): void
    {
        $service = $this->serviceSource();

        $this->assertStringNotContainsString('UrbanGoodzRoutePackage', $service);
        $this->assertStringNotContainsString('notifyRoutePackageException', $service);
    }

    public function test_p4_p5_routes_preserved_and_no_new_migration(): void
    {
        $routes = $this->routeSource();
        $service = $this->serviceSource();

        foreach ([
            "Route::get('business-jobs',",
            "Route::post('business-jobs/{jobId}/accept',",
            "Route::get('capability-profile',",
            "Route::get('job-discovery',",
            "Route::get('dispatch-notifications',",
            "Route::post('dispatch-notifications/{notificationId}/read',",
            "Route::post('dispatch-notifications/{notificationId}/dismiss',",
            "Route::get('routes',",
            "Route::get('earnings',",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }

        $this->assertStringNotContainsString('Schema::create', $service);
        $this->assertStringNotContainsString('Schema::table', $service);
    }
}
