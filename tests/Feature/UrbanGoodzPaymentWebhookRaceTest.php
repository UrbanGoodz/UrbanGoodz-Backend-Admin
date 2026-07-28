<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\DeliveryManWallet;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\UrbanGoodzWebhookEvent;
use App\Models\Vendor;
use App\Services\Payments\PaymentProviderManager;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UrbanGoodzPaymentWebhookRaceTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'whsec_urban_goodz_race_test';

    private UrbanGoodzPaymentService $payments;
    private Vendor $vendor;
    private DeliveryMan $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'stripe');
        Config::set('urban_goodz_payments.currency', 'USD');
        Config::set('urban_goodz_payments.stripe.enabled', true);
        Config::set('urban_goodz_payments.stripe.secret_key', 'sk_test_local_only');
        Config::set('urban_goodz_payments.stripe.webhook_secret', self::WEBHOOK_SECRET);

        $manager = new PaymentProviderManager($this->app);
        $this->app->instance(PaymentProviderManager::class, $manager);
        $this->payments = new UrbanGoodzPaymentService($manager);
        $this->app->instance(UrbanGoodzPaymentService::class, $this->payments);

        $module = \App\Models\Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
        );

        $zone = \App\Models\Zone::firstOrCreate(
            ['name' => 'Test Zone'],
            [
                'coordinates' => new \Illuminate\Database\Query\Expression(
                    "ST_GeomFromText('POLYGON((0 0, 0 100, 100 100, 100 0, 0 0))')"
                ),
                'status' => 1,
            ]
        );

        $this->vendor = Vendor::firstOrCreate(
            ['email' => 'vendor-payment-race@urbangoodz.test'],
            [
                'f_name' => 'Vendor',
                'l_name' => 'Race',
                'phone' => '1112223301',
                'password' => bcrypt('password'),
            ]
        );

        Store::firstOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'name' => 'Payment Race Store',
                'phone' => '1112223302',
                'logo' => 'store.png',
                'address' => '123 Store St',
                'module_id' => $module->id,
                'zone_id' => $zone->id,
            ]
        );

        $this->driver = DeliveryMan::firstOrCreate(
            ['phone' => '9998887701'],
            [
                'f_name' => 'Driver',
                'l_name' => 'Race',
                'email' => 'driver-payment-race@urbangoodz.test',
                'password' => bcrypt('password'),
                'active' => 1,
                'application_status' => 'approved',
                'zone_id' => $zone->id,
            ]
        );
    }

    private function authorizedRequest(string $number, float $amount): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create([
            'request_number' => $number,
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_PARTICIPATING_VENDOR,
            'status' => 'shopping',
            'quote_amount' => $amount,
            'authorized_amount' => $amount,
            'payment_provider' => 'stripe',
            'payment_status' => 'authorized',
        ]);
    }

    private function captureData(string $eventId, string $paymentIntent, float $amount): array
    {
        return [
            'captured_amount' => $amount,
            'capture_idempotency_key' => "webhook:stripe:{$eventId}",
            'capture_reference' => $paymentIntent,
            'payment_intent_id' => $paymentIntent,
            'psp_reference' => $paymentIntent,
            'currency' => 'USD',
            'source' => 'webhook',
        ];
    }

    private function ledgerCount(OrderAnywhereRequest $request, string $eventType): int
    {
        return UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('event_type', $eventType)
            ->count();
    }

    private function captureNotificationCount(OrderAnywhereRequest $request): int
    {
        return $request->activityLogs()->where('event', 'payment.capture')->count();
    }

    private function finalizationCount(OrderAnywhereRequest $request): int
    {
        return UrbanGoodzWebhookEvent::where('event_type', 'payment_finalization')
            ->where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->count();
    }

    private function signedStripePost(array $event)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }

    private function stripeEvent(
        string $eventId,
        string $type,
        string $merchantReference,
        string $paymentIntent,
        int $amountMinor,
        ?string $chargeId = null
    ): array {
        $object = [
            'id' => $chargeId ?? $paymentIntent,
            'amount' => $amountMinor,
            'currency' => 'usd',
            'metadata' => ['merchant_reference' => $merchantReference],
        ];

        if ($chargeId) {
            $object['payment_intent'] = $paymentIntent;
        }

        return [
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => $object],
        ];
    }

    public function test_payment_intent_event_first_then_charge_event(): void
    {
        $request = $this->authorizedRequest('OA-PI-FIRST', 50.00);
        $pi = 'pi_UG_PI_FIRST';

        $this->signedStripePost($this->stripeEvent(
            'evt_ug_pi_first',
            'payment_intent.succeeded',
            $request->request_number,
            $pi,
            5000
        ))->assertOk();
        $this->signedStripePost($this->stripeEvent(
            'evt_ug_charge_second',
            'charge.succeeded',
            $request->request_number,
            $pi,
            5000,
            'ch_UG_SECOND'
        ))->assertOk();

        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(1, $this->finalizationCount($request));
        $this->assertSame(2, UrbanGoodzWebhookEvent::where('event_id', 'like', 'evt_ug_%')->count());
    }

    public function test_charge_event_first_then_payment_intent_event(): void
    {
        $request = $this->authorizedRequest('OA-CHARGE-FIRST', 75.00);
        $pi = 'pi_UG_CHARGE_FIRST';

        $this->signedStripePost($this->stripeEvent(
            'evt_ug_charge_first',
            'charge.succeeded',
            $request->request_number,
            $pi,
            7500,
            'ch_UG_FIRST'
        ))->assertOk();
        $this->signedStripePost($this->stripeEvent(
            'evt_ug_pi_second',
            'payment_intent.succeeded',
            $request->request_number,
            $pi,
            7500
        ))->assertOk();

        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->finalizationCount($request));
        $this->assertSame(75.0, (float) $request->fresh()->captured_amount);
    }

    public function test_sequential_delivery_returns_existing_finalization(): void
    {
        $request = $this->authorizedRequest('OA-SEQUENTIAL', 30.00);
        $data = $this->captureData('evt_seq', 'pi_UG_SEQ', 30.00);

        $first = $this->payments->finalizeCustomerPayment($request, $data);
        $second = $this->payments->finalizeCustomerPayment($request->fresh(), $data);

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertSame($first->finalizationKey, $second->finalizationKey);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
    }

    public function test_concurrent_split_unique_index_race_converges(): void
    {
        $request = $this->authorizedRequest('OA-SPLIT-RACE', 50.00);
        $injected = false;

        DB::listen(function ($query) use (&$injected) {
            if ($injected
                || stripos(ltrim($query->sql), 'select') !== 0
                || ! str_contains($query->sql, 'urban_goodz_payment_ledgers')) {
                return;
            }

            foreach ($query->bindings as $binding) {
                if (is_string($binding) && str_contains($binding, ':split_calculated:')) {
                    $injected = true;
                    UrbanGoodzPaymentLedger::create([
                        'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
                        'feature' => 'order_anywhere',
                        'payable_type' => OrderAnywhereRequest::class,
                        'payable_id' => (int) explode(':', $binding)[2],
                        'event_type' => 'split_calculated',
                        'direction' => 'neutral',
                        'amount' => 50.00,
                        'currency' => 'USD',
                        'payment_status' => 'quoted',
                        'idempotency_key' => $binding,
                    ]);
                    return;
                }
            }
        });

        $result = $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('evt_split_race', 'pi_UG_SPLIT_RACE', 50.00)
        );

        $this->assertTrue($injected);
        $this->assertFalse($result->alreadyProcessed);
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
    }

    public function test_stale_concurrent_delivery_does_not_double_capture(): void
    {
        $request = $this->authorizedRequest('OA-STALE-RACE', 40.00);
        $stale = OrderAnywhereRequest::findOrFail($request->id);
        $pi = 'pi_UG_STALE_RACE';

        $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('evt_stale_pi', $pi, 40.00)
        );
        $result = $this->payments->finalizeCustomerPayment(
            $stale,
            $this->captureData('evt_stale_charge', $pi, 40.00)
        );

        $this->assertSame('authorized', $stale->payment_status);
        $this->assertTrue($result->alreadyProcessed);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(40.0, (float) $result->request->captured_amount);
    }

    public function test_duplicate_payment_intent_event_is_an_exact_replay(): void
    {
        $request = $this->authorizedRequest('OA-DUP-PI', 25.00);
        $event = $this->stripeEvent(
            'evt_ug_dup_pi',
            'payment_intent.succeeded',
            $request->request_number,
            'pi_UG_DUP_PI',
            2500
        );

        $this->signedStripePost($event)->assertOk();
        $this->signedStripePost($event)->assertOk();

        $receipt = UrbanGoodzWebhookEvent::where('provider', 'stripe')
            ->where('event_id', 'evt_ug_dup_pi')
            ->sole();
        $this->assertSame('succeeded', $receipt->status);
        $this->assertSame(1, $receipt->duplicate_count);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
    }

    public function test_duplicate_charge_event_is_an_exact_replay(): void
    {
        $request = $this->authorizedRequest('OA-DUP-CHARGE', 35.00);
        $event = $this->stripeEvent(
            'evt_ug_dup_charge',
            'charge.succeeded',
            $request->request_number,
            'pi_UG_DUP_CHARGE',
            3500,
            'ch_UG_DUP_CHARGE'
        );

        $this->signedStripePost($event)->assertOk();
        $this->signedStripePost($event)->assertOk();

        $receipt = UrbanGoodzWebhookEvent::where('event_id', 'evt_ug_dup_charge')->sole();
        $this->assertSame(1, $receipt->duplicate_count);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
    }

    public function test_distinct_event_ids_for_one_payment_intent_share_one_finalization(): void
    {
        $request = $this->authorizedRequest('OA-DISTINCT-EVENTS', 45.00);
        $pi = 'pi_UG_DISTINCT';

        $first = $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('evt_distinct_pi', $pi, 45.00)
        );
        $second = $this->payments->finalizeCustomerPayment(
            $request->fresh(),
            $this->captureData('evt_distinct_charge', $pi, 45.00)
        );

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertSame($first->finalizationKey, $second->finalizationKey);
        $this->assertSame(1, $this->finalizationCount($request));
    }

    public function test_exactly_one_recipient_balance_mutation(): void
    {
        $request = $this->authorizedRequest('OA-BALANCE-ONCE', 60.00);
        $pi = 'pi_UG_BALANCE';

        $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('evt_balance_pi', $pi, 60.00)
        );
        $vendorAfterFirst = (float) StoreWallet::where('vendor_id', $this->vendor->id)->value('total_earning');
        $driverAfterFirst = (float) DeliveryManWallet::where('delivery_man_id', $this->driver->id)->value('total_earning');

        $this->payments->finalizeCustomerPayment(
            $request->fresh(),
            $this->captureData('evt_balance_charge', $pi, 60.00)
        );

        $this->assertSame(
            $vendorAfterFirst,
            (float) StoreWallet::where('vendor_id', $this->vendor->id)->value('total_earning')
        );
        $this->assertSame(
            $driverAfterFirst,
            (float) DeliveryManWallet::where('delivery_man_id', $this->driver->id)->value('total_earning')
        );
        $this->assertGreaterThan(0, $vendorAfterFirst);
    }

    public function test_exactly_one_capture_notification_is_written(): void
    {
        $request = $this->authorizedRequest('OA-NOTIFICATION-ONCE', 45.00);
        $pi = 'pi_UG_NOTIFICATION';

        $this->payments->finalizeCustomerPayment(
            $request,
            $this->captureData('evt_notification_pi', $pi, 45.00)
        );
        $this->payments->finalizeCustomerPayment(
            $request->fresh(),
            $this->captureData('evt_notification_charge', $pi, 45.00)
        );

        $this->assertSame(1, $this->captureNotificationCount($request));
    }

    public function test_partial_captured_state_recovers_missing_splits_and_finalization(): void
    {
        $request = $this->authorizedRequest('OA-PARTIAL-RECOVERY', 55.00);
        $pi = 'pi_UG_PARTIAL';
        $request->update([
            'payment_status' => 'captured',
            'captured_amount' => 55.00,
            'capture_reference' => $pi,
            'psp_reference' => $pi,
        ]);
        UrbanGoodzPaymentLedger::create([
            'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
            'feature' => 'order_anywhere',
            'payable_type' => OrderAnywhereRequest::class,
            'payable_id' => $request->id,
            'event_type' => 'capture',
            'direction' => 'credit',
            'amount' => 55.00,
            'currency' => 'USD',
            'payment_status' => 'captured',
            'reference' => $pi,
            'idempotency_key' => 'legacy-partial-capture:' . $request->id,
        ]);

        $result = $this->payments->finalizeCustomerPayment(
            $request->fresh(),
            $this->captureData('evt_partial_recovery', $pi, 55.00)
        );

        $splits = UrbanGoodzPaymentSplit::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->get();
        $this->assertTrue($result->alreadyProcessed);
        $this->assertNotEmpty($splits);
        $this->assertSame(['released'], $splits->pluck('status')->unique()->values()->all());
        $this->assertSame(55.0, (float) $splits->sum('amount'));
        $this->assertSame(1, $this->finalizationCount($request));
        $this->assertSame(1, $this->captureNotificationCount($request));
    }

    public function test_stale_payment_status_recovers_from_completed_finalization(): void
    {
        $request = $this->authorizedRequest('OA-STALE-STATUS', 20.00);
        $data = $this->captureData('evt_stale_status_first', 'pi_UG_STALE_STATUS', 20.00);
        $this->payments->finalizeCustomerPayment($request, $data);
        $request->update(['payment_status' => 'authorized']);

        $result = $this->payments->finalizeCustomerPayment($request->fresh(), $data);

        $this->assertTrue($result->alreadyProcessed);
        $this->assertSame('captured', $result->request->payment_status);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->captureNotificationCount($request));
    }

    public function test_same_amount_with_different_payment_intents_remains_distinct(): void
    {
        $firstRequest = $this->authorizedRequest('OA-SAME-AMOUNT-A', 50.00);
        $secondRequest = $this->authorizedRequest('OA-SAME-AMOUNT-B', 50.00);

        $first = $this->payments->finalizeCustomerPayment(
            $firstRequest,
            $this->captureData('evt_same_a', 'pi_UG_SAME_A', 50.00)
        );
        $second = $this->payments->finalizeCustomerPayment(
            $secondRequest,
            $this->captureData('evt_same_b', 'pi_UG_SAME_B', 50.00)
        );

        $this->assertNotSame($first->finalizationKey, $second->finalizationKey);
        $this->assertSame(1, $this->ledgerCount($firstRequest, 'capture'));
        $this->assertSame(1, $this->ledgerCount($secondRequest, 'capture'));
    }

    public function test_same_payment_intent_with_conflicting_internal_reference_fails_safely(): void
    {
        $firstRequest = $this->authorizedRequest('OA-CONFLICT-A', 80.00);
        $secondRequest = $this->authorizedRequest('OA-CONFLICT-B', 80.00);
        $pi = 'pi_UG_CONFLICT';
        $this->payments->finalizeCustomerPayment(
            $firstRequest,
            $this->captureData('evt_conflict_a', $pi, 80.00)
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('conflicts with another internal payment reference');

        $this->payments->finalizeCustomerPayment(
            $secondRequest,
            $this->captureData('evt_conflict_b', $pi, 80.00)
        );
    }

    public function test_invalid_signature_is_rejected_without_receipt_or_money_mutation(): void
    {
        $payload = json_encode($this->stripeEvent(
            'evt_invalid_signature',
            'payment_intent.succeeded',
            'OA-NOT-TRUSTED',
            'pi_UG_INVALID',
            1000
        ), JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/payments/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
            ],
            $payload
        )->assertOk();

        $this->assertSame(0, UrbanGoodzWebhookEvent::where('event_id', 'evt_invalid_signature')->count());
        $this->assertSame(0, UrbanGoodzPaymentLedger::where('reference', 'pi_UG_INVALID')->count());
    }

    public function test_unknown_payment_is_recorded_as_sanitized_failure(): void
    {
        $this->signedStripePost($this->stripeEvent(
            'evt_unknown_payment',
            'payment_intent.succeeded',
            'OA-UNKNOWN-PAYMENT',
            'pi_UG_UNKNOWN',
            1500
        ))->assertOk();

        $receipt = UrbanGoodzWebhookEvent::where('event_id', 'evt_unknown_payment')->sole();
        $this->assertSame('failed', $receipt->status);
        $this->assertSame('unmatched_payment', $receipt->failure_type);
        $this->assertNull($receipt->payable_id);
        $this->assertSame(0, UrbanGoodzPaymentLedger::where('reference', 'pi_UG_UNKNOWN')->count());
    }

    public function test_deterministic_existing_capture_unique_index_race_repairs_once(): void
    {
        $request = $this->authorizedRequest('OA-CAPTURE-UNIQUE-RACE', 100.00);
        $pi = 'pi_UG_UNIQUE_RACE';
        $stale = OrderAnywhereRequest::findOrFail($request->id);
        UrbanGoodzPaymentLedger::create([
            'ledger_number' => UrbanGoodzPaymentLedger::nextLedgerNumber(),
            'feature' => 'order_anywhere',
            'payable_type' => OrderAnywhereRequest::class,
            'payable_id' => $request->id,
            'event_type' => 'capture',
            'direction' => 'credit',
            'amount' => 100.00,
            'currency' => 'USD',
            'payment_status' => 'captured',
            'reference' => $pi,
            'idempotency_key' => 'winning-capture:' . $request->id,
        ]);
        $request->update([
            'payment_status' => 'captured',
            'captured_amount' => 100.00,
            'capture_reference' => $pi,
            'psp_reference' => $pi,
        ]);

        $result = $this->payments->finalizeCustomerPayment(
            $stale,
            $this->captureData('evt_unique_race', $pi, 100.00)
        );

        $this->assertTrue($result->alreadyProcessed);
        $this->assertSame(1, $this->ledgerCount($request, 'capture'));
        $this->assertSame(1, $this->ledgerCount($request, 'split_calculated'));
        $this->assertSame(1, $this->finalizationCount($request));
        $this->assertSame(100.0, (float) UrbanGoodzPaymentSplit::where('payable_id', $request->id)->sum('amount'));
    }

    public function test_event_receipt_composite_identity_and_finalization_identity_are_distinct(): void
    {
        $request = $this->authorizedRequest('OA-IDENTITIES', 50.00);
        $key = UrbanGoodzPaymentService::finalizationIdentity(
            'stripe',
            $request,
            'pi_UG_IDENTITIES',
            'capture'
        );

        $this->assertStringContainsString('payment_finalization:stripe:capture', $key);
        $this->assertStringContainsString($request->request_number, $key);
        $this->assertStringContainsString('pi_UG_IDENTITIES', $key);
        $this->assertNotSame('webhook_event:stripe:evt_UG_IDENTITIES', $key);
    }
}
