<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServicesMarketplaceReleaseContractTest extends TestCase
{
    public function test_release_migration_is_additive_and_tracks_booking_service_area(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_07_30_120000_release_services_marketplace.php'
        ));

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_areas')", $source);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_quotes')", $source);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_service_requests', 'service_area_id')", $source);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_service_requests', 'amount_paid_minor')", $source);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_service_requests', 'refunded_amount_minor')", $source);
        $this->assertStringNotContainsString('migrate:fresh', $source);
        $this->assertStringNotContainsString('truncate(', strtolower($source));
    }

    public function test_customer_provider_and_admin_release_routes_are_exposed(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/service_bookings.php'));

        foreach ([
            "Route::get('categories'",
            "Route::get('providers/{provider}/services/{service}/slots'",
            "Route::post('{booking}/accept-quote'",
            "Route::post('{booking}/payment'",
            "Route::post('{booking}/review'",
            "Route::post('onboarding/submit'",
            "Route::put('service-areas'",
            "Route::get('earnings'",
            "Route::get('dashboard'",
            "Route::post('bookings/{booking}/refund'",
            "Route::get('audit'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function test_provider_history_is_retired_instead_of_deleted(): void
    {
        $controller = file_get_contents(app_path(
            'Http/Controllers/Api/V1/Vendor/ServiceBookingController.php'
        ));

        $this->assertStringContainsString("\$provider->areas()->update(['is_active'=>false])", $controller);
        $this->assertStringContainsString("\$service->update(['is_active'=>false])", $controller);
        $this->assertStringNotContainsString('$provider->areas()->delete()', $controller);
        $this->assertStringNotContainsString('$service->delete()', $controller);
    }

    public function test_admin_approval_requires_complete_provider_onboarding(): void
    {
        $controller = file_get_contents(app_path(
            'Http/Controllers/Api/V1/Admin/ServiceBookingController.php'
        ));

        $this->assertStringContainsString('$provider->submitted_at', $controller);
        $this->assertStringContainsString("\$provider->services()->where('is_active',true)->exists()", $controller);
        $this->assertStringContainsString("\$provider->availability()->where('is_active',true)->exists()", $controller);
        $this->assertStringContainsString("\$provider->areas()->where('is_active',true)->exists()", $controller);
    }

    public function test_payment_refunds_are_idempotent_and_reconciled_from_transactions(): void
    {
        $service = file_get_contents(app_path(
            'Services/ServiceBookings/ServiceBookingRefundService.php'
        ));

        $this->assertStringContainsString('$alreadyCompletedForKey', $service);
        $this->assertStringContainsString('synchronizeRefundTotal', $service);
        $this->assertStringContainsString("->where('transaction_type', 'refund')", $service);
        $this->assertStringContainsString("->where('internal_status', 'succeeded')", $service);
    }
}
