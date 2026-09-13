<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzRoutePackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A driver choosing where the run ends by typing an address.
 *
 * The geocoder is bounded to a circle around the route's pickup. That bound is
 * the safety model, not the provider's confidence score - unbounded, Pelias
 * answers nonsense queries with a real place on another continent at
 * confidence 1.0 - so the rejection cases below matter as much as the happy
 * path.
 */
class UrbanGoodzDriverFinishAddressTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryMan $driver;
    private UrbanGoodzDedicatedRoute $route;
    private string $authToken = 'FINISH_ADDRESS_TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz.openrouteservice', [
            'enabled' => true,
            'api_key' => 'TEST_ORS_KEY',
            'base_url' => 'https://api.openrouteservice.org',
            'cache_ttl_hours' => 0,
            'geocode_radius_km' => 150,
        ]);

        $business = UrbanGoodzBusinessClient::create([
            'company_name' => 'Finish Line Co',
            'email' => 'finish@urbangoodz.test',
            'status' => 'approved',
        ]);

        $this->driver = DeliveryMan::create([
            'f_name' => 'Finish',
            'l_name' => 'Driver',
            'phone' => '5550001111',
            'email' => 'finishdriver@urbangoodz.test',
            'password' => bcrypt('password'),
            'auth_token' => $this->authToken,
        ]);

        $this->route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $business->id,
            'route_name' => 'Finish Route',
            'route_label' => 'F',
            'total_packages' => 2,
            'estimated_miles' => 10.0,
            'estimated_duration' => 30,
            'scheduled_date' => now()->toDateString(),
            'route_type' => 'bulk_delivery',
            'status' => 'active',
            'assigned_driver_id' => $this->driver->id,
            'pickup_lat' => 29.7604000,
            'pickup_lng' => -95.3698000,
            'pickup_location' => 'Houston Hub',
        ]);

        foreach ([[29.7700000, -95.3700000], [29.7800000, -95.3800000]] as $i => [$lat, $lng]) {
            $pkg = UrbanGoodzRoutePackage::create([
                'dedicated_route_id' => $this->route->id,
                'business_client_id' => $business->id,
                'tracking_id' => 'TRK-F-' . ($i + 1),
                'barcode' => 'BAR-F-' . ($i + 1),
                'dropoff_name' => 'Stop ' . ($i + 1),
                'dropoff_address' => (100 * ($i + 1)) . ' Main St',
                'dropoff_lat' => $lat,
                'dropoff_lng' => $lng,
                'status' => 'pending',
                'stop_order' => $i + 1,
            ]);

            UrbanGoodzRouteOptimizationStop::create([
                'dedicated_route_id' => $this->route->id,
                'package_id' => $pkg->id,
                'stop_order' => $i + 1,
                'estimated_distance_from_prev' => 1.0,
                'estimated_duration_from_prev' => 5,
            ]);
        }
    }

    private function fakeGeocode(float $lat, float $lng, string $label): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response([
                'features' => [[
                    // Pelias orders a pair [lon, lat].
                    'geometry' => ['coordinates' => [$lng, $lat]],
                    'properties' => ['label' => $label, 'confidence' => 1],
                ]],
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

    private function finish(array $payload)
    {
        $this->actingAs($this->driver, 'delivery_men');

        return $this->postJson(
            "/api/v1/urban-goodz/driver/routes/{$this->route->id}/finish?token={$this->authToken}",
            $payload
        );
    }

    public function test_a_typed_address_becomes_the_route_finish_point(): void
    {
        $this->fakeGeocode(29.7373, -95.461268, '2800 Post Oak Boulevard, Houston, TX, USA');

        $response = $this->finish([
            'mode' => 'address',
            'end_address' => '2800 Post Oak Blvd, Houston, TX 77056',
        ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');

        $this->route->refresh();

        self::assertEqualsWithDelta(29.7373, (float) $this->route->end_lat, 0.0001);
        self::assertEqualsWithDelta(-95.461268, (float) $this->route->end_lng, 0.0001);
        self::assertFalse((bool) $this->route->return_to_origin);

        // The normalised label is stored, not the driver's raw typing, so the
        // run sheet shows what was actually matched.
        self::assertSame('2800 Post Oak Boulevard, Houston, TX, USA', $this->route->end_location);
        $response->assertJsonPath('route.finish_label', '2800 Post Oak Boulevard, Houston, TX, USA');
    }

    public function test_an_address_outside_the_service_area_is_refused_and_changes_nothing(): void
    {
        // Bounded search is meant to prevent this, but a provider that ignored
        // the bound would hand back London. The route must not move.
        $this->fakeGeocode(51.5034, -0.1276, '10 Downing Street, London, England');

        $response = $this->finish([
            'mode' => 'address',
            'end_address' => '10 Downing Street, London',
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'geocode_failed');

        $this->route->refresh();

        self::assertNull($this->route->end_lat);
        self::assertNull($this->route->end_lng);
        self::assertNull($this->route->end_location);
    }

    public function test_an_unmatched_address_is_refused(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response(['features' => []], 200),
            '*' => Http::response([], 200),
        ]);

        $this->finish([
            'mode' => 'address',
            'end_address' => 'asdkjhqwe zzzz nowhere 99999',
        ])->assertStatus(422)->assertJsonPath('code', 'geocode_failed');

        $this->route->refresh();
        self::assertNull($this->route->end_lat);
    }

    public function test_address_mode_requires_either_an_address_or_a_point(): void
    {
        $this->finish(['mode' => 'address'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_address');
    }

    public function test_explicit_coordinates_are_used_without_geocoding(): void
    {
        Http::fake([
            'api.openrouteservice.org/geocode/search*' => Http::response([], 500),
            '*' => Http::response([], 200),
        ]);

        $this->finish([
            'mode' => 'address',
            'end_lat' => 29.7400000,
            'end_lng' => -95.4600000,
            'end_label' => 'Picked on map',
        ])->assertStatus(200);

        $this->route->refresh();

        self::assertEqualsWithDelta(29.74, (float) $this->route->end_lat, 0.0001);
        self::assertSame('Picked on map', $this->route->end_location);

        // A point on the map needs no lookup, so a failing geocoder is no
        // obstacle to it.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/geocode/search'));
    }

    public function test_hub_mode_finishes_back_at_the_pickup(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->finish(['mode' => 'hub'])->assertStatus(200);

        $this->route->refresh();

        self::assertTrue((bool) $this->route->return_to_origin);
        self::assertEqualsWithDelta(29.7604, (float) $this->route->end_lat, 0.0001);
        self::assertSame('Houston Hub', $this->route->end_location);
    }

    public function test_open_mode_clears_the_finish_point(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->route->update([
            'end_lat' => 29.74,
            'end_lng' => -95.46,
            'end_location' => 'Somewhere',
        ]);

        $this->finish(['mode' => 'open'])->assertStatus(200);

        $this->route->refresh();

        self::assertNull($this->route->end_lat);
        self::assertNull($this->route->end_location);
        self::assertFalse((bool) $this->route->return_to_origin);
    }

    public function test_a_driver_cannot_set_the_finish_on_someone_elses_route(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->route->update(['assigned_driver_id' => $this->driver->id + 999]);

        $this->finish(['mode' => 'hub'])->assertStatus(404);
    }
}
