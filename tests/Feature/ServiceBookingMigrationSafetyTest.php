<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceBookingMigrationSafetyTest extends TestCase
{
    private function migrationSource(): string
    {
        return file_get_contents(
            database_path('migrations/2026_07_12_130000_complete_service_booking_workflow.php')
        );
    }

    private function deliveryManVehiclesMigration(): string
    {
        return file_get_contents(
            database_path('migrations/2026_07_13_000001_create_delivery_man_vehicles_table.php')
        );
    }

    private function driverCertificationsMigration(): string
    {
        return file_get_contents(
            database_path('migrations/2026_07_13_000002_create_driver_certifications_table.php')
        );
    }

    private function vendorNotificationsMigration(): string
    {
        return file_get_contents(
            database_path('migrations/2026_07_13_000003_create_vendor_notifications_table.php')
        );
    }

    private function earnMoneyApplicationsMigration(): string
    {
        return file_get_contents(
            database_path('migrations/2026_07_13_000004_add_delivery_man_id_and_applied_at_to_earn_money_applications_table.php')
        );
    }

    public function test_130000_migration_uses_has_table_guards(): void
    {
        $src = $this->migrationSource();

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_providers')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_services')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_availability')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_requests')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_booking_events')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_provider_earnings')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_reviews')", $src);
    }

    public function test_130000_migration_checks_columns_before_adding(): void
    {
        $src = $this->migrationSource();

        foreach ([
            'vendor_id', 'approval_status', 'location_modes', 'rating', 'rating_count',
            'user_id', 'provider_id', 'provider_service_id', 'location_mode', 'location_details',
            'requested_start_at', 'scheduled_at', 'quoted_amount_minor', 'deposit_amount_minor',
            'currency', 'provider_notes', 'cancellation_reason', 'payment_status',
            'accepted_at', 'completed_at',
        ] as $column) {
            $this->assertStringContainsString(
                "'{$column}'",
                $src,
                "Migration must reference column: {$column}"
            );
        }
    }

    public function test_130000_migration_has_no脆弱_after_clauses(): void
    {
        $src = $this->migrationSource();
        $this->assertStringNotContainsString('->after(', $src);
    }

    public function test_130000_create_tables_use_has_table_guard(): void
    {
        $src = $this->migrationSource();

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_services')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_availability')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_booking_events')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_provider_earnings')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_reviews')", $src);
    }

    public function test_130000_down_method_is_defensive(): void
    {
        $src = $this->migrationSource();

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_reviews')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_provider_earnings')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_booking_events')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_requests')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_availability')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_services')", $src);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_providers')", $src);
    }

    public function test_130000_no_destructive_operations(): void
    {
        $src = $this->migrationSource();
        $this->assertStringNotContainsString('migrate:fresh', $src);
        $this->assertStringNotContainsString('db:wipe', $src);
        $this->assertStringNotContainsString('truncate', strtolower($src));
    }

    public function test_delivery_man_vehicles_has_has_table_guard(): void
    {
        $src = $this->deliveryManVehiclesMigration();
        $this->assertStringContainsString("Schema::hasTable('delivery_man_vehicles')", $src);
    }

    public function test_driver_certifications_has_has_table_guard(): void
    {
        $src = $this->driverCertificationsMigration();
        $this->assertStringContainsString("Schema::hasTable('driver_certifications')", $src);
    }

    public function test_vendor_notifications_has_has_table_guard(): void
    {
        $src = $this->vendorNotificationsMigration();
        $this->assertStringContainsString("Schema::hasTable('vendor_notifications')", $src);
    }

    public function test_earn_money_has_table_guard(): void
    {
        $src = $this->earnMoneyApplicationsMigration();
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_earn_money_applications')", $src);
    }

    public function test_earn_money_columns_guarded(): void
    {
        $src = $this->earnMoneyApplicationsMigration();
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_earn_money_applications', 'delivery_man_id')", $src);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_earn_money_applications', 'applied_at')", $src);
    }

    public function test_earn_money_down_method_is_defensive(): void
    {
        $src = $this->earnMoneyApplicationsMigration();
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_earn_money_applications', 'delivery_man_id')", $src);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_earn_money_applications', 'applied_at')", $src);
    }
}
