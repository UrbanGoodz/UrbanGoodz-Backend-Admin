<?php

namespace Tests\Feature;

use App\Exceptions\PaymentFinalizationConflictException;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\Module;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzPaymentFinalization;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\UrbanGoodzWebhookEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\Payments\PaymentProviderManager;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzPaymentFinalizationIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'whsec_urban_goodz_test_only';

    private UrbanGoodzPaymentService $payments;
    private User $customer;
    private Vendor $vendor;
    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'stripe');
        Config::set('urban_goodz_payments.currency', 'USD');
        Config::set('urban_goodz_payments.default_platform_fee_percent', 10);
        Config::set('urban_goodz_payments.stripe.enabled', true);
        Config::set('urban_goodz_payments.stripe.secret_key', 'sk_test_local_only');
        Config::set('urban_goodz_payments.stripe.webhook_secret', self::WEBHOOK_SECRET);

        $this->payments = new UrbanGoodzPaymentService(new PaymentProviderManager(app()));

        $module = Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );
        $zone = Zone::firstOrCreate(
            ['name' => 'Payment Finalization Test Zone'],
            [
                'coordinates' => new Expression("ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"),
                'status' => 1,
            ]
        );
        $this->customer = User::firstOrCreate(
            ['email' => 'payment-finalization@urbangoodz.test'],
            [
                'f_name' => 'Payment',
                'l_name' => 'Customer',
                'phone' => '3125557001',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        $this->customer->update(['cm_firebase_token' => null]);
        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'payment-finalization-vendor@urbangoodz.test'],
            [
                'f_name' => 'Payment',
                'l_name' => 'Vendor',
                'phone' => '3125557002',
                'password' => bcrypt('password'),
                'status' => 1,
            ]
        );
        Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Payment Finalization Store',
                'phone' => '3125557003',
                'logo' => 'store.png',
                'address' => '500 Test Avenue',
                'module_id' => $module->id,
                'zone_id' => $zone->id,
            ]
        );
        $this->owner = Admin::where('role_id', 1)->firstOrFail();
        StoreWallet::updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            ['total_earning' => 0, 'total_withdrawn' => 0, 'pending_withdraw' => 0, 'collected_cash' => 0]
        );
        AdminWallet::firstOrCreate(['admin_id' => $this->owner->id]);
    }

    public function test_payment_intent_first_then_charge_is_one_exact_finalization(): void
    {
        $request = $this->authorizedRequest('UG-FINALIZE-PI-FIRST', 'pi_ug_pi_first');
        $staleChargeDelivery = OrderAnywhereRequest::findOrFail($request->id);
        $vendorBefore = $this->vendorBalance();
        $platformBefore = $this->platformBalance();

        $first = $this->payments->finalizeCustomerPayment($request, $this->captureData(
            'pi_ug_pi_first',
            'evt_pi_first'
        ));
        $second = $this->payments->finalizeCustomerPayment($staleChargeDelivery, $this->captureData(
            'pi_ug_pi_first',
            'evt_charge_second',
            'ch_ug_second'
        ));

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertExactFiveDollarFinalization($request, $vendorBefore, $platformBefore);
    }

    public function test_charge_first_then_payment_intent_is_one_exact_finalization(): void
    {
        $request = $this->authorizedRequest('UG-FINALIZE-CHARGE-FIRST', 'pi_ug_charge_first');
        $stalePaymentIntentDelivery = OrderAnywhereRequest::findOrFail($request->id);
        $vendorBefore = $this->vendorBalance();
        $platformBefore = $this->platformBalance();

        $first = $this->payments->finalizeCustomerPayment($request, $this->captureData(
            'pi_ug_charge_first',
            'evt_charge_first',
            'ch_ug_first'
        ));
        $second = $this->payments->finalizeCustomerPayment($stalePaymentIntentDelivery, $this->captureData(
            'pi_ug_charge_first',
            'evt_pi_second'
        ));

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertExactFiveDollarFinalization($request, $vendorBefore, $platformBefore);
    }

    public function test_partial_split_finalization_recovers_without_duplicate_rows(): void
    {
        $request = $this->authorizedRequest('UG-FINALIZE-PARTIAL', 'pi_ug_partial');
        UrbanGoodzPaymentLedger::create([
            'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
            'feature' => 'order_anywhere',
            'payable_type' => OrderAnywhereRequest::class,
            'payable_id' => $request->id,
            'event_type' => 'split_calculated',
            'direction' => 'neutral',
            'amount' => 5.00,
            'currency' => 'USD',
            'payment_status' => 'quoted',
            'idempotency_key' => "order_anywhere:stripe:{$request->id}:split_calculated:5.00",
        ]);

        $result = $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('pi_ug_partial', 'evt_partial')
        );

        $this->assertFalse($result->alreadyProcessed);
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(500, $this->splitCents($request));
    }

    public function test_same_amount_with_different_payment_intent_fails_without_money_mutation(): void
    {
        $request = $this->authorizedRequest('UG-FINALIZE-CONFLICT-PI', 'pi_ug_original');
        $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('pi_ug_original', 'evt_original')
        );
        $vendorAfterFirst = $this->vendorBalance();
        $platformAfterFirst = $this->platformBalance();

        try {
            $this->payments->finalizeCustomerPayment(
                $request->fresh(),
                $this->captureData('pi_ug_different', 'evt_conflicting')
            );
            $this->fail('A different PaymentIntent must not be accepted as a replay.');
        } catch (PaymentFinalizationConflictException) {
            $this->assertSame(1, UrbanGoodzPaymentFinalization::where('payable_id', $request->id)->count());
            $this->assertSame(1, $this->ledgerCount($request, 'capture'));
            $this->assertSame($vendorAfterFirst, $this->vendorBalance());
            $this->assertSame($platformAfterFirst, $this->platformBalance());
        }
    }

    public function test_conflicting_internal_reference_fails_before_capture(): void
    {
        $request = $this->authorizedRequest('UG-FINALIZE-CONFLICT-REF', 'pi_ug_ref_conflict');

        $this->expectException(PaymentFinalizationConflictException::class);
        try {
            $this->payments->finalizeCustomerPayment($request, array_merge(
                $this->captureData('pi_ug_ref_conflict', 'evt_ref_conflict'),
                ['internal_reference' => 'NOT-THE-ORDER']
            ));
        } finally {
            $this->assertSame('authorized', $request->fresh()->payment_status);
            $this->assertSame(0, $this->ledgerCount($request, 'capture'));
            $this->assertSame(0, UrbanGoodzPaymentFinalization::where('payable_id', $request->id)->count());
        }
    }

    public function test_exact_event_replay_is_receipted_once_and_counted_as_duplicate(): void
    {
        $request = $this->authorizedRequest('UG-WEBHOOK-REPLAY', 'pi_ug_replay');
        $payload = $this->stripePayload(
            'evt_ug_exact_replay',
            'payment_intent.succeeded',
            $request->request_number,
            'pi_ug_replay'
        );

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $receipt = UrbanGoodzWebhookEvent::where('provider', 'stripe')
            ->where('event_id', 'evt_ug_exact_replay')
            ->firstOrFail();
        $this->assertSame('processed', $receipt->processing_status);
        $this->assertSame(1, $receipt->duplicate_count);
        $this->assertTrue($receipt->signature_valid);
        $this->assertSame(1, UrbanGoodzPaymentFinalization::where('payable_id', $request->id)->count());
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->paymentNotificationCount($request));
    }

    public function test_distinct_stripe_events_for_one_payment_intent_are_both_receipted(): void
    {
        $request = $this->authorizedRequest('UG-WEBHOOK-DISTINCT', 'pi_ug_distinct');
        $charge = $this->stripePayload(
            'evt_ug_charge',
            'charge.succeeded',
            $request->request_number,
            'pi_ug_distinct',
            'ch_ug_distinct'
        );
        $intent = $this->stripePayload(
            'evt_ug_intent',
            'payment_intent.succeeded',
            $request->request_number,
            'pi_ug_distinct'
        );

        $this->postStripeWebhook($charge)->assertOk();
        $this->postStripeWebhook($intent)->assertOk();

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_ug_charge',
            'payment_intent_id' => 'pi_ug_distinct',
            'charge_id' => 'ch_ug_distinct',
            'processing_status' => 'processed',
        ]);
        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_ug_intent',
            'payment_intent_id' => 'pi_ug_distinct',
            'processing_status' => 'already_processed',
        ]);
        $this->assertSame(1, UrbanGoodzPaymentFinalization::where('payable_id', $request->id)->count());
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(1, $this->paymentNotificationCount($request));
    }

    public function test_invalid_signature_is_safely_receipted_without_payment_mutation(): void
    {
        $request = $this->authorizedRequest('UG-WEBHOOK-BAD-SIGNATURE', 'pi_ug_bad_signature');
        $payload = $this->stripePayload(
            'evt_ug_bad_signature',
            'payment_intent.succeeded',
            $request->request_number,
            'pi_ug_bad_signature'
        );

        $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid', 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertOk();

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_ug_bad_signature',
            'processing_status' => 'failed',
            'signature_valid' => 0,
            'failure_type' => 'invalid_signature',
        ]);
        $this->assertSame('authorized', $request->fresh()->payment_status);
        $this->assertSame(0, $this->ledgerCount($request, 'capture'));
    }

    public function test_unknown_payment_is_receipted_as_unmatched_without_http_500(): void
    {
        $payload = $this->stripePayload(
            'evt_ug_unknown',
            'payment_intent.succeeded',
            'UG-NOT-A-REAL-ORDER',
            'pi_ug_unknown'
        );

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertDatabaseHas('urban_goodz_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_ug_unknown',
            'processing_status' => 'unmatched',
            'failure_type' => 'unknown_payment',
            'signature_valid' => 1,
        ]);
        $this->assertSame(0, UrbanGoodzPaymentFinalization::where('payment_intent_id', 'pi_ug_unknown')->count());
    }

    private function authorizedRequest(string $number, string $paymentIntent): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create([
            'request_number' => $number,
            'merchant_reference' => $number,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'shopping',
            'quote_amount' => 5.00,
            'authorized_amount' => 5.00,
            'payment_status' => 'authorized',
            'payment_provider' => 'stripe',
            'psp_reference' => $paymentIntent,
        ]);
    }

    private function captureData(string $paymentIntent, string $eventId, ?string $chargeId = null): array
    {
        return [
            'captured_amount' => 5.00,
            'payment_intent_id' => $paymentIntent,
            'psp_reference' => $paymentIntent,
            'capture_reference' => $paymentIntent,
            'charge_id' => $chargeId,
            'source' => 'webhook',
            'capture_idempotency_key' => "webhook:stripe:{$eventId}",
        ];
    }

    private function assertExactFiveDollarFinalization(
        OrderAnywhereRequest $request,
        int $vendorBefore,
        int $platformBefore
    ): void {
        $request->refresh();
        $finalization = UrbanGoodzPaymentFinalization::where('payable_id', $request->id)->firstOrFail();
        $this->assertSame('captured', $request->payment_status);
        $this->assertSame(500, (int) round((float) $request->captured_amount * 100));
        $this->assertSame(500, $finalization->amount_cents);
        $this->assertSame('completed', $finalization->status);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(500, $this->splitCents($request));
        $this->assertSame(450, $this->splitTypeCents($request, 'vendor_earning'));
        $this->assertSame(50, $this->splitTypeCents($request, 'platform_fee'));
        $this->assertSame($vendorBefore + 450, $this->vendorBalance());
        $this->assertSame($platformBefore + 50, $this->platformBalance());
        $this->assertSame(1, $this->paymentNotificationCount($request));
    }

    private function ledgerCount(OrderAnywhereRequest $request, string $eventType): int
    {
        return UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('event_type', $eventType)
            ->count();
    }

    private function splitCents(OrderAnywhereRequest $request): int
    {
        return UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->get()
            ->sum(fn (UrbanGoodzPaymentSplit $split) => (int) round((float) $split->amount * 100));
    }

    private function splitTypeCents(OrderAnywhereRequest $request, string $splitType): int
    {
        return (int) round((float) UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('split_type', $splitType)
            ->sum('amount') * 100);
    }

    private function vendorBalance(): int
    {
        return (int) round((float) StoreWallet::where('vendor_id', $this->vendor->id)
            ->value('total_earning') * 100);
    }

    private function platformBalance(): int
    {
        return (int) round((float) AdminWallet::where('admin_id', $this->owner->id)
            ->value('total_commission_earning') * 100);
    }

    private function paymentNotificationCount(OrderAnywhereRequest $request): int
    {
        return UserNotification::where('user_id', $this->customer->id)
            ->where('data', 'like', '%"request_number":"' . $request->request_number . '"%')
            ->where('data', 'like', '%"type":"urban_goodz_payment_captured"%')
            ->count();
    }

    private function stripePayload(
        string $eventId,
        string $eventType,
        string $internalReference,
        string $paymentIntent,
        ?string $chargeId = null
    ): string {
        $object = [
            'id' => $chargeId ?? $paymentIntent,
            'amount' => 500,
            'currency' => 'usd',
            'metadata' => ['merchant_reference' => $internalReference],
        ];
        if ($chargeId !== null) {
            $object['payment_intent'] = $paymentIntent;
        }

        return json_encode([
            'id' => $eventId,
            'type' => $eventType,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
    }

    private function postStripeWebhook(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }
}
