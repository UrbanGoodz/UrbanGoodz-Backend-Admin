<?php

namespace Tests\Feature;

use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\User;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Follow the money on the real automatic path: admin quotes -> customer pays (hold) -> nearest driver
 * accepts -> funds captured -> split between merchant purchase funds, driver, and Urban Goodz.
 * Every captured dollar must be allocated, and the driver must actually be on the books.
 */
class OrderAnywhereMoneyReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private const LAT = 40.7580;
    private const LNG = -73.9855;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function run_(): array
    {
        $customer = User::create(['f_name' => 'Rec', 'l_name' => 'On', 'email' => 'rec-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
        $driver = DeliveryMan::create([
            'f_name' => 'Rec', 'l_name' => 'Driver', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'rd-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(8)), 'status' => 1, 'active' => 1, 'application_status' => 'approved', 'available_for_order_anywhere' => true,
            'private_endpoint_lat' => self::LAT + 1 / 69, 'private_endpoint_lng' => self::LNG, 'current_orders' => 0,
        ]);
        $request = OrderAnywhereRequest::create([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $customer->id, 'status' => 'sourcing', 'payment_status' => 'awaiting_quote',
            'fulfillment_type' => 'external_merchant', 'dropoff_address' => '1 Customer Way', 'pickup_latitude' => self::LAT, 'pickup_longitude' => self::LNG,
        ]);

        // The real admin quote: $100 of goods, $20 markup, $8 delivery, $7.50 tax => $135.50.
        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 135.50, 'final_amount' => 135.50, 'item_subtotal' => 100, 'service_fee' => 20, 'delivery_fee' => 8, 'tax' => 7.5,
        ]);

        $this->actingAs($customer, 'api')->postJson("/api/v1/order-anywhere/requests/{$request->id}/authorize-payment")->assertOk();
        $dispatch = AiDispatch::where('order_id', $request->fresh()->order_id)->firstOrFail();
        $this->postJson("/api/v1/urban-goodz/driver/ai-dispatches/{$dispatch->id}/accept?token=".$driver->auth_token)->assertOk();

        return [$request->fresh(), $driver];
    }

    public function test_the_driver_is_attached_to_the_request_when_they_accept(): void
    {
        [$request, $driver] = $this->run_();

        $this->assertSame($driver->id, (int) $request->assigned_delivery_man_id, 'without this the driver cannot be paid and cannot use the job endpoints');
        $this->assertSame('shopper_accepted', $request->status, 'the next driver action is to start shopping');
    }

    public function test_every_captured_dollar_is_allocated_and_the_driver_is_paid(): void
    {
        [$request, $driver] = $this->run_();

        $this->assertSame('captured', $request->payment_status);
        $splits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)->where('payable_id', $request->id)->get();

        $driverSplit = $splits->firstWhere('recipient_type', 'driver');
        $this->assertNotNull($driverSplit, 'the driver delivery pay must be on the books');
        $this->assertSame($driver->id, (int) $driverSplit->recipient_id);
        $this->assertGreaterThan(0, (float) $driverSplit->amount);

        $platform = (float) $splits->where('recipient_type', 'platform')->sum('amount');
        $purchase = (float) $request->merchant_purchase_amount;
        $this->assertSame(107.5, $purchase, 'purchase funds = item cost + tax, never revenue or driver pay');

        $allocated = $platform + (float) $driverSplit->amount + (float) $splits->where('recipient_type', 'dispatcher')->sum('amount');
        $this->assertEqualsWithDelta(135.50, $purchase + $allocated, 0.01, 'purchase funds + driver + platform must equal what was captured');
    }

    public function test_the_drivers_delivery_pay_lands_as_a_withdrawable_earning_exactly_once(): void
    {
        [$request, $driver] = $this->run_();

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->get();
        $split = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->where('recipient_type', 'driver')->firstOrFail();

        $this->assertCount(1, $earnings, 'one delivery, one earning');
        $this->assertEqualsWithDelta((float) $split->amount, (float) $earnings->first()->amount, 0.01);
        $this->assertSame('pending', $earnings->first()->status, 'pending earnings are what the driver can request a payout against');
        $this->assertEqualsWithDelta((float) $split->amount, \App\Services\UrbanGoodzPayoutGuard::driverAvailable($driver->id), 0.01);
    }

    public function test_the_automatic_flow_leaves_split_rows_that_match_the_request(): void
    {
        [$request] = $this->run_();

        $this->assertSame([], app(UrbanGoodzPaymentService::class)->splitRowMismatches($request));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('order-anywhere:audit-splits'));
    }

    public function test_a_driver_assigned_by_an_admin_after_capture_is_paid_exactly_once(): void
    {
        $customer = User::create(['f_name' => 'Late', 'l_name' => 'Assign', 'email' => 'late-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
        $driver = DeliveryMan::create([
            'f_name' => 'Late', 'l_name' => 'Driver', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'ld-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.bin2hex(random_bytes(8)), 'status' => 1, 'active' => 1, 'application_status' => 'approved',
        ]);
        Config::set('urban_goodz_payments.order_anywhere.auto_dispatch', false);   // admin drives this one by hand
        $request = OrderAnywhereRequest::create([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $customer->id, 'status' => 'sourcing', 'payment_status' => 'awaiting_quote',
            'fulfillment_type' => 'external_merchant', 'dropoff_address' => '1 Customer Way',
        ]);
        $payments = app(UrbanGoodzPaymentService::class);
        $payments->quoteOrderAnywhere($request, ['quote_amount' => 135.50, 'final_amount' => 135.50, 'item_subtotal' => 100, 'service_fee' => 20, 'delivery_fee' => 8, 'tax' => 7.5]);
        $this->actingAs($customer, 'api')->postJson("/api/v1/order-anywhere/requests/{$request->id}/authorize-payment")->assertOk();
        $payments->captureCustomerPayment($request->fresh(), ['captured_amount' => 135.50, 'capture_reference' => 'manual-'.uniqid(), 'source' => 'admin']);

        // Captured with nobody attached: the audit must say so rather than pass silently.
        $this->assertNotSame([], $payments->splitRowMismatches($request->fresh()));

        $admin = \App\Models\Admin::create(['f_name' => 'A', 'l_name' => 'D', 'email' => 'ad-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1]);
        $assign = fn () => $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/requests/{$request->id}/assign-driver", ['driver_id' => $driver->id])->assertOk();
        $assign();
        $assign();   // a repeat must not pay twice

        $split = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->where('recipient_type', 'driver')->get();
        $this->assertCount(1, $split);
        $this->assertSame('released', $split->first()->status);
        $this->assertSame(1, \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)->count(), 'one earning, even though the driver was assigned twice');
        $this->assertSame([], $payments->splitRowMismatches($request->fresh()));
    }

    public function test_a_customer_can_cancel_for_a_full_refund_until_the_goods_are_bought(): void
    {
        [$request, $driver] = $this->run_();          // driver accepted, payment captured, nothing bought yet
        $customer = User::find($request->customer_id);

        $this->actingAs($customer, 'api')->postJson("/api/v1/order-anywhere/requests/{$request->id}/cancel", ['reason' => 'Changed my mind'])->assertOk();

        $fresh = $request->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('refunded', $fresh->payment_status);
        $this->assertSame(135.5, (float) $fresh->refunded_amount, 'every dollar comes back');
        $this->assertSame(AiDispatch::STATUS_CANCELLED, AiDispatch::where('order_id', $fresh->order_id)->value('status'), 'the job leaves the driver\'s list');
    }

    public function test_a_customer_cannot_cancel_once_the_driver_has_bought_the_goods(): void
    {
        [$request] = $this->run_();
        $customer = User::find($request->customer_id);
        $request->forceFill(['status' => 'purchased'])->save();

        $this->actingAs($customer, 'api')->postJson("/api/v1/order-anywhere/requests/{$request->id}/cancel")->assertStatus(422);

        $this->assertSame('purchased', $request->fresh()->status);
        $this->assertSame('captured', $request->fresh()->payment_status);
    }

    public function test_an_admin_is_alerted_when_money_is_captured_but_the_driver_is_missing_from_the_split(): void
    {
        $customer = User::create(['f_name' => 'Al', 'l_name' => 'Ert', 'email' => 'alert-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
        \App\Models\Admin::create(['f_name' => 'B', 'l_name' => 'Oss', 'email' => 'boss-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1]);
        Config::set('urban_goodz_payments.order_anywhere.auto_dispatch', false);
        $request = OrderAnywhereRequest::create([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $customer->id, 'status' => 'sourcing', 'payment_status' => 'awaiting_quote',
            'fulfillment_type' => 'external_merchant', 'dropoff_address' => '1 Customer Way',
        ]);
        $payments = app(UrbanGoodzPaymentService::class);
        $payments->quoteOrderAnywhere($request, ['quote_amount' => 135.50, 'final_amount' => 135.50, 'item_subtotal' => 100, 'service_fee' => 20, 'delivery_fee' => 8, 'tax' => 7.5]);
        $this->actingAs($customer, 'api')->postJson("/api/v1/order-anywhere/requests/{$request->id}/authorize-payment")->assertOk();

        // Captured with nobody attached (an unusual path): the gap must not pass silently.
        $this->assertTrue(app(\App\Services\OrderAnywhereDispatchIntegrationService::class)->captureFundsForDriver($request->fresh()));

        $alert = \App\Models\UrbanGoodzNotification::where('event_type', 'order_anywhere_split_mismatch')->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString($request->request_number, $alert->body);
    }
}
