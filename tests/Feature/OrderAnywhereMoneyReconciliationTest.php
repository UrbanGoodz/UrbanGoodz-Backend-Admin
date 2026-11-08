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
}
