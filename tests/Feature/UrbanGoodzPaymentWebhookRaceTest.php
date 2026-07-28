<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\Store;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\Vendor;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stripe fans one payment out into several events (payment_intent.succeeded and
 * charge.succeeded), each carrying its own event id. The webhook-level guard is keyed on
 * that event id, so it cannot collapse them, and both deliveries reach
 * captureCustomerPayment() for the same request.
 *
 * In production this surfaced as an unhandled error:
 *   SQLSTATE[23000]: Duplicate entry 'order_anywhere:stripe:1:split_calculated:5.00'
 *   for key 'urban_goodz_payment_ledgers_idempotency_key_unique'
 *
 * These tests cover both halves of that race: the duplicate insert itself, and the
 * double capture that the duplicate insert was masking.
 */
class UrbanGoodzPaymentWebhookRaceTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzPaymentService $paymentService;
    private Vendor $vendor;
    private DeliveryMan $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);

        app()->singleton(UrbanGoodzPaymentService::class, function () {
            return new UrbanGoodzPaymentService(new \App\Services\Payments\PaymentProviderManager(app()));
        });
        $this->paymentService = app(UrbanGoodzPaymentService::class);

        $module = \App\Models\Module::firstOrCreate(
            ['module_name' => 'Food'],
            ['module_type' => 'food', 'status' => 1]
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

        Store::firstOrCreate(
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
    }

    private function authorizedRequest(string $number, float $amount): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create([
            'request_number' => $number,
            'customer_id' => 1,
            'vendor_id' => $this->vendor->id,
            'assigned_delivery_man_id' => $this->driver->id,
            'status' => 'shopping',
            'quote_amount' => $amount,
            'authorized_amount' => $amount,
            'payment_status' => 'authorized',
        ]);
    }

    /**
     * The exact production failure: a concurrent writer commits the split_calculated
     * ledger row between our SELECT and our INSERT, so the INSERT hits the unique index.
     *
     * The race is reproduced deterministically by injecting the competing row from a
     * query listener that fires on the firstOrCreate lookup — the same interleaving,
     * without needing two processes.
     */
    public function test_concurrent_split_calculated_write_does_not_abort_capture(): void
    {
        $request = $this->authorizedRequest('OA-RACE-SPLIT-1', 50.00);

        $injected = false;

        DB::listen(function ($query) use (&$injected) {
            if ($injected || ! str_contains($query->sql, 'urban_goodz_payment_ledgers')) {
                return;
            }

            if (stripos(ltrim($query->sql), 'select') !== 0) {
                return;
            }

            foreach ($query->bindings as $binding) {
                if (is_string($binding) && str_contains($binding, ':split_calculated:')) {
                    $injected = true;

                    // The concurrent delivery wins the unique index.
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
                        'metadata' => ['injected_by' => 'race_test'],
                    ]);

                    return;
                }
            }
        });

        $captured = $this->paymentService->captureCustomerPayment($request, [
            'captured_amount' => 50.00,
            'capture_idempotency_key' => 'webhook:stripe:evt_race_split',
            'capture_reference' => 'pi_race_split',
            'psp_reference' => 'pi_race_split',
            'source' => 'webhook',
        ]);

        $this->assertTrue($injected, 'The competing split_calculated row was never injected; the race was not exercised.');

        // Before the fix this line was never reached — the duplicate key surfaced as an
        // unhandled QueryException with a full stack trace.
        $this->assertEquals('captured', $captured->payment_status);

        $splitLedgers = UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('event_type', 'split_calculated')
            ->get();

        $this->assertCount(1, $splitLedgers, 'The race must converge on a single split_calculated ledger row.');
    }

    /**
     * Second half of the race: once the duplicate insert no longer aborts the loser, the
     * loser must not go on to write a second capture ledger for money already captured.
     *
     * The stale model reproduces what each concurrent webhook holds — both read
     * payment_status = 'authorized' before either commits.
     */
    public function test_second_webhook_delivery_does_not_double_capture(): void
    {
        $request = $this->authorizedRequest('OA-RACE-CAPTURE-1', 40.00);

        // Both deliveries loaded the request while it still read 'authorized'.
        $staleSecondDelivery = OrderAnywhereRequest::find($request->id);

        $this->paymentService->captureCustomerPayment($request, [
            'captured_amount' => 40.00,
            'capture_idempotency_key' => 'webhook:stripe:evt_payment_intent_succeeded',
            'capture_reference' => 'pi_race_capture',
            'psp_reference' => 'pi_race_capture',
            'source' => 'webhook',
        ]);

        $this->assertEquals('authorized', $staleSecondDelivery->payment_status, 'Guard: the second delivery must still hold the stale status.');

        // charge.succeeded — a different event id, so the webhook-level guard lets it through.
        $result = $this->paymentService->captureCustomerPayment($staleSecondDelivery, [
            'captured_amount' => 40.00,
            'capture_idempotency_key' => 'webhook:stripe:evt_charge_succeeded',
            'capture_reference' => 'ch_race_capture',
            'psp_reference' => 'ch_race_capture',
            'source' => 'webhook',
        ]);

        $this->assertEquals('captured', $result->payment_status);
        $this->assertEquals(40.00, (float) $result->captured_amount, 'The captured amount must not be restated by the duplicate delivery.');

        $captureLedgers = UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
            ->where('payable_id', $request->id)
            ->where('event_type', 'capture')
            ->get();

        $this->assertCount(1, $captureLedgers, 'A duplicate provider event must not double-write the capture ledger.');
        $this->assertEquals(40.00, (float) $captureLedgers->first()->amount);
    }
}
