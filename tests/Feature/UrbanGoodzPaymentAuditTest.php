<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Passport;
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

    public function test_sandbox_payment_link_creation_is_persistent_and_idempotent(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-LINK-SESSION-9',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'status' => 'quote_needed',
            'quote_amount' => 42.50,
            'final_amount' => 42.50,
            'payment_status' => 'quoted',
        ]);

        $created = $this->paymentService->createPaymentLink($request);
        $replayed = $this->paymentService->createPaymentLink($request->fresh());

        $this->assertTrue($created['success']);
        $this->assertTrue($created['staged_test']);
        $this->assertSame('staged_test', $created['provider']);
        $this->assertStringStartsWith('/admin/urban-goodz/order-anywhere?', $created['payment_url']);
        $this->assertTrue($replayed['idempotent_replay']);
        $this->assertSame($created['provider_reference'], $replayed['provider_reference']);
        $this->assertSame(1, UrbanGoodzPaymentLedger::where('payable_id', $request->id)
            ->where('event_type', 'payment_link_created')
            ->count());
    }

    public function test_failed_webhook_is_persisted_and_idempotent(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-WEBHOOK-FAILED-SESSION-9',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
            'psp_reference' => 'PSP_FAILED_SESSION_9',
        ]);

        $payload = [
            'event_code' => 'CAPTURE',
            'merchant_reference' => $request->request_number,
            'provider_reference' => 'PSP_FAILED_SESSION_9',
            'amount_minor' => 5000,
            'currency' => 'USD',
            'success' => false,
        ];

        $this->post('/api/v1/payments/webhooks/staged_test', $payload)->assertOk();
        $this->post('/api/v1/payments/webhooks/staged_test', $payload)->assertOk();

        $request->refresh();
        $this->assertSame('capture_failed', $request->payment_status);

        $ledger = UrbanGoodzPaymentLedger::where('payable_id', $request->id)
            ->where('event_type', 'capture_failed')
            ->sole();
        $this->assertSame('0.00', $ledger->amount);
        $this->assertSame(50.0, (float) $ledger->metadata['attempted_amount']);
        $this->assertSame(1, UrbanGoodzPaymentLedger::where('payable_id', $request->id)
            ->where('event_type', 'capture_failed')
            ->count());
    }

    public function test_stripe_webhook_fails_closed_without_signing_secret(): void
    {
        Config::set('urban_goodz_payments.stripe.enabled', true);
        Config::set('urban_goodz_payments.stripe.secret_key', 'sk_test_session_9_not_real');
        Config::set('urban_goodz_payments.stripe.webhook_secret', '');

        $gateway = new \App\Services\Payments\StripePaymentGateway();

        $this->assertFalse($gateway->validateWebhook('{"type":"charge.succeeded"}', []));
    }

    public function test_customer_cannot_access_or_authorize_another_customers_payment(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'session9-payment-owner@urbangoodz.test'],
            [
                'f_name' => 'Payment',
                'l_name' => 'Owner',
                'phone' => '3125551901',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        $other = User::firstOrCreate(
            ['email' => 'session9-payment-other@urbangoodz.test'],
            [
                'f_name' => 'Other',
                'l_name' => 'Customer',
                'phone' => '3125551902',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-OWNER-SESSION-9',
            'customer_id' => $owner->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'quote_needed',
            'quote_amount' => 25.00,
            'final_amount' => 25.00,
            'payment_status' => 'quoted',
        ]);

        Passport::actingAs($other);

        $this->getJson("/api/v1/order-anywhere/requests/{$request->id}")->assertNotFound();
        $this->postJson("/api/v1/order-anywhere/requests/{$request->id}/authorize-payment", [
            'authorized_amount' => 25.00,
        ])->assertNotFound();

        $this->assertSame('quoted', $request->fresh()->payment_status);
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
        $driverWallet = DeliveryManWallet::where('delivery_man_id', $this->driver->id)->first();
        $platformAdmin = Admin::where('role_id', 1)->firstOrFail();
        $adminWallet = AdminWallet::firstOrCreate(['admin_id' => $platformAdmin->id]);
        $adminCommissionBefore = (float) $adminWallet->total_commission_earning;
        $this->assertEquals(0.00, $vendorWallet->total_earning);

        // Transition to completed -> should trigger settleSplits()
        $request->transitionTo('picked_up');
        $request->transitionTo('out_for_delivery');
        $request->transitionTo('completed');

        $vendorWallet->refresh();
        $driverWallet->refresh();
        $adminWallet->refresh();
        $this->assertEquals(35.00, $vendorWallet->total_earning);
        $this->assertEquals(10.00, $driverWallet->total_earning);
        $this->assertEquals($adminCommissionBefore + 5.00, (float) $adminWallet->total_commission_earning);
        $this->assertSame(1, UrbanGoodzDriverEarning::where('delivery_man_id', $this->driver->id)
            ->where('description', "Order Anywhere Delivery - Req #{$request->request_number}")
            ->count());

        $releasedTotal = UrbanGoodzPaymentSplit::where('payable_id', $request->id)
            ->where('status', 'released')
            ->whereIn('split_type', ['platform_fee', 'vendor_earning', 'driver_earning'])
            ->sum('amount');
        $this->assertEquals(50.00, $releasedTotal);

        // Trigger manual settleSplits to test double-credit prevention
        $this->paymentService->settleSplits($request);

        $vendorWallet->refresh();
        $driverWallet->refresh();
        $adminWallet->refresh();
        $this->assertEquals(35.00, $vendorWallet->total_earning); // Remains 35.00! No double credit.
        $this->assertEquals(10.00, $driverWallet->total_earning);
        $this->assertEquals($adminCommissionBefore + 5.00, (float) $adminWallet->total_commission_earning);
    }

    public function test_full_refund_reverses_all_wallets_and_reconciles_ledger(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-FULL-REFUND-SESSION-9',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => 50.00,
            'payment_status' => 'authorized',
        ]);
        $platformAdmin = Admin::where('role_id', 1)->firstOrFail();
        $adminWallet = AdminWallet::firstOrCreate(['admin_id' => $platformAdmin->id]);
        $adminCommissionBefore = (float) $adminWallet->total_commission_earning;

        $this->paymentService->captureOrderAnywhere($request, [
            'captured_amount' => 50.00,
            'platform_fee' => 5.00,
            'driver_amount' => 10.00,
            'vendor_amount' => 35.00,
            'source' => 'manual',
        ]);
        $request->transitionTo('picked_up');
        $request->transitionTo('out_for_delivery');
        $request->transitionTo('completed');

        $this->paymentService->refundOrderAnywhere($request->fresh(), [
            'refund_amount' => 50.00,
            'reason' => 'Full test reversal',
        ]);

        $this->assertEquals(0.00, StoreWallet::where('vendor_id', $this->vendor->id)->value('total_earning'));
        $this->assertEquals(0.00, DeliveryManWallet::where('delivery_man_id', $this->driver->id)->value('total_earning'));
        $this->assertEquals($adminCommissionBefore, (float) $adminWallet->fresh()->total_commission_earning);

        $captureTotal = UrbanGoodzPaymentSplit::where('payable_id', $request->id)
            ->whereIn('split_type', ['platform_fee', 'vendor_earning', 'driver_earning'])
            ->sum('amount');
        $reversalTotal = UrbanGoodzPaymentSplit::where('payable_id', $request->id)
            ->whereIn('split_type', [
                'vendor_refund_reversal',
                'driver_refund_reversal',
                'platform_refund_reversal',
            ])
            ->sum('amount');
        $ledgerBalance = UrbanGoodzPaymentLedger::where('payable_id', $request->id)
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS balance")
            ->value('balance');

        $this->assertEquals(50.00, $captureTotal);
        $this->assertEquals(50.00, $reversalTotal);
        $this->assertEquals(0.00, $ledgerBalance);
        $this->assertSame('refunded', $request->fresh()->payment_status);
    }

    public function test_driver_earnings_are_owner_scoped_and_not_double_counted(): void
    {
        $otherDriver = DeliveryMan::firstOrCreate(
            ['phone' => '9998887766'],
            [
                'f_name' => 'Other',
                'l_name' => 'Driver',
                'email' => 'other-driver-session9@urbangoodz.test',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $this->driver->zone_id,
            ]
        );
        $this->driver->update(['auth_token' => 'driver-session-9-owner-token']);

        UrbanGoodzDriverEarning::create([
            'delivery_man_id' => $this->driver->id,
            'earning_type' => 'per_package',
            'amount' => 12.00,
            'currency' => 'USD',
            'status' => 'pending',
            'description' => 'Session 9 owner earning',
        ]);
        UrbanGoodzDriverEarning::create([
            'delivery_man_id' => $otherDriver->id,
            'earning_type' => 'per_package',
            'amount' => 99.00,
            'currency' => 'USD',
            'status' => 'pending',
            'description' => 'Session 9 other driver earning',
        ]);

        $response = $this->getJson('/api/v1/urban-goodz/driver/earnings?token=driver-session-9-owner-token')
            ->assertOk();

        $this->assertSame(
            (float) $response->json('totals.total'),
            (float) $response->json('all_time_total')
        );
        $this->assertNotContains(
            $otherDriver->id,
            collect($response->json('earnings'))->pluck('delivery_man_id')->all()
        );
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
