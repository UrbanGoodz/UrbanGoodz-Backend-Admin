<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Models\DeliveryMan;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\DriverLocator;
use App\Services\OrderAnywhereDispatchIntegrationService;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Dispatch is always location aware: nearest first, from where the driver IS, never a blind pick. */
class DriverLocatorTest extends TestCase
{
    use DatabaseTransactions;

    private const LAT = 40.7580;
    private const LNG = -73.9855;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function driver(string $tag, ?float $homeMilesNorth = null, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'D', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "dl-{$tag}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => 'tok-'.bin2hex(random_bytes(8)), 'status' => 1, 'active' => 1,
            'application_status' => 'approved', 'available_for_order_anywhere' => true, 'is_delivery' => 1, 'current_orders' => 0,
            'private_endpoint_lat' => $homeMilesNorth === null ? null : self::LAT + $homeMilesNorth / 69.0,
            'private_endpoint_lng' => $homeMilesNorth === null ? null : self::LNG,
        ], $over));
    }

    private function reportAt(DeliveryMan $d, float $milesNorth, int $minutesAgo = 1): void
    {
        DB::table('delivery_histories')->insert([
            'delivery_man_id' => $d->id, 'latitude' => self::LAT + $milesNorth / 69.0, 'longitude' => self::LNG,
            'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function ids(array $ranked): array
    {
        return array_map(fn ($d) => (int) (is_array($d) ? $d['id'] : $d->id), $ranked);
    }

    public function test_where_the_driver_is_now_beats_their_saved_home_address(): void
    {
        $homeBodyAway = $this->driver('homebody', 0.5);   // lives next to the pickup...
        $this->reportAt($homeBodyAway, 30);               // ...but is 30 miles away right now
        $onSite = $this->driver('onsite', 40);            // lives far away...
        $this->reportAt($onSite, 0.3);                    // ...but is standing near the pickup

        $ranked = app(DriverLocator::class)->rankByDistance([$homeBodyAway, $onSite], self::LAT, self::LNG);

        $this->assertSame([$onSite->id, $homeBodyAway->id], $this->ids($ranked), 'the driver who is actually near must be offered the job first');
        $this->assertSame('live', $ranked[0]->location_source);
    }

    public function test_a_stale_report_is_not_trusted_and_falls_back_to_the_saved_address(): void
    {
        $d = $this->driver('stale', 2);
        $this->reportAt($d, 50, 120); // two hours old

        $ranked = app(DriverLocator::class)->rankByDistance([$d], self::LAT, self::LNG);

        $this->assertSame('home', $ranked[0]->location_source);
        $this->assertEqualsWithDelta(2.0, $ranked[0]->distance_miles, 0.2);
    }

    public function test_a_driver_with_no_position_is_skipped_never_guessed(): void
    {
        $none = $this->driver('nowhere');
        $near = $this->driver('near', 1);

        $this->assertSame([$near->id], $this->ids(app(DriverLocator::class)->rankByDistance([$none, $near], self::LAT, self::LNG)));
    }

    public function test_the_order_goes_outward_from_nearest_to_furthest_and_respects_the_radius(): void
    {
        $a = $this->driver('a', 20);
        $b = $this->driver('b', 1);
        $c = $this->driver('c', 7);
        $d = $this->driver('d', 90);

        $ranked = app(DriverLocator::class)->rankByDistance([$a, $b, $c, $d], self::LAT, self::LNG, 50);

        $this->assertSame([$b->id, $c->id, $a->id], $this->ids($ranked));
    }

    public function test_order_anywhere_nearest_search_uses_live_positions(): void
    {
        $homeBodyAway = $this->driver('hb', 0.5);
        $this->reportAt($homeBodyAway, 30);
        $onSite = $this->driver('os', 40);
        $this->reportAt($onSite, 0.3);

        $found = app(OrderAnywhereDispatchIntegrationService::class)->findNearestDrivers(self::LAT, self::LNG, 25);

        $this->assertSame([$onSite->id], $this->ids($found), 'only the driver actually within 25 miles NOW qualifies');
    }

    private function delayedMarketplaceOrder(): Order
    {
        $zone = Zone::firstOrCreate(['name' => 'DL Zone'], [
            'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"), 'status' => 1,
        ]);
        $module = Module::firstOrCreate(['module_name' => 'Food'], ['module_type' => 'food', 'status' => 1]);
        $vendor = Vendor::create(['f_name' => 'V', 'l_name' => 'DL', 'email' => 'vdl-'.uniqid().'@urbangoodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'status' => 1]);
        $store = Store::create(['name' => 'DL Store', 'phone' => '2223334445', 'logo' => 's.png', 'address' => 'x', 'latitude' => self::LAT, 'longitude' => self::LNG,
            'module_id' => $module->id, 'zone_id' => $zone->id, 'status' => 1, 'vendor_id' => $vendor->id]);
        $customer = User::create(['f_name' => 'C', 'l_name' => 'DL', 'email' => 'cdl-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1]);

        return Order::create([
            'user_id' => $customer->id, 'store_id' => $store->id, 'module_id' => $module->id, 'order_amount' => 30, 'delivery_charge' => 5,
            'order_status' => 'pending', 'zone_id' => $zone->id, 'delivery_address' => 'y', 'payment_method' => 'staged_test', 'distance' => 2,
            'order_type' => 'delivery', 'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3),
        ]);
    }

    private function monique(): array
    {
        $admin = Admin::create(['f_name' => 'B', 'l_name' => 'DL', 'email' => 'adl-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1]);
        config(['urban_goodz_ai.monique_proactive.categories.delayed_orders.action' => 'auto_run', 'urban_goodz_ai.monique_proactive.email_alerts' => false]);

        return [$admin, app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id)];
    }

    public function test_monique_assigns_the_nearest_courier_not_the_first_free_one(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]); // isolate from other fixtures in this DB
        $far = $this->driver('far', 25);      // lowest id, would have won the old "first free courier" rule
        $near = $this->driver('near', 0.4);
        $order = $this->delayedMarketplaceOrder();

        $this->monique();

        $this->assertSame($near->id, (int) $order->fresh()->delivery_man_id, 'the nearest courier gets the order');
        $this->assertNotSame($far->id, (int) $order->fresh()->delivery_man_id);
    }

    public function test_monique_will_not_assign_blindly_when_no_courier_has_a_known_position(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]);
        $this->driver('lost'); // active, approved, but no position anywhere
        $order = $this->delayedMarketplaceOrder();

        [$admin] = $this->monique();

        $this->assertNull($order->fresh()->delivery_man_id);
        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'delayed_orders')->first();
        $this->assertNotNull($note, 'it must escalate to a person, not stay silent');
        $this->assertSame(AiMoniqueNotification::STATUS_PENDING, $note->status);
    }

    public function test_a_business_with_its_own_couriers_gets_first_call_then_the_pool_nearest_to_furthest(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]);
        $ownFar = $this->driver('ownfar', 15, ['vendor_id' => 777, 'ownership_type' => 'vendor_owned', 'available_for_marketplace' => 0]);
        $ownNear = $this->driver('ownnear', 6, ['vendor_id' => 777, 'ownership_type' => 'vendor_owned', 'available_for_marketplace' => 0]);
        $poolNearest = $this->driver('pool1', 0.5);
        $poolNext = $this->driver('pool2', 3);

        $found = app(OrderAnywhereDispatchIntegrationService::class)
            ->findNearestDrivers(self::LAT, self::LNG, 50, ['vendor_id' => 777]);

        $this->assertSame([$ownNear->id, $ownFar->id, $poolNearest->id, $poolNext->id], $this->ids($found));
    }

    public function test_another_businesss_private_fleet_is_never_offered_the_job(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]);
        $theirs = $this->driver('theirs', 0.2, ['vendor_id' => 888, 'ownership_type' => 'vendor_owned', 'available_for_marketplace' => 0]);
        $theirsButMarketplace = $this->driver('theirs-open', 0.3, ['vendor_id' => 888, 'ownership_type' => 'vendor_owned', 'available_for_marketplace' => 1]);
        $platform = $this->driver('platform', 5);

        $found = app(OrderAnywhereDispatchIntegrationService::class)->findNearestDrivers(self::LAT, self::LNG, 50, ['vendor_id' => 777]);

        $this->assertSame([$theirsButMarketplace->id, $platform->id], $this->ids($found));
        $this->assertNotContains($theirs->id, $this->ids($found));
    }

    public function test_an_admin_can_lift_the_pool_rules_explicitly(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]);
        $theirs = $this->driver('theirs', 0.2, ['vendor_id' => 888, 'ownership_type' => 'vendor_owned', 'available_for_marketplace' => 0]);
        $platform = $this->driver('platform', 5);

        $found = app(OrderAnywhereDispatchIntegrationService::class)
            ->findNearestDrivers(self::LAT, self::LNG, 50, ['vendor_id' => 777, 'any_pool' => true]);

        $this->assertSame([$theirs->id, $platform->id], $this->ids($found));
    }

    public function test_monique_gives_the_stores_own_courier_first_call_even_when_a_pool_courier_is_closer(): void
    {
        DeliveryMan::query()->withoutGlobalScopes()->update(['active' => 0]);
        $order = $this->delayedMarketplaceOrder();
        $vendorId = (int) $order->store->vendor_id;
        $own = $this->driver('own', 8, ['vendor_id' => $vendorId, 'ownership_type' => 'vendor_owned']);
        $closerPool = $this->driver('closer', 0.3);

        $this->monique();

        $this->assertSame($own->id, (int) $order->fresh()->delivery_man_id);
    }
}
