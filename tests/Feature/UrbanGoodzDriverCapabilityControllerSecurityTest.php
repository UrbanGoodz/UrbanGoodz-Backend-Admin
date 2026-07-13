<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzDriverCapabilityControllerSecurityTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/Api/UrbanGoodzDriverCapabilityController.php'));
    }

    private function routeSource(): string
    {
        return file_get_contents(base_path('routes/api/v1/urban_goodz.php'));
    }

    private function migrationSource(): string
    {
        return file_get_contents(database_path('migrations/2026_07_09_001200_add_capability_fields_to_delivery_men_table.php'));
    }

    public function test_capability_routes_stay_under_delivery_man_driver_group(): void
    {
        $routes = $this->routeSource();

        $this->assertStringContainsString("'prefix' => 'urban-goodz/driver', 'middleware' => 'dm.api'", $routes);
        foreach ([
            "Route::get('capability-profile'",
            "Route::get('capability-summary'",
            "Route::post('capability-profile/vehicle'",
            "Route::post('capability-profile/cargo'",
            "Route::post('capability-profile/zones'",
            "Route::post('capability-profile/work-types'",
            "Route::post('capability-profile/tags'",
            "Route::post('capability-profile/availability'",
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
    }

    public function test_allowed_capability_tags_are_locked(): void
    {
        $controller = $this->controllerSource();

        foreach ([
            'food_delivery',
            'retail_delivery',
            'business_courier',
            'package_routes',
            'medical_courier',
            'order_anywhere',
            'cargo_van',
            'pickup_truck',
            'box_truck',
            'car',
            'suv',
            'event_runner',
            'rental_support',
        ] as $tag) {
            $this->assertStringContainsString("'{$tag}'", $controller);
        }

        $this->assertStringContainsString('Rule::in(self::CAPABILITY_TAGS)', $controller);
    }

    public function test_migration_is_guarded_and_non_destructive(): void
    {
        $migration = $this->migrationSource();

        $this->assertStringContainsString("Schema::hasTable('delivery_men')", $migration);
        foreach ([
            'vehicle_type',
            'cargo_capacity_notes',
            'max_package_count',
            'max_weight_lbs',
            'has_cargo_space',
            'has_cooler_bag',
            'has_medical_courier_training',
            'has_liftgate',
            'preferred_zones',
            'preferred_work_types',
            'capability_tags',
            'availability_preference',
            'available_for_business_courier',
            'available_for_package_routes',
            'available_for_order_anywhere',
            'available_for_medical_courier',
        ] as $column) {
            $this->assertStringContainsString("Schema::hasColumn('delivery_men', '{$column}')", $migration);
        }

        $this->assertStringNotContainsString('dropIfExists', $migration);
        $this->assertStringNotContainsString('truncate', strtolower($migration));
    }
}
