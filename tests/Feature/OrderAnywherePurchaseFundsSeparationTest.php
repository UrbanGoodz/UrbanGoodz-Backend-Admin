<?php

namespace Tests\Feature;

use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Order Anywhere is not a marketplace sale. The customer pays Urban Goodz so a
 * driver can go and BUY their goods from a retailer we do not own. That means
 * one order carries four different kinds of money:
 *
 *   1. what the customer paid
 *   2. purchase funds - customer money passing through to a retailer
 *   3. driver delivery earnings
 *   4. Urban Goodz revenue
 *
 * These tests pin the boundary between (2) and (3)/(4), using the worked
 * example from the business model:
 *
 *   items        $100.00
 *   tax            $8.00   -> purchase funds  $108.00
 *   delivery fee  $15.00   -> driver earnings
 *   service fee   $10.00   -> Urban Goodz revenue
 *   customer total         $133.00
 *
 * The driver must not be credited $123.00, and Urban Goodz must not book
 * $118.00 of revenue.
 */
class OrderAnywherePurchaseFundsSeparationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeRequest(array $overrides = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => 'OA-TEST-' . uniqid(),
            'customer_name' => 'QA Customer',
            'customer_phone' => '5550000099',
            'request_details' => 'Go to Walmart and purchase these items.',
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'tip' => 0.00,
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ], $overrides));
    }

    /**
     * The defect this pins: quoteOrderAnywhere() sets item_subtotal and tax but
     * never passes merchant_purchase_amount, so calculateSplits() used to fall
     * through to 0 and book the customer's entire grocery bill as platform
     * revenue.
     */
    public function test_purchase_funds_are_not_counted_as_urban_goodz_revenue(): void
    {
        $request = $this->makeRequest();

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
        ]);

        $fresh = $request->fresh();

        $this->assertEquals(
            108.00,
            (float) $fresh->merchant_purchase_amount,
            'Purchase funds must be items + tax, the money the driver spends at the retailer.'
        );

        $this->assertEquals(
            10.00,
            round((float) $fresh->urban_goodz_revenue, 2),
            'Urban Goodz revenue must exclude the purchase funds and the driver payout.'
        );

        $this->assertLessThan(
            (float) $fresh->final_amount,
            (float) $fresh->urban_goodz_revenue,
            'Revenue can never be the whole customer payment.'
        );
    }

    public function test_purchase_funds_are_not_counted_as_driver_earnings(): void
    {
        $request = $this->makeRequest();

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
        ]);

        $fresh = $request->fresh();

        $this->assertEquals(
            15.00,
            round((float) $fresh->driver_payout_amount, 2),
            'The driver earns the delivery fee, not the money spent on the goods.'
        );

        $this->assertNotEquals(
            123.00,
            round((float) $fresh->driver_payout_amount, 2),
            'Driver earnings must never absorb the purchase funds.'
        );
    }

    /**
     * Every dollar the customer paid must be attributable. Nothing may vanish
     * into an unexplained remainder.
     */
    public function test_customer_payment_fully_reconciles_across_the_four_buckets(): void
    {
        $request = $this->makeRequest();

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
        ]);

        $fresh = $request->fresh();

        $accounted = (float) $fresh->merchant_purchase_amount
            + (float) $fresh->driver_payout_amount
            + (float) $fresh->dispatcher_commission
            + (float) $fresh->processing_reserve
            + (float) $fresh->urban_goodz_revenue;

        $this->assertEqualsWithDelta(
            (float) $fresh->final_amount,
            $accounted,
            0.01,
            'Purchase funds + driver + dispatcher + reserve + revenue must equal what the customer paid.'
        );
    }

    /**
     * The snapshot is the audit trail: it has to say which number was used and
     * where it came from, so a disputed order can be explained later.
     */
    public function test_financial_snapshot_records_the_purchase_funds_and_their_source(): void
    {
        $request = $this->makeRequest();

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
        ]);

        $snapshot = $request->fresh()->financial_rules_snapshot;
        $snapshot = is_array($snapshot) ? $snapshot : json_decode((string) $snapshot, true);

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('merchant_purchase_amount', $snapshot);
        $this->assertArrayHasKey('merchant_purchase_source', $snapshot);
        $this->assertEquals(108.00, (float) $snapshot['merchant_purchase_amount']);
        $this->assertEquals('derived_item_subtotal_plus_tax', $snapshot['merchant_purchase_source']);
    }

    /**
     * An explicitly supplied amount - a reconciled receipt total, say - must
     * win over the derivation, otherwise reconciliation could never correct an
     * estimate.
     */
    public function test_explicitly_supplied_purchase_amount_overrides_the_derivation(): void
    {
        $request = $this->makeRequest();

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
            'merchant_purchase_amount' => 103.47,
        ]);

        $fresh = $request->fresh();

        $this->assertEquals(103.47, (float) $fresh->merchant_purchase_amount);
        $this->assertEquals(
            14.53,
            round((float) $fresh->urban_goodz_revenue, 2),
            'A cheaper actual purchase leaves the difference on the platform side until it is refunded or credited.'
        );
    }
}
