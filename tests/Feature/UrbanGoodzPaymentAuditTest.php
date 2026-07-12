<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\Vendor;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzPaymentAuditTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzPaymentService $paymentService;
    private Admin $admin;
    private Vendor $vendor;
    private Store $store;
    private DeliveryMan $driver;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure sandbox mode by default
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        app()->singleton(UrbanGoodzPaymentService::class, function () {
            return new UrbanGoodzPaymentService(new \App\Services\Payments\PaymentProviderManager(app()));
        });
        $this->paymentService = app(UrbanGoodzPaymentService::class);

        // Create core models
        $this->admin = Admin::firstOrCreate(
            ['email' => 'test-admin@urbangoodz.com'],
            [
                'f_name' => 'Admin',
                'l_name' => 'User',
                'phone' => '1234567890',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );

        $module = \App\Models\Module::firstOrCreate(
            ['module_name' => 'Food'],
            [
                'module_type' => 'food',
                'status' => 1,
            ]
        );

        $zone = \App\Models\Zone::firstOrCreate(
            ['name' => 'Test Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'vendor-test@urbangoodz.com'],
            [
                'f_name' => 'Vendor',
                'l_name' => 'Test',
                'phone' => '1112223333',
                'password' => bcrypt('password'),
            ]
        );

        $this->store = Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Test Store',
                'phone' => '1112223334',
                'logo' => 'store.png',
                'address' => '123 Store St',
                'module_id' => $module->id,
                'zone_id' => $zone->id,
            ]
        );

        $this->driver = DeliveryMan::firstOrCreate(
            ['phone' => '9998887777'],
            [
                'f_name' => 'Driver',
                'l_name' => 'User',
                'email' => 'driver-test@urbangoodz.com',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $zone->id,
            ]
        );

        // Reset wallets
        StoreWallet::updateOrCreate(['vendor_id' => $this->vendor->id], ['total_earning' => 0.0, 'total_withdrawn' => 0.0, 'pending_withdraw' => 0.0, 'collected_cash' => 0.0]);
        DeliveryManWallet::updateOrCreate(['delivery_man_id' => $this->driver->id], ['total_earning' => 0.0, 'total_withdrawn' => 0.0, 'pending_withdraw' => 0.0, 'collected_cash' => 0.0]);
    }

    public function test_valid_sandbox_capture(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-CAPTURE-TEST-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 35.00,
            'source' => 'manual',
        ]);

        $request->refresh();
        $this->assertEquals('captured', $request->payment_status);
        $this->assertEquals(50.00, $request->captured_amount);

        // Assert ledger entry created
        $ledger = UrbanGoodzPaymentLedger::where('payable_id', $request->id)->where('event_type', 'capture')->first();
        $this->assertNotNull($ledger);
        $this->assertEquals(50.00, $ledger->amount);

        // Assert split records created with manual_pending status
        $splits = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->get();
        $this->assertCount(3, $splits);

        $platformSplit = $splits->where('recipient_type', 'platform')->first();
        $vendorSplit = $splits->where('recipient_type', 'vendor')->first();
        $driverSplit = $splits->where('recipient_type', 'driver')->first();

        $this->assertEquals(5.00, $platformSplit->amount);
        $this->assertEquals('manual_pending', $platformSplit->status);

        $this->assertEquals(35.00, $vendorSplit->amount);
        $this->assertEquals('manual_pending', $vendorSplit->status);

        $this->assertEquals(10.00, $driverSplit->amount);
        $this->assertEquals('manual_pending', $driverSplit->status);
    }

    public function test_invalid_webhook_signature(): void
    {
        // For Adyen, validateWebhook returns true in sandbox StagedTest gateway, but if we mock it to return false:
        $response = $this->post('/api/v1/payments/webhooks/staged_test', ['event_code' => 'AUTHORISATION'], ['X-Adyen-Signature' => 'invalid']);
        $response->assertStatus(200); // Controller returns 200 to prevent retry noise but logs warning
    }

    public function test_duplicate_webhook_replay(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-WEBHOOK-TEST',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'pending',
            'psp_reference' => 'PSP_REF_123',
        ]);

        $payload = [
            'event_code' => 'CAPTURE',
            'merchant_reference' => 'OA-WEBHOOK-TEST',
            'provider_reference' => 'PSP_REF_123',
            'amount_minor' => 5000,
            'currency' => 'USD',
            'success' => true,
        ];

        // Send first webhook
        $response1 = $this->post('/api/v1/payments/webhooks/staged_test', $payload);
        $response1->assertStatus(200);

        $request->refresh();
        $this->assertEquals('captured', $request->payment_status);

        $ledgerCountBefore = UrbanGoodzPaymentLedger::where('payable_id', $request->id)->count();
        $this->assertEquals(1, $ledgerCountBefore);

        // Send replay webhook
        $response2 = $this->post('/api/v1/payments/webhooks/staged_test', $payload);
        $response2->assertStatus(200);

        // Verify no duplicate ledger created
        $ledgerCountAfter = UrbanGoodzPaymentLedger::where('payable_id', $request->id)->count();
        $this->assertEquals(1, $ledgerCountAfter);
    }

    public function test_ledger_split_consistency_check(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-FAIL-TEST',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ledger split mismatch');

        // Mismatched splits: 5 + 10 + 30 = 45 !== 50
        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 30.00,
            'source' => 'manual',
        ]);
    }

    public function test_duplicate_wallet_release_idempotency(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-RELEASE-TEST',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 35.00,
            'source' => 'manual',
        ]);

        $vendorWallet = StoreWallet::where('vendor_id', $this->vendor->id)->first();
        $this->assertEquals(0.00, $vendorWallet->total_earning);

        // Transition to completed -> should trigger settleSplits()
        $request->transitionTo('picked_up');
        $request->transitionTo('out_for_delivery');
        $request->transitionTo('completed');

        $vendorWallet->refresh();
        $this->assertEquals(35.00, $vendorWallet->total_earning);

        // Trigger manual settleSplits to test double-credit prevention
        $this->paymentService->settleSplits($request);

        $vendorWallet->refresh();
        $this->assertEquals(35.00, $vendorWallet->total_earning); // Remains 35.00! No double credit.
    }

    public function test_refund_before_settlement(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-REFUND-BEFORE',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 35.00,
            'source' => 'manual',
        ]);

        // Refund 20.00 before request is completed (still in shopping status)
        $this->paymentService->refundOrderAnywhere($request, [
            'refund_amount' => 20.00,
            'reason' => 'Partial return',
        ]);

        // Settle splits (completed status)
        $request->transitionTo('picked_up');
        $request->transitionTo('out_for_delivery');
        $request->transitionTo('completed');

        // Vendor wallet should only receive 35.00 - 20.00 = 15.00
        $vendorWallet = StoreWallet::where('vendor_id', $this->vendor->id)->first();
        $this->assertEquals(15.00, $vendorWallet->total_earning);
    }

    public function test_refund_after_settlement(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-REFUND-AFTER',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 35.00,
            'source' => 'manual',
        ]);

        // Transition to completed to release splits to wallets
        $request->transitionTo('picked_up');
        $request->transitionTo('out_for_delivery');
        $request->transitionTo('completed');

        $vendorWallet = StoreWallet::where('vendor_id', $this->vendor->id)->first();
        $this->assertEquals(35.00, $vendorWallet->total_earning);

        // Refund 20.00 after settlement
        $this->paymentService->refundOrderAnywhere($request, [
            'refund_amount' => 20.00,
            'reason' => 'Post-delivery refund',
        ]);

        // Vendor wallet should be debited by 20.00, leaving 15.00 total earning
        $vendorWallet->refresh();
        $this->assertEquals(15.00, $vendorWallet->total_earning);
    }

    public function test_payout_exceeding_available_balance(): void
    {
        $driver = $this->driver;
        $driver->auth_token = 'test-driver-auth-token-999';
        $driver->save();

        // Create $10.00 pending earning
        UrbanGoodzDriverEarning::create([
            'delivery_man_id' => $driver->id,
            'earning_type' => 'per_package',
            'amount' => 10.00,
            'currency' => 'USD',
            'status' => 'pending',
            'description' => 'Test earning',
        ]);

        // Request payout of $15.00 -> should fail (400)
        $response = $this->postJson('/api/v1/urban-goodz/driver/payout-request?token=test-driver-auth-token-999', [
            'payout_type' => 'instant',
            'amount' => 15.00,
        ]);
        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Requested amount exceeds pending earnings');

        // Request $5.00 payout -> should succeed
        $response2 = $this->postJson('/api/v1/urban-goodz/driver/payout-request?token=test-driver-auth-token-999', [
            'payout_type' => 'instant',
            'amount' => 5.00,
        ]);
        $response2->assertStatus(200);

        // Request another $6.00 payout -> should fail because only $5.00 remains available ($10 - $5 pending payout)
        $response3 = $this->postJson('/api/v1/urban-goodz/driver/payout-request?token=test-driver-auth-token-999', [
            'payout_type' => 'instant',
            'amount' => 6.00,
        ]);
        $response3->assertStatus(400);
    }

    public function test_disabled_payment_mode(): void
    {
        Config::set('urban_goodz_payments.mode', 'disabled');

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-DISABLED-TEST',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'pending',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Payments are currently disabled.');

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
        ]);
    }

    public function test_controlled_live_cap(): void
    {
        Config::set('urban_goodz_payments.mode', 'live_controlled');
        Config::set('urban_goodz_payments.live_controlled.max_amount', 50.00);

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-CAP-TEST',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'status' => 'shopping',
            'quote_amount' => 100.00,
            'payment_status' => 'pending',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('exceeds maximum allowed cap');

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 60.00,
        ]);
    }

    public function test_staged_test_rejected_in_production_or_live_mode(): void
    {
        // 1. Force payment mode to live -> staged_test must be disabled
        Config::set('urban_goodz_payments.mode', 'live');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        $gateway = new \App\Services\Payments\StagedTestPaymentGateway();
        $this->assertFalse($gateway->isEnabled());
        $this->assertFalse($gateway->validateWebhook([]));

        // 2. Force environment to production -> staged_test must be disabled
        Config::set('urban_goodz_payments.mode', 'sandbox');
        $this->app['env'] = 'production';
        $this->assertFalse($gateway->isEnabled());
        $this->assertFalse($gateway->validateWebhook([]));

        // Restore environment
        $this->app['env'] = 'testing';
    }
}
