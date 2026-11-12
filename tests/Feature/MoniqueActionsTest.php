<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiDispatch;
use App\Models\AiMoniqueNotification;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\User;
use App\Models\UrbanGoodzOrderAnywhereReimbursement;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** What Monique can DO (not just notice): widen a stuck search, audit applications, pre-check receipts. */
class MoniqueActionsTest extends TestCase
{
    use DatabaseTransactions;

    private const LAT = 40.7580;
    private const LNG = -73.9855;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Mail::fake();
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);
        Config::set('urban_goodz_payments.order_anywhere.auto_dispatch', true);
        Config::set('urban_goodz_ai.monique_proactive.email_alerts', false);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function admin(): Admin
    {
        return Admin::create([
            'f_name' => 'Boss', 'l_name' => 'Owner', 'email' => 'boss-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1,
        ]);
    }

    private function driverAt(float $miles, string $tag, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'D', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "ma-{$tag}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => 'tok-'.bin2hex(random_bytes(10)), 'status' => 1, 'active' => 1,
            'application_status' => 'approved', 'available_for_order_anywhere' => true,
            'private_endpoint_lat' => self::LAT + $miles / 69.0, 'private_endpoint_lng' => self::LNG, 'current_orders' => 0,
        ], $over));
    }

    /** A paid, approved request that nobody could be offered, waiting long enough to be flagged. */
    private function stuckRequest(): OrderAnywhereRequest
    {
        $c = User::create(['f_name' => 'C', 'l_name' => 'Cust', 'email' => 'c-'.uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1]);
        $r = OrderAnywhereRequest::create([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $c->id, 'status' => 'awaiting_payment',
            'payment_status' => 'awaiting_payment', 'quote_amount' => 60, 'final_amount' => 60, 'item_subtotal' => 40, 'delivery_fee' => 8,
            'service_fee' => 6, 'tax' => 6, 'fulfillment_type' => 'external_merchant', 'dropoff_address' => '1 Customer Way',
            'pickup_latitude' => self::LAT, 'pickup_longitude' => self::LNG,
        ]);
        $this->actingAs($c, 'api')->postJson("/api/v1/order-anywhere/requests/{$r->id}/authorize-payment")->assertOk();
        DB::table('order_anywhere_requests')->where('id', $r->id)->update(['updated_at' => now()->subHour()]);

        return $r->fresh();
    }

    private function noteFor(Admin $admin, string $category): AiMoniqueNotification
    {
        app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        return AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', $category)->firstOrFail();
    }

    public function test_on_her_own_she_widens_a_stuck_search_and_the_job_reaches_a_driver_beyond_the_normal_radius(): void
    {
        $far = $this->driverAt(40, 'far'); // outside the default 25 mi
        $r = $this->stuckRequest();
        $this->assertSame(0, AiDispatch::where('order_id', $r->order_id)->count(), 'precondition: nobody was in range');

        $admin = $this->admin();
        // Default policy is auto_run: no click needed.
        $result = app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        $this->assertGreaterThanOrEqual(1, $result['auto_resolved_count']);
        $this->assertSame([$far->id], AiDispatch::where('order_id', $r->order_id)->pluck('delivery_man_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(50.0, (float) $r->fresh()->metadata['dispatch_radius_override_miles']);
        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'order_anywhere_unassigned')->firstOrFail();
        $this->assertSame(AiMoniqueNotification::STATUS_RESOLVED, $note->status);
        $this->assertTrue((bool) $note->auto_resolved);
    }

    public function test_in_ask_mode_the_same_fix_waits_for_a_click(): void
    {
        Config::set('urban_goodz_ai.monique_proactive.categories.order_anywhere_unassigned.action', 'ask');
        $far = $this->driverAt(40, 'far');
        $r = $this->stuckRequest();
        $admin = $this->admin();
        $note = $this->noteFor($admin, 'order_anywhere_unassigned');
        $this->assertSame(0, AiDispatch::where('order_id', $r->order_id)->count(), 'ask mode must not act before the click');
        $this->assertContains('let_monique_handle_it', array_column((array) $note->actions, 'action'));

        $out = app(MoniqueProactiveAttentionService::class)->handleNotificationAction($note->id, 'let_monique_handle_it');

        $this->assertTrue($out['success'] && $out['verified']);
        $this->assertSame([$far->id], AiDispatch::where('order_id', $r->order_id)->pluck('delivery_man_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_she_never_claims_success_when_nobody_is_in_range_even_widened(): void
    {
        $this->driverAt(200, 'unreachable');
        $r = $this->stuckRequest();
        $admin = $this->admin();
        $note = $this->noteFor($admin, 'order_anywhere_unassigned');

        $out = app(MoniqueProactiveAttentionService::class)->handleNotificationAction($note->id, 'let_monique_handle_it');

        $this->assertFalse($out['success']);
        $this->assertSame(0, AiDispatch::where('order_id', $r->order_id)->count());
        $this->assertSame(AiMoniqueNotification::STATUS_PENDING, $note->fresh()->status, 'it must stay open for a person');
    }

    public function test_she_stops_widening_at_the_cap(): void
    {
        Config::set('urban_goodz_ai.monique_proactive.ops.max_widen_miles', 25); // same as the default radius
        $this->driverAt(40, 'far');
        $this->stuckRequest();
        $admin = $this->admin();
        $note = $this->noteFor($admin, 'order_anywhere_unassigned');

        $out = app(MoniqueProactiveAttentionService::class)->handleNotificationAction($note->id, 'let_monique_handle_it');

        $this->assertFalse($out['success']);
        $this->assertStringContainsString('widest', $out['message']);
    }

    public function test_a_wider_override_never_narrows_the_admin_radius(): void
    {
        $r = $this->stuckRequest();
        $r->update(['metadata' => [...($r->metadata ?? []), 'dispatch_radius_override_miles' => 5]]);
        $near = $this->driverAt(20, 'mid'); // inside the global 25, outside the tiny override

        app(\App\Services\OrderAnywhereOrderConversionService::class)->handleApproved($r->fresh());

        $this->assertSame([$near->id], AiDispatch::where('order_id', $r->fresh()->order_id)->pluck('delivery_man_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_she_audits_pending_driver_applications_without_approving_anyone(): void
    {
        DeliveryMan::create([
            'f_name' => 'Ready', 'l_name' => 'One', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'ready-'.uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'application_status' => 'pending', 'status' => 0, 'image' => 'me.png', 'identity_image' => '["id.png"]', 'vehicle_id' => 1,
        ]);
        $thin = DeliveryMan::create([
            'f_name' => 'Thin', 'l_name' => 'Two', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'thin-'.uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'application_status' => 'pending', 'status' => 0,
        ]);
        DB::table('delivery_men')->where('application_status', 'pending')->update(['created_at' => now()->subDays(3)]);

        $admin = $this->admin();
        $note = $this->noteFor($admin, 'driver_applications_pending');
        $out = app(MoniqueProactiveAttentionService::class)->handleNotificationAction($note->id, 'let_monique_handle_it');

        $this->assertTrue($out['success']);
        $this->assertStringContainsString('Thin Two', $out['resolution_summary']);
        $this->assertStringContainsString('ID photo', $out['resolution_summary']);
        $this->assertSame('pending', $thin->fresh()->application_status, 'Monique must never approve a driver');
    }

    public function test_she_pre_checks_reimbursement_claims_but_never_approves_them(): void
    {
        Storage::fake('local');
        $driver = $this->driverAt(1, 'rb');
        $r = OrderAnywhereRequest::create([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'status' => 'shopping', 'payment_status' => 'captured',
            'fulfillment_type' => 'external_merchant', 'assigned_delivery_man_id' => $driver->id,
        ]);
        Storage::disk('local')->put('private/order-anywhere-reimbursements/1/ok.jpg', 'x');
        $good = UrbanGoodzOrderAnywhereReimbursement::create([
            'order_anywhere_request_id' => $r->id, 'delivery_man_id' => $driver->id, 'claimed_total' => 40, 'authorized_total' => 40,
            'cap_total' => 44, 'status' => 'pending', 'receipt_path' => 'private/order-anywhere-reimbursements/1/ok.jpg',
        ]);
        $over = UrbanGoodzOrderAnywhereReimbursement::create([
            'order_anywhere_request_id' => $r->id, 'delivery_man_id' => $driver->id, 'claimed_total' => 43, 'authorized_total' => 40,
            'cap_total' => 44, 'status' => 'pending', 'receipt_path' => 'private/order-anywhere-reimbursements/1/missing.jpg',
        ]);
        DB::table('urban_goodz_order_anywhere_reimbursements')->update(['created_at' => now()->subHours(10)]);

        $admin = $this->admin();
        $note = $this->noteFor($admin, 'reimbursements_pending');
        $out = app(MoniqueProactiveAttentionService::class)->handleNotificationAction($note->id, 'let_monique_handle_it');

        $this->assertTrue($out['success']);
        $this->assertStringContainsString("Claim #{$good->id} (\$40.00): looks fine", $out['resolution_summary']);
        $this->assertStringContainsString('over the customer-funded budget by $3.00', $out['resolution_summary']);
        $this->assertStringContainsString('receipt file is missing', $out['resolution_summary']);
        $this->assertSame('pending', $good->fresh()->status, 'Monique only recommends; she never approves money');
        $this->assertSame(0, \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->count());
    }
}
