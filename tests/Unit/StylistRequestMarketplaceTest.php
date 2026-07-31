<?php

namespace Tests\Unit;

use App\Models\UrbanGoodzStylistBid;
use App\Models\UrbanGoodzStylistMeasurementGrant;
use Tests\TestCase;

class StylistRequestMarketplaceTest extends TestCase
{
    private function grant(array $attributes = []): UrbanGoodzStylistMeasurementGrant
    {
        $grant = new UrbanGoodzStylistMeasurementGrant();
        $grant->measurements_allowed = true;
        $grant->photos_allowed = false;
        $grant->granted_at = now();
        $grant->revoked_at = null;
        $grant->expires_at = null;
        foreach ($attributes as $key => $value) {
            $grant->{$key} = $value;
        }

        return $grant;
    }

    public function test_a_fresh_grant_shares_measurements_but_never_photos(): void
    {
        $grant = $this->grant();

        $this->assertTrue($grant->allowsMeasurements());
        $this->assertFalse($grant->allowsPhotos(), 'Body photos must never be shared automatically.');
    }

    public function test_revoking_a_grant_removes_all_access(): void
    {
        $grant = $this->grant(['photos_allowed' => true, 'revoked_at' => now()->subMinute()]);

        $this->assertFalse($grant->isActive());
        $this->assertFalse($grant->allowsMeasurements());
        $this->assertFalse($grant->allowsPhotos());
    }

    public function test_an_expired_grant_removes_all_access(): void
    {
        $grant = $this->grant(['photos_allowed' => true, 'expires_at' => now()->subDay()]);

        $this->assertFalse($grant->isActive());
        $this->assertFalse($grant->allowsMeasurements());
        $this->assertFalse($grant->allowsPhotos());
    }

    public function test_a_future_expiry_keeps_the_grant_active(): void
    {
        $grant = $this->grant(['expires_at' => now()->addDay()]);

        $this->assertTrue($grant->isActive());
        $this->assertTrue($grant->allowsMeasurements());
    }

    public function test_photo_access_requires_an_explicit_opt_in(): void
    {
        $grant = $this->grant(['photos_allowed' => true]);
        $this->assertTrue($grant->allowsPhotos());

        // Turning measurements off must not leave photos reachable by accident.
        $revoked = $this->grant(['photos_allowed' => true, 'revoked_at' => now()]);
        $this->assertFalse($revoked->allowsPhotos());
    }

    public function test_only_live_bids_can_be_selected(): void
    {
        foreach (['submitted', 'revised'] as $status) {
            $bid = new UrbanGoodzStylistBid();
            $bid->status = $status;
            $bid->expires_at = null;
            $this->assertTrue($bid->isSelectable(), "A {$status} bid should be selectable.");
        }

        foreach (['withdrawn', 'rejected', 'accepted', 'superseded'] as $status) {
            $bid = new UrbanGoodzStylistBid();
            $bid->status = $status;
            $bid->expires_at = null;
            $this->assertFalse($bid->isSelectable(), "A {$status} bid must not be selectable.");
        }
    }

    public function test_an_expired_bid_cannot_be_selected(): void
    {
        $bid = new UrbanGoodzStylistBid();
        $bid->status = 'submitted';
        $bid->expires_at = now()->subHour();

        $this->assertFalse($bid->isSelectable());
    }

    public function test_measurement_access_service_gates_photos_separately_from_measurements(): void
    {
        $source = file_get_contents(app_path('Services/StylistRequests/StylistMeasurementAccessService.php'));

        // photosFor must check allowsPhotos, not allowsMeasurements.
        $this->assertStringContainsString('$grant->allowsPhotos()', $source);
        $this->assertStringContainsString('$grant->allowsMeasurements()', $source);
        // Every read path must audit.
        $this->assertStringContainsString("'stylist_measurements_viewed'", $source);
        $this->assertStringContainsString("'stylist_photos_viewed'", $source);
        // Ownership check that prevents reaching an unrelated profile.
        $this->assertStringContainsString('$profile->customer_id === (int) $request->user_id', $source);
        // Only approved profiles may be shared.
        $this->assertStringContainsString('$profile->approved_at !== null', $source);
    }

    public function test_stylist_grants_do_not_reuse_the_fashion_fit_grant_table(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_07_30_190000_create_stylist_request_marketplace.php'
        ));

        // Reusing fashion_fit_access_grants would let a stylist request id
        // collide with a Fashion Fit request id and cross-authorize body data.
        $this->assertStringContainsString('urban_goodz_stylist_measurement_grants', $migration);
        $this->assertStringNotContainsString("Schema::create('fashion_fit_access_grants'", $migration);
        $this->assertStringContainsString("\$table->boolean('photos_allowed')->default(false)", $migration);
    }

    public function test_marketplace_migration_is_create_only_and_guarded(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_07_30_190000_create_stylist_request_marketplace.php'
        ));

        $this->assertStringContainsString("Schema::hasTable('urban_goodz_stylist_requests')", $migration);
        $this->assertStringContainsString("Schema::hasTable('urban_goodz_stylist_bids')", $migration);
        $this->assertStringNotContainsString('migrate:fresh', $migration);
        $this->assertStringNotContainsString('truncate(', strtolower($migration));
    }

    public function test_customer_and_stylist_routes_are_registered(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/stylist_requests.php'));

        foreach ([
            "Route::post('/', [StylistRequestCustomerController::class, 'store']",
            "Route::post('{stylistRequest}/publish'",
            "Route::post('{stylistRequest}/invite'",
            "Route::get('{stylistRequest}/bids'",
            "Route::post('{stylistRequest}/bids/{bid}/select'",
            "Route::post('{stylistRequest}/access/photos'",
            "Route::delete('{stylistRequest}/access'",
            "Route::get('matching'",
            "Route::post('{stylistRequest}/bids', [VendorStylistRequestController::class, 'bid']",
            "Route::get('{stylistRequest}/measurements'",
            "Route::get('{stylistRequest}/photos'",
        ] as $route) {
            $this->assertStringContainsString($route, $routes, "Missing route: {$route}");
        }

        $provider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));
        $this->assertStringContainsString('routes/api/v1/stylist_requests.php', $provider);
    }

    public function test_a_stylist_bid_supersedes_rather_than_overwrites(): void
    {
        $controller = file_get_contents(app_path(
            'Http/Controllers/Api/V1/Vendor/StylistRequestController.php'
        ));

        $this->assertStringContainsString("'status' => 'superseded'", $controller);
        $this->assertStringContainsString("'supersedes_bid_id' => \$previous?->id", $controller);
    }
}
