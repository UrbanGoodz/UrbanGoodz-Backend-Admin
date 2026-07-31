<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzServiceArea;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Services\ServiceBookings\ServiceProviderDiscoveryService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ServicesProductionCompletionTest extends TestCase
{
    private const HOUSTON_LAT = 29.7604;
    private const HOUSTON_LON = -95.3698;
    private const DALLAS_LAT = 32.7767;
    private const DALLAS_LON = -96.7970;

    private function area(float $lat, float $lon, ?int $radius): UrbanGoodzServiceArea
    {
        $area = new UrbanGoodzServiceArea();
        $area->latitude = $lat;
        $area->longitude = $lon;
        $area->radius_miles = $radius;
        $area->is_active = true;

        return $area;
    }

    private function providerWithAreas(array $areas): UrbanGoodzServiceProvider
    {
        $provider = new UrbanGoodzServiceProvider();
        $provider->setRelation('areas', new Collection($areas));

        return $provider;
    }

    public function test_distance_between_houston_and_dallas_is_accurate(): void
    {
        $miles = ServiceProviderDiscoveryService::distanceMiles(
            self::HOUSTON_LAT,
            self::HOUSTON_LON,
            self::DALLAS_LAT,
            self::DALLAS_LON
        );

        // Great-circle Houston -> Dallas is ~225 miles.
        $this->assertEqualsWithDelta(225.0, $miles, 5.0);
    }

    public function test_distance_is_zero_for_identical_points(): void
    {
        $this->assertEqualsWithDelta(
            0.0,
            ServiceProviderDiscoveryService::distanceMiles(self::HOUSTON_LAT, self::HOUSTON_LON, self::HOUSTON_LAT, self::HOUSTON_LON),
            0.0001
        );
    }

    public function test_area_covers_point_only_within_its_radius(): void
    {
        // A 30-mile Houston area does not reach Dallas, a 300-mile one does.
        $this->assertFalse(ServiceProviderDiscoveryService::areaCoversPoint(
            self::HOUSTON_LAT, self::HOUSTON_LON, 30, self::DALLAS_LAT, self::DALLAS_LON
        ));
        $this->assertTrue(ServiceProviderDiscoveryService::areaCoversPoint(
            self::HOUSTON_LAT, self::HOUSTON_LON, 300, self::DALLAS_LAT, self::DALLAS_LON
        ));
    }

    public function test_area_without_coordinates_or_radius_never_covers_a_point(): void
    {
        $this->assertFalse(ServiceProviderDiscoveryService::areaCoversPoint(null, null, 50, self::HOUSTON_LAT, self::HOUSTON_LON));
        $this->assertFalse(ServiceProviderDiscoveryService::areaCoversPoint(self::HOUSTON_LAT, self::HOUSTON_LON, null, self::HOUSTON_LAT, self::HOUSTON_LON));
        $this->assertFalse(ServiceProviderDiscoveryService::areaCoversPoint(self::HOUSTON_LAT, self::HOUSTON_LON, 0, self::HOUSTON_LAT, self::HOUSTON_LON));
    }

    public function test_attach_distances_excludes_providers_that_do_not_cover_the_point(): void
    {
        $discovery = new ServiceProviderDiscoveryService();

        $covering = $this->providerWithAreas([$this->area(self::HOUSTON_LAT, self::HOUSTON_LON, 25)]);
        // Based in Dallas with a small radius: must not surface for a Houston search.
        $notCovering = $this->providerWithAreas([$this->area(self::DALLAS_LAT, self::DALLAS_LON, 20)]);

        $matched = $discovery->attachDistances([$covering, $notCovering], self::HOUSTON_LAT, self::HOUSTON_LON);

        $this->assertCount(1, $matched);
        $this->assertSame($covering, $matched[0]);
        $this->assertEqualsWithDelta(0.0, $matched[0]->distance_miles, 0.01);
    }

    public function test_attach_distances_sorts_nearest_first_and_uses_nearest_covering_area(): void
    {
        $discovery = new ServiceProviderDiscoveryService();

        // ~0.1 degrees of latitude is roughly 7 miles.
        $near = $this->providerWithAreas([$this->area(self::HOUSTON_LAT + 0.1, self::HOUSTON_LON, 50)]);
        $far = $this->providerWithAreas([
            $this->area(self::HOUSTON_LAT + 1.0, self::HOUSTON_LON, 200),
            $this->area(self::HOUSTON_LAT + 0.5, self::HOUSTON_LON, 200),
        ]);

        $matched = $discovery->attachDistances([$far, $near], self::HOUSTON_LAT, self::HOUSTON_LON);

        $this->assertCount(2, $matched);
        $this->assertSame($near, $matched[0], 'The nearest provider must sort first.');
        $this->assertLessThan($matched[1]->distance_miles, $matched[0]->distance_miles);
        // The far provider must report its *nearest* covering area (~34mi), not the ~69mi one.
        $this->assertEqualsWithDelta(34.5, $matched[1]->distance_miles, 2.0);
    }

    public function test_attach_distances_respects_an_explicit_search_limit(): void
    {
        $discovery = new ServiceProviderDiscoveryService();
        // Covers the point by its own 200-mile radius, but sits ~69 miles away.
        $provider = $this->providerWithAreas([$this->area(self::HOUSTON_LAT + 1.0, self::HOUSTON_LON, 200)]);

        $this->assertCount(1, $discovery->attachDistances([$provider], self::HOUSTON_LAT, self::HOUSTON_LON, 100));
        $this->assertCount(0, $discovery->attachDistances([$provider], self::HOUSTON_LAT, self::HOUSTON_LON, 10));
    }

    public function test_provider_commission_falls_back_to_the_platform_default(): void
    {
        config()->set('service_bookings.platform_fee_percent', 15);

        $provider = new UrbanGoodzServiceProvider();
        $provider->commission_percent = null;
        $this->assertSame(15.0, $provider->commissionPercent());

        $provider->commission_percent = 8.5;
        $this->assertSame(8.5, $provider->commissionPercent());
    }

    public function test_provider_commission_is_clamped_to_a_valid_percentage(): void
    {
        $provider = new UrbanGoodzServiceProvider();
        $provider->commission_percent = 250;
        $this->assertSame(100.0, $provider->commissionPercent());

        $provider->commission_percent = -10;
        $this->assertSame(0.0, $provider->commissionPercent());
    }

    public function test_zero_commission_override_is_honoured_and_not_treated_as_unset(): void
    {
        config()->set('service_bookings.platform_fee_percent', 15);

        $provider = new UrbanGoodzServiceProvider();
        $provider->commission_percent = 0;

        // A 0% override is a real business decision and must not fall back to 15%.
        $this->assertSame(0.0, $provider->commissionPercent());
    }

    public function test_payable_amount_applies_adjustments_and_never_goes_negative(): void
    {
        $earning = new UrbanGoodzServiceProviderEarning();
        $earning->provider_amount_minor = 10000;
        $earning->adjustment_minor = 0;
        $this->assertSame(10000, $earning->payableAmountMinor());

        $earning->adjustment_minor = -2500;
        $this->assertSame(7500, $earning->payableAmountMinor());

        $earning->adjustment_minor = 1500;
        $this->assertSame(11500, $earning->payableAmountMinor());

        $earning->adjustment_minor = -99999;
        $this->assertSame(0, $earning->payableAmountMinor());
    }

    public function test_completion_migration_is_additive_only(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_07_30_180000_services_production_completion.php'
        ));

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_provider_portfolio_items')", $source);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_disputes')", $source);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_service_admin_audits')", $source);
        $this->assertStringContainsString("Schema::hasColumn('urban_goodz_service_providers', 'commission_percent')", $source);
        $this->assertStringNotContainsString('migrate:fresh', $source);
        $this->assertStringNotContainsString('truncate(', strtolower($source));
        $this->assertStringNotContainsString('dropcolumn', str_replace(' ', '', strtolower(substr($source, 0, (int) strpos($source, 'public function down')))));
    }

    public function test_new_customer_provider_and_admin_routes_are_registered(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/service_bookings.php'));

        foreach ([
            "Route::post('{booking}/refund-request'",
            "Route::get('disputes/mine'",
            "Route::get('portfolio'",
            "Route::post('portfolio'",
            "Route::put('portfolio/{item}'",
            "Route::delete('portfolio/{item}'",
            "Route::put('providers/{provider}/commission'",
            "Route::get('disputes'",
            "Route::post('disputes/{dispute}/resolve'",
            "Route::post('earnings/{earning}/adjust'",
            "Route::post('earnings/{earning}/settle'",
            "Route::post('earnings/settle-batch'",
            "Route::get('admin-audit'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, "Missing route: {$route}");
        }
    }

    public function test_portfolio_items_are_retired_rather_than_deleted(): void
    {
        $controller = file_get_contents(app_path(
            'Http/Controllers/Api/V1/Vendor/ServiceBookingController.php'
        ));

        $this->assertStringContainsString("\$item->update(['is_active' => false])", $controller);
        $this->assertStringNotContainsString('$item->delete()', $controller);
    }

    public function test_dispute_refunds_reuse_the_idempotent_refund_service(): void
    {
        $controller = file_get_contents(app_path(
            'Http/Controllers/Api/V1/Admin/ServiceBookingController.php'
        ));

        // A dispute must never write payment rows directly.
        $this->assertStringContainsString('$refunds->refund($booking, $amount, $data[\'idempotency_key\'])', $controller);
        $this->assertStringContainsString("'idempotency_key' => 'required_if:resolution,refunded|string|max:100'", $controller);
    }
}
