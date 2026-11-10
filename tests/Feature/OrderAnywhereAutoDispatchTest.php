<?php

namespace Tests\Feature;

use App\Models\AiDispatch;
use App\Services\UrbanGoodzAiDispatchService;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use App\Models\UrbanGoodzNotification;
use App\Models\UrbanGoodzPaymentLedger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Order Anywhere dispatch is automatic: payment authorized -> approved -> order created ->
 * nearest driver to the PICKUP location offered the job -> declined/expired offers move to
 * the next driver -> an admin can override by assigning a specific driver.
 */
class OrderAnywhereAutoDispatchTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);
        Config::set('urban_goodz_payments.order_anywhere.auto_dispatch', true);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    public function test_expiring_stale_offers_never_touches_a_delivery_already_in_progress(): void
    {
        $past = now()->subMinutes(20);
        $inProgress = [];
        foreach ([AiDispatch::STATUS_ACCEPTED, AiDispatch::STATUS_EN_ROUTE_TO_PICKUP, AiDispatch::STATUS_PICKED_UP, AiDispatch::STATUS_IN_TRANSIT, AiDispatch::STATUS_DELIVERED] as $status) {
            $inProgress[$status] = $this->insertRow('ai_dispatches', ['status' => $status, 'offer_expires_at' => $past]);
        }
        $stale = $this->insertRow('ai_dispatches', ['status' => AiDispatch::STATUS_SENT, 'offer_expires_at' => $past, 'sent_at' => $past]);

        app(UrbanGoodzAiDispatchService::class)->expireStaleOffers(30);

        foreach ($inProgress as $status => $id) {
            $this->assertSame($status, DB::table('ai_dispatches')->where('id', $id)->value('status'), "a '{$status}' dispatch must not be expired");
        }
        $this->assertSame(AiDispatch::STATUS_EXPIRED, DB::table('ai_dispatches')->where('id', $stale)->value('status'), 'a genuinely stale offer must expire');
    }

    // Pickup is a merchant in Times Square; "near" is ~1 mile away, "far" ~20 miles.
    private const PICK_LAT = 40.7580;
    private const PICK_LNG = -73.9855;

    private function customer(): User
    {
        return User::create(['f_name' => 'Auto', 'l_name' => 'Disp', 'email' => 'auto-'.uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1]);
    }

    private function driverAt(float $milesNorth, string $tag): DeliveryMan
    {
        return DeliveryMan::create([
            'f_name' => 'D', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "ad-{$tag}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => 'tok-'.bin2hex(random_bytes(10)), 'status' => 1, 'active' => 1,
            'application_status' => 'approved', 'available_for_order_anywhere' => true,
            'private_endpoint_lat' => self::PICK_LAT + $milesNorth / 69.0, 'private_endpoint_lng' => self::PICK_LNG, 'current_orders' => 0,
        ]);
    }

    private function quotedRequest(User $c, array $over = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $c->id, 'status' => 'awaiting_payment',
            'payment_status' => 'awaiting_payment', 'quote_amount' => 60, 'final_amount' => 60, 'item_subtotal' => 40, 'delivery_fee' => 8,
            'service_fee' => 6, 'tax' => 6, 'fulfillment_type' => 'external_merchant', 'dropoff_address' => '1 Customer Way',
            'pickup_latitude' => self::PICK_LAT, 'pickup_longitude' => self::PICK_LNG,
        ], $over));
    }

    private function pay(User $c, OrderAnywhereRequest $r): void
    {
        $this->actingAs($c, 'api')->postJson("/api/v1/order-anywhere/requests/{$r->id}/authorize-payment")->assertOk();
    }

    private function offeredTo(OrderAnywhereRequest $r): array
    {
        return AiDispatch::where('order_id', $r->fresh()->order_id)->orderBy('id')->pluck('delivery_man_id')->map(fn ($v) => (int) $v)->all();
    }

    public function test_paying_dispatches_the_nearest_driver_to_the_pickup_with_nobody_in_the_loop(): void
    {
        $far = $this->driverAt(20, 'far');
        $near = $this->driverAt(1, 'near');
        $c = $this->customer();
        $r = $this->quotedRequest($c);

        $this->pay($c, $r);

        $fresh = $r->fresh();
        $this->assertSame('approved', $fresh->status, 'payment must approve the request automatically');
        $this->assertNotNull($fresh->order_id, 'approval must create the order');
        $this->assertSame([$near->id], $this->offeredTo($r), 'only the nearest driver to the pickup gets the offer');
        $this->assertNotContains($far->id, $this->offeredTo($r));
    }

    public function test_a_declined_job_moves_to_the_next_nearest_and_only_returns_after_everyone_has_had_a_turn(): void
    {
        $near = $this->driverAt(1, 'near');
        $next = $this->driverAt(3, 'next');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        AiDispatch::where('order_id', $r->fresh()->order_id)->firstOrFail()->declineDispatch('distance');
        Artisan::call('order-anywhere:dispatch-pending');
        Artisan::call('order-anywhere:dispatch-pending'); // idempotent: a live offer exists now

        $this->assertSame([$near->id, $next->id], $this->offeredTo($r), 'second offer goes to the next driver, exactly once');

        AiDispatch::where('order_id', $r->fresh()->order_id)->where('delivery_man_id', $next->id)->firstOrFail()->declineDispatch('payout');
        Artisan::call('order-anywhere:dispatch-pending');
        // Everyone in range has now had a turn: a new round starts from the nearest, not before.
        $this->assertSame([$near->id, $next->id, $near->id], $this->offeredTo($r));
    }

    public function test_an_admin_can_override_by_assigning_a_specific_driver(): void
    {
        $near = $this->driverAt(1, 'near');
        $chosen = $this->driverAt(9, 'chosen');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);
        $this->assertSame([$near->id], $this->offeredTo($r));

        $admin = \App\Models\Admin::create([
            'f_name' => 'Ops', 'l_name' => 'Admin', 'email' => 'ops-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'ops-token',
        ]);
        $this->actingAs($admin, 'admin')
            ->postJson("/api/v1/order-anywhere/admin/requests/{$r->id}/assign-driver", ['driver_id' => $chosen->id])
            ->assertOk();

        $this->assertSame($chosen->id, (int) $r->fresh()->assigned_delivery_man_id);
        $this->assertSame(AiDispatch::STATUS_CANCELLED, AiDispatch::where('order_id', $r->fresh()->order_id)->where('delivery_man_id', $near->id)->value('status'),
            'the automatic offer to the nearest driver is withdrawn');

        Artisan::call('order-anywhere:dispatch-pending');
        $this->assertSame([$near->id], $this->offeredTo($r), 'automation must not offer the job to anyone else once an admin chose');
    }

    public function test_nobody_nearby_flags_the_request_for_an_admin_instead_of_stalling_silently(): void
    {
        Config::set('urban_goodz_payments.order_anywhere.escalate_after_minutes', 0);
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        Artisan::call('order-anywhere:dispatch-pending');

        $this->assertNotNull(($r->fresh()->metadata ?? [])['needs_manual_dispatch_at'] ?? null);
    }

    public function test_auto_dispatch_can_be_switched_off(): void
    {
        Config::set('urban_goodz_payments.order_anywhere.auto_dispatch', false);
        $this->driverAt(1, 'near');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        $this->assertSame('authorized', $r->fresh()->status);
        $this->assertNull($r->fresh()->order_id);
    }

    private function superAdmin(): \App\Models\Admin
    {
        return \App\Models\Admin::create([
            'f_name' => 'Ops', 'l_name' => 'Boss', 'email' => 'boss-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'boss-token',
        ]);
    }

    // ---------------------------------------------------------------- money timing

    public function test_the_customer_is_only_charged_when_a_driver_accepts(): void
    {
        $near = $this->driverAt(1, 'near');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        $this->assertSame('authorized', $r->fresh()->payment_status, 'checkout only places a hold');
        $this->assertSame(0.0, (float) $r->fresh()->captured_amount);

        $dispatch = AiDispatch::where('order_id', $r->fresh()->order_id)->firstOrFail();
        $this->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept?token=".$near->auth_token)->assertOk();

        $fresh = $r->fresh();
        $this->assertSame('captured', $fresh->payment_status, 'acceptance captures the held funds');
        $this->assertSame(60.0, (float) $fresh->captured_amount);
        $this->assertSame(1, UrbanGoodzPaymentLedger::where('payable_id', $r->id)->where('event_type', 'capture')->count());
    }

    public function test_a_failed_capture_issues_no_card_and_alerts_an_admin(): void
    {
        $near = $this->driverAt(1, 'near');
        $this->superAdmin();
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);
        $r->fresh()->update(['payment_status' => 'authorization_failed']); // the hold is no longer good

        $dispatch = AiDispatch::where('order_id', $r->fresh()->order_id)->firstOrFail();
        $this->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept?token=".$near->auth_token)->assertOk();

        $this->assertSame(0, \App\Models\UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $r->id)->count(), 'no card may be issued without secured funds');
        $this->assertGreaterThan(0, UrbanGoodzNotification::where('recipient_type', 'admin')->where('channel', 'admin_alert')->count());
    }

    // ---------------------------------------------------------------- no driver

    public function test_no_driver_alerts_admins_and_tells_the_customer_without_charging_them(): void
    {
        Config::set('urban_goodz_payments.order_anywhere.escalate_after_minutes', 0);
        Config::set('urban_goodz_payments.order_anywhere.cancel_after_minutes', 0);
        $this->superAdmin();
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        Artisan::call('order-anywhere:dispatch-pending');

        $alert = UrbanGoodzNotification::where('recipient_type', 'admin')->where('event_type', 'order_anywhere_no_driver')->first();
        $this->assertNotNull($alert, 'an admin must be told when nobody is available');
        $this->assertStringContainsString($r->request_number, $alert->body);
        $this->assertSame('authorized', $r->fresh()->payment_status, 'still only a hold');
    }

    public function test_after_the_timeout_the_request_is_cancelled_and_the_hold_released(): void
    {
        Config::set('urban_goodz_payments.order_anywhere.escalate_after_minutes', 0);
        Config::set('urban_goodz_payments.order_anywhere.cancel_after_minutes', 1);
        $this->superAdmin();
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        Artisan::call('order-anywhere:dispatch-pending');                       // starts the clock
        $m = $r->fresh()->metadata;
        $m['dispatch_waiting_since'] = now()->subMinutes(5)->toIso8601String();
        $r->fresh()->update(['metadata' => $m]);
        Artisan::call('order-anywhere:dispatch-pending');                       // timeout passed

        $fresh = $r->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('cancelled', $fresh->payment_status);
        $this->assertSame(1, UrbanGoodzPaymentLedger::where('payable_id', $r->id)->where('event_type', 'authorization_voided')->count(), 'the hold must be voided, not left to expire');
        $this->assertSame(0, UrbanGoodzPaymentLedger::where('payable_id', $r->id)->where('event_type', 'capture')->count(), 'nothing was ever captured');
    }

    // ---------------------------------------------------------------- admin cancel returns money

    public function test_an_admin_cancel_before_capture_voids_the_hold(): void
    {
        $this->driverAt(1, 'near');
        $admin = $this->superAdmin();
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/requests/{$r->id}/status", ['status' => 'cancelled', 'reason' => 'Customer called'])->assertOk();

        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame('cancelled', $r->fresh()->payment_status);
        $this->assertSame(1, UrbanGoodzPaymentLedger::where('payable_id', $r->id)->where('event_type', 'authorization_voided')->count());
    }

    public function test_an_admin_cancel_after_capture_refunds_the_customer_in_full(): void
    {
        $near = $this->driverAt(1, 'near');
        $admin = $this->superAdmin();
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);
        $dispatch = AiDispatch::where('order_id', $r->fresh()->order_id)->firstOrFail();
        $this->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept?token=".$near->auth_token)->assertOk();
        $this->assertSame('captured', $r->fresh()->payment_status);

        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/requests/{$r->id}/status", ['status' => 'cancelled', 'reason' => 'Merchant closed'])->assertOk();

        $fresh = $r->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('refunded', $fresh->payment_status);
        $this->assertSame(60.0, (float) $fresh->refunded_amount);
        $this->assertGreaterThan(0, UrbanGoodzPaymentLedger::where('payable_id', $r->id)->where('event_type', 'refund')->count());
    }

    // ---------------------------------------------------------------- cycling

    private function declineLive(OrderAnywhereRequest $r): void
    {
        AiDispatch::where('order_id', $r->fresh()->order_id)
            ->whereIn('status', [AiDispatch::STATUS_PENDING_DRIVER, AiDispatch::STATUS_SENT, AiDispatch::STATUS_VIEWED])
            ->firstOrFail()->declineDispatch('distance');
        Artisan::call('order-anywhere:dispatch-pending');
    }

    public function test_it_cycles_nearest_first_through_every_driver_in_range_and_then_starts_a_new_round(): void
    {
        $a = $this->driverAt(1, 'a');
        $b = $this->driverAt(4, 'b');
        $c2 = $this->driverAt(9, 'c');
        $outside = $this->driverAt(60, 'outside');                    // beyond the default radius
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        $this->declineLive($r);   // b
        $this->declineLive($r);   // c
        $this->assertSame([$a->id, $b->id, $c2->id], $this->offeredTo($r), 'nearest first, then next closest, in order');

        $this->declineLive($r);   // everyone in range has had a turn -> new round, nearest again
        $this->assertSame([$a->id, $b->id, $c2->id, $a->id], $this->offeredTo($r), 'a new round starts from the nearest');
        $this->assertNotContains($outside->id, $this->offeredTo($r), 'drivers beyond the radius are never offered');
        $this->assertSame(2, (int) $r->fresh()->metadata['dispatch_round']);
    }

    public function test_the_mileage_is_adjustable_by_an_admin_and_applies_immediately(): void
    {
        $near = $this->driverAt(1, 'near');
        $mid = $this->driverAt(40, 'mid');
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->putJson('/api/v1/order-anywhere/admin/dispatch-settings', ['radius_miles' => 50])->assertOk()->assertJsonPath('data.radius_miles', 50);
        $this->actingAs($admin, 'admin')->getJson('/api/v1/order-anywhere/admin/dispatch-settings')->assertOk()->assertJsonPath('data.radius_miles', 50);

        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);
        $this->declineLive($r);
        $this->assertSame([$near->id, $mid->id], $this->offeredTo($r), 'with a 50-mile radius the 40-mile driver is in range');

        $this->actingAs($admin, 'admin')->putJson('/api/v1/order-anywhere/admin/dispatch-settings', ['radius_miles' => 5])->assertOk();
        $c2 = $this->customer();
        $r2 = $this->quotedRequest($c2);
        $this->pay($c2, $r2);
        $this->declineLive($r2);
        $this->assertSame([$near->id, $near->id], $this->offeredTo($r2), 'with a 5-mile radius only the near driver is in range, so the next round re-offers them');

        $this->actingAs($admin, 'admin')->putJson('/api/v1/order-anywhere/admin/dispatch-settings', ['radius_miles' => 9999])->assertStatus(422);
    }

    public function test_a_lapsed_offer_moves_on_to_the_next_driver_without_anyone_declining(): void
    {
        $a = $this->driverAt(1, 'a');
        $b = $this->driverAt(4, 'b');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);

        AiDispatch::where('order_id', $r->fresh()->order_id)->update(['offer_expires_at' => now()->subMinute()]);
        Artisan::call('order-anywhere:dispatch-pending');

        $this->assertSame([$a->id, $b->id], $this->offeredTo($r));
    }

    public function test_cycling_stops_the_moment_a_driver_accepts(): void
    {
        $a = $this->driverAt(1, 'a');
        $this->driverAt(4, 'b');
        $c = $this->customer();
        $r = $this->quotedRequest($c);
        $this->pay($c, $r);
        $dispatch = AiDispatch::where('order_id', $r->fresh()->order_id)->firstOrFail();
        $this->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept?token=".$a->auth_token)->assertOk();

        AiDispatch::where('order_id', $r->fresh()->order_id)->update(['offer_expires_at' => now()->subMinute()]);
        Artisan::call('order-anywhere:dispatch-pending');
        Artisan::call('order-anywhere:dispatch-pending');

        $this->assertSame([$a->id], $this->offeredTo($r), 'an accepted job is never re-offered');
    }

    // ---------------------------------------------------------------- pickup location

    private function mapKey(?string $key): void
    {
        \App\Models\BusinessSetting::updateOrInsert(['key' => 'map_api_key_server'], ['value' => $key ?? '']);
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_an_address_with_no_coordinates_is_geocoded_so_the_nearest_driver_is_really_nearest(): void
    {
        $this->mapKey('test-key');
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => [[
            'formatted_address' => '1 Merchant Way, New York, NY', 'types' => ['street_address'],
            'geometry' => ['location' => ['lat' => self::PICK_LAT, 'lng' => self::PICK_LNG], 'location_type' => 'ROOFTOP'],
        ]]], 200)]);
        $near = $this->driverAt(1, 'near');
        $far = $this->driverAt(20, 'far');
        $c = $this->customer();
        $r = $this->quotedRequest($c, ['pickup_latitude' => null, 'pickup_longitude' => null, 'pickup_address' => '1 Merchant Way, New York, NY']);

        $this->pay($c, $r);

        $this->assertSame('geocoded', $r->fresh()->metadata['pickup_location_source']);
        $this->assertEqualsWithDelta(self::PICK_LAT, (float) $r->fresh()->pickup_latitude, 0.0001);
        $this->assertSame([$near->id], $this->offeredTo($r), 'the driver nearest the real pickup, not the zone centre');
    }

    public function test_without_a_usable_address_the_location_is_marked_as_an_estimate(): void
    {
        $this->mapKey(null);                                    // no key: nothing to look up with
        $this->driverAt(1, 'near');
        $c = $this->customer();
        $r = $this->quotedRequest($c, ['pickup_latitude' => null, 'pickup_longitude' => null, 'pickup_address' => 'https://example.test/product/123']);

        $this->pay($c, $r);

        $this->assertSame('estimated_zone_center', $r->fresh()->metadata['pickup_location_source'], 'an admin can see the location is a guess');
        Http::assertNothingSent();
    }

    public function test_a_city_level_match_is_not_trusted_as_a_pickup_location(): void
    {
        $this->mapKey('test-key');
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => [[
            'formatted_address' => 'Houston, TX', 'types' => ['locality', 'political'],
            'geometry' => ['location' => ['lat' => 29.76, 'lng' => -95.36], 'location_type' => 'APPROXIMATE'],
        ]]], 200)]);

        $this->assertNull(app(\App\Services\GeocodingService::class)->geocode('somewhere in Houston Texas'));
    }
}
