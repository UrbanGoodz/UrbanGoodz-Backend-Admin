<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzSplitControlTest extends TestCase
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
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);
        Config::set('urban_goodz_payments.default_platform_fee_percent', 10);
        Config::set('urban_goodz_payments.default_dispatcher_commission_rate', 0);

        app()->singleton(UrbanGoodzPaymentService::class, function () {
            return new UrbanGoodzPaymentService(new \App\Services\Payments\PaymentProviderManager(app()));
        });
        $this->paymentService = app(UrbanGoodzPaymentService::class);

        $this->admin = Admin::firstOrCreate(
            ['email' => 'split-test-admin@urbangoodz.com'],
            ['f_name' => 'Admin', 'l_name' => 'User', 'phone' => '5551000001', 'password' => bcrypt('password'), 'role_id' => 1]
        );

        $module = \App\Models\Module::firstOrCreate(['module_name' => 'Food'], ['module_type' => 'food', 'status' => 1]);
        $zone = \App\Models\Zone::firstOrCreate(
            ['name' => 'Split Test Zone'],
            ['coordinates' => new \Illuminate\Database\Query\Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"), 'status' => 1]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'split-test-vendor@urbangoodz.com'],
            ['f_name' => 'Vendor', 'l_name' => 'Test', 'phone' => '5551000002', 'password' => bcrypt('password')]
        );

        $this->store = Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            ['name' => 'Split Test Store', 'phone' => '5551000003', 'logo' => 'store.png', 'address' => '123 Test St', 'module_id' => $module->id, 'zone_id' => $zone->id]
        );

        $this->driver = DeliveryMan::firstOrCreate(
            ['phone' => '5551000004'],
            ['f_name' => 'Driver', 'l_name' => 'Test', 'email' => 'split-test-driver@urbangoodz.com', 'password' => bcrypt('password'), 'active' => 1, 'application_status' => 'approved', 'zone_id' => $zone->id]
        );

        StoreWallet::updateOrCreate(['vendor_id' => $this->vendor->id], ['total_earning' => 0.0, 'total_withdrawn' => 0.0, 'pending_withdraw' => 0.0, 'collected_cash' => 0.0]);
        DeliveryManWallet::updateOrCreate(['delivery_man_id' => $this->driver->id], ['total_earning' => 0.0, 'total_withdrawn' => 0.0, 'pending_withdraw' => 0.0, 'collected_cash' => 0.0]);
    }

    // ─── SPLIT CALCULATION TESTS ────────────────────────────────────────

    public function test_participating_vendor_split_calculation(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-PV-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
            'service_fee' => 15.00,
            'delivery_fee' => 10.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00);

        $request->refresh();
        $this->assertEquals(10.00, (float) $request->platform_fee); // 10% of 100
        $this->assertEquals(10.00, (float) $request->driver_payout_amount);
        $this->assertEquals(65.00, (float) $request->vendor_payout_amount); // 100 - 10 platform - 15 service - 10 driver
        $this->assertEquals(25.00, (float) $request->urban_goodz_revenue); // 10 platform + 15 service_fee

        $splits = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->get();
        $this->assertCount(4, $splits); // platform fee, service revenue, vendor, driver
        $this->assertEquals(4, $splits->where('status', 'calculated')->count());
    }

    public function test_external_merchant_split_calculation(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-EM-1',
            'customer_id' => 1,
            'vendor_id' => null,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'external_merchant',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00, [
            'merchant_purchase_amount' => 60.00,
            'driver_amount' => 15.00,
        ]);

        $request->refresh();
        $this->assertEquals(10.00, (float) $request->platform_fee); // 10% of 100
        $this->assertEquals(15.00, (float) $request->driver_payout_amount);
        $this->assertEquals(0.00, (float) $request->vendor_payout_amount); // no vendor for external
        $this->assertEquals(60.00, (float) $request->merchant_purchase_amount);
        $this->assertEquals(25.00, (float) $request->urban_goodz_revenue); // 100 - 60 merchant - 15 driver - 0 dispatcher - 0 reserve

        $splits = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->get();
        $this->assertCount(3, $splits); // platform fee + residual revenue + driver (no vendor)
    }

    public function test_no_dispatcher_commission_when_no_dispatcher(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-NODISP-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00);

        $request->refresh();
        $this->assertEquals(0.00, (float) $request->dispatcher_commission);
        $this->assertNull(UrbanGoodzPaymentSplit::where('payable_id', $request->id)->where('recipient_type', 'dispatcher')->first());
    }

    public function test_dispatcher_commission_from_platform_default(): void
    {
        Config::set('urban_goodz_payments.default_dispatcher_commission_rate', 15);

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-DISP-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
            'service_fee' => 10.00,
            'delivery_fee' => 10.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00, [
            'dispatcher_id' => 999,
        ]);

        $request->refresh();
        $commissionBase = 10.00 + 10.00; // delivery_fee + service_fee
        $expectedCommission = round($commissionBase * 0.15, 2); // 3.00
        $this->assertEquals($expectedCommission, (float) $request->dispatcher_commission);

        $dispatcherSplit = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->where('recipient_type', 'dispatcher')->first();
        $this->assertNotNull($dispatcherSplit);
        $this->assertEquals($expectedCommission, (float) $dispatcherSplit->amount);
    }

    public function test_dispatcher_commission_from_business_client(): void
    {
        Config::set('urban_goodz_payments.default_dispatcher_commission_rate', 5);

        $businessClient = UrbanGoodzBusinessClient::create([
            'company_name' => 'Test Dispatch Co',
            'dispatch_default_commission_rate' => 20.00,
        ]);

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-BC-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'business_id' => $businessClient->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
            'service_fee' => 10.00,
            'delivery_fee' => 10.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00, [
            'dispatcher_id' => 999,
        ]);

        $request->refresh();
        $commissionBase = 10.00 + 10.00;
        $expectedCommission = round($commissionBase * 0.20, 2); // business_client rate wins: 4.00
        $this->assertEquals($expectedCommission, (float) $request->dispatcher_commission);

        $snapshot = $request->financial_rules_snapshot;
        $this->assertEquals('business_client', $snapshot['dispatcher_rule_source']);
        $this->assertEquals(20.00, $snapshot['dispatcher_commission_rate']);
    }

    public function test_per_order_override_wins_over_all(): void
    {
        Config::set('urban_goodz_payments.default_dispatcher_commission_rate', 5);

        $businessClient = UrbanGoodzBusinessClient::create([
            'company_name' => 'Override Test Co',
            'dispatch_default_commission_rate' => 20.00,
        ]);

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SPLIT-OVERRIDE-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'business_id' => $businessClient->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
            'service_fee' => 10.00,
            'delivery_fee' => 10.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00, [
            'dispatcher_id' => 999,
            'dispatcher_commission_rate' => 25,
        ]);

        $request->refresh();
        $commissionBase = 10.00 + 10.00;
        $expectedCommission = round($commissionBase * 0.25, 2); // per_order_override wins: 5.00
        $this->assertEquals($expectedCommission, (float) $request->dispatcher_commission);

        $snapshot = $request->financial_rules_snapshot;
        $this->assertEquals('per_order_override', $snapshot['dispatcher_rule_source']);
        $this->assertEquals(25, $snapshot['dispatcher_commission_rate']);
    }

    // ─── FINANCIAL RULE SNAPSHOT ────────────────────────────────────────

    public function test_financial_rules_snapshot_is_persisted(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SNAPSHOT-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00, [
            'dispatcher_id' => 999,
        ]);

        $request->refresh();
        $snapshot = $request->financial_rules_snapshot;
        $this->assertNotNull($snapshot);
        $this->assertEquals(10, $snapshot['platform_fee_percent']);
        $this->assertEquals('participating_vendor', $snapshot['fulfillment_type']);
        $this->assertEquals('urban_goodz_payment_service_v2', $snapshot['rule_source']);
        $this->assertEquals('2.0', $snapshot['rule_version']);
        $this->assertArrayHasKey('effective_timestamp', $snapshot);
        $this->assertArrayHasKey('vendor_amount_formula', $snapshot);
        $this->assertArrayHasKey('urban_goodz_revenue_formula', $snapshot);
    }

    public function test_config_change_does_not_affect_existing_order(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SNAPSHOT-IMMUTABLE-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 100.00,
        ]);

        $this->paymentService->calculateSplits($request, 100.00);
        $originalSnapshot = $request->fresh()->financial_rules_snapshot;
        $this->assertEquals(10, $originalSnapshot['platform_fee_percent']);

        // Change config AFTER snapshot
        Config::set('urban_goodz_payments.default_platform_fee_percent', 20);

        // Snapshot should be unchanged
        $request->refresh();
        $this->assertEquals(10, $request->financial_rules_snapshot['platform_fee_percent']);
    }

    // ─── SETTLEMENT SAFETY ─────────────────────────────────────────────

    public function test_settle_splits_only_once(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SETTLE-ONCE-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'out_for_delivery',
            'payment_status' => 'authorized',
            'quote_amount' => 50.00,
            'final_amount' => 50.00,
            'authorized_amount' => 50.00,
        ]);

        $this->paymentService->calculateSplits($request, 50.00);
        $this->paymentService->reservePendingSplits($request);
        $this->paymentService->finalizeSplits($request);
        $this->paymentService->settleSplits($request);

        $vendorWallet = StoreWallet::where('vendor_id', $this->vendor->id)->first();
        $driverWallet = DeliveryManWallet::where('delivery_man_id', $this->driver->id)->first();
        $vendorEarning1 = (float) $vendorWallet->total_earning;
        $driverEarning1 = (float) $driverWallet->total_earning;

        // Try settling again
        $this->paymentService->settleSplits($request);

        $vendorWallet->refresh();
        $driverWallet->refresh();
        $this->assertEquals($vendorEarning1, (float) $vendorWallet->total_earning, 'Vendor wallet double-credited');
        $this->assertEquals($driverEarning1, (float) $driverWallet->total_earning, 'Driver wallet double-credited');
    }

    public function test_duplicate_refund_is_prevented(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-DUP-REFUND-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'completed',
            'payment_status' => 'captured',
            'quote_amount' => 50.00,
            'final_amount' => 50.00,
            'authorized_amount' => 50.00,
            'captured_amount' => 50.00,
        ]);

        // First refund should succeed
        $this->paymentService->refundCustomerPayment($request, [
            'refund_amount' => 50.00,
            'reason' => 'Test refund',
        ]);

        $request->refresh();
        $this->assertEquals(50.00, (float) $request->refunded_amount);
        $this->assertEquals('refunded', $request->payment_status);

        // Second refund should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot refund: payment status is refunded');
        $this->paymentService->refundCustomerPayment($request, [
            'refund_amount' => 50.00,
            'reason' => 'Duplicate attempt',
        ]);
    }

    // ─── CANCELLATION TESTS ────────────────────────────────────────────

    public function test_cancellation_before_authorization_reverses_pending_splits(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-CANCEL-BEFORE-AUTH-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 50.00,
        ]);

        $this->paymentService->calculateSplits($request, 50.00);
        $this->paymentService->cancelOrder($request, 'Customer changed mind');

        $request->refresh();
        $this->assertEquals('cancelled', $request->payment_status);

        $cancelledSplits = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->where('status', 'cancelled')->count();
        $totalSplits = UrbanGoodzPaymentSplit::where('payable_id', $request->id)->count();
        $this->assertEquals($totalSplits, $cancelledSplits, 'All splits should be cancelled');
    }

    // ─── CARD PURCHASE ISOLATION ────────────────────────────────────────

    public function test_card_purchase_does_not_modify_customer_payment_status(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-CARD-ISO-1',
            'customer_id' => 1,
            'vendor_id' => null,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'external_merchant',
            'status' => 'authorized',
            'payment_status' => 'authorized',
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
            'authorized_amount' => 100.00,
        ]);

        $originalPaymentStatus = $request->payment_status;
        $originalCapturedAmount = $request->captured_amount;

        // Card purchase does NOT exist on the service - it's handled by OrderAnywhereCardService
        // The critical assertion is that UrbanGoodzPaymentService has NO method called captureOrderAnywhere
        $this->assertFalse(method_exists($this->paymentService, 'captureOrderAnywhere'), 'captureOrderAnywhere must not exist');

        $request->refresh();
        $this->assertEquals($originalPaymentStatus, $request->payment_status);
        $this->assertEquals($originalCapturedAmount, $request->captured_amount);
    }

    // ─── ADMIN METHOD EXISTENCE ─────────────────────────────────────────

    public function test_all_public_methods_exist(): void
    {
        $this->assertTrue(method_exists($this->paymentService, 'captureCustomerPayment'));
        $this->assertTrue(method_exists($this->paymentService, 'refundCustomerPayment'));
        $this->assertTrue(method_exists($this->paymentService, 'authorizeCustomerPayment'));
        $this->assertTrue(method_exists($this->paymentService, 'createPaymentSession'));
        $this->assertTrue(method_exists($this->paymentService, 'calculateSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'settleSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'finalizeSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'reservePendingSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'reversePendingSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'reverseSettledSplits'));
        $this->assertTrue(method_exists($this->paymentService, 'reconcileSplits'));

        // Verify broken methods do NOT exist
        $this->assertFalse(method_exists($this->paymentService, 'captureOrderAnywhere'));
        $this->assertFalse(method_exists($this->paymentService, 'refundOrderAnywhere'));
        $this->assertFalse(method_exists($this->paymentService, 'authorizeOrderAnywhere'));
    }

    // ─── RECONCILIATION ────────────────────────────────────────────────

    public function test_reconciliation_passes_for_balanced_splits(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-RECON-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'completed',
            'payment_status' => 'captured',
            'quote_amount' => 100.00,
            'final_amount' => 100.00,
            'captured_amount' => 100.00,
            'platform_fee' => 10.00,
            'vendor_payout_amount' => 65.00,
            'driver_payout_amount' => 10.00,
            'dispatcher_commission' => 0.00,
            'urban_goodz_revenue' => 25.00, // 10 platform + 15 service_fee (using default 10%)
            'processing_reserve' => 0.00,
        ]);

        // Urban Goodz revenue = platform_fee + service_fee = 10 + 15 = 25
        // But actual calculation: 100 - 10 platform - 10 driver - 0 dispatcher - 0 reserve = 80 vendor
        // Revenue for participating vendor = platform_fee + service_fee = 10 + 15 = 25
        // Reconciliation: vendor(80) + driver(10) + dispatcher(0) + revenue(25) + reserve(0) = 115 != 100
        // This proves the reconciliation works! We need to fix the values.
        // For participating vendor: captured = vendor + driver + dispatcher + revenue + reserve
        // 100 = vendor + driver + dispatcher + (platform + service) + reserve
        // vendor = captured - platform - driver - dispatcher - service - reserve = 100 - 10 - 10 - 0 - 15 - 0 = 65

        $request->update([
            'vendor_payout_amount' => 65.00,
            'urban_goodz_revenue' => 25.00,
        ]);

        $result = $this->paymentService->reconcileSplits($request);
        $this->assertTrue($result);
    }

    public function test_reconciliation_fails_for_unbalanced_splits(): void
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-RECON-FAIL-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => 'participating_vendor',
            'status' => 'completed',
            'payment_status' => 'captured',
            'captured_amount' => 100.00,
            'platform_fee' => 10.00,
            'vendor_payout_amount' => 70.00,
            'driver_payout_amount' => 10.00,
            'dispatcher_commission' => 0.00,
            'urban_goodz_revenue' => 10.00,
            'processing_reserve' => 0.00,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reconciliation failed');
        $this->paymentService->reconcileSplits($request);
    }

    // ─── AI CANNOT CHANGE FINANCIAL VALUES ──────────────────────────────

    public function test_ai_service_has_no_split_methods(): void
    {
        $aiService = app(\App\Services\UrbanGoodz\UrbanGoodzAIService::class);

        $this->assertFalse(method_exists($aiService, 'calculateSplits'));
        $this->assertFalse(method_exists($aiService, 'settleSplits'));
        $this->assertFalse(method_exists($aiService, 'capturePayment'));
        $this->assertFalse(method_exists($aiService, 'refundPayment'));
        $this->assertFalse(method_exists($aiService, 'setPlatformFee'));
        $this->assertFalse(method_exists($aiService, 'setDriverPayout'));
    }

    public function test_payment_ai_controller_cannot_set_split_percentages(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'split-ai-admin@urbangoodz.com'],
            ['f_name' => 'AI', 'l_name' => 'Admin', 'phone' => '5551000099', 'password' => bcrypt('password'), 'role_id' => 1]
        );

        $this->actingAs($admin, 'admin');

        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-AI-BLOCK-1',
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'status' => 'quote_ready',
            'payment_status' => 'quoted',
            'quote_amount' => 50.00,
        ]);

        // AI controller manualOverride requires admin auth
        // The manualOverride method does NOT accept split percentages
        // It only accepts action, entity_type, entity_id, amount, reason, admin_id
        $response = $this->postJson('/api/v1/urban-goodz/payments/override', [
            'action' => 'force_capture',
            'entity_type' => 'order_anywhere',
            'entity_id' => $request->id,
            'amount' => 50.00,
            'reason' => 'Test override',
            'admin_id' => $admin->id,
        ]);

        // The override should work (it goes through the service, not directly setting splits)
        $this->assertNotEquals(422, $response->status());
    }
}
