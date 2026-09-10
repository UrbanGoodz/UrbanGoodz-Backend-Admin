<?php

namespace Tests\Feature;

use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Receipt reconciliation for the cardless Order Anywhere path.
 *
 * A receipt covers MERCHANDISE. It must therefore be judged against the
 * purchase authorisation - items + tax - and not against the full customer
 * charge, which also carries delivery, service fee and tip.
 *
 * Worked example used throughout:
 *   items          $100.00
 *   tax              $8.00   -> purchase authorisation $108.00
 *   delivery fee    $15.00
 *   service fee     $10.00
 *   customer total          $133.00
 */
class OrderAnywhereReceiptReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private function quotedRequest(): OrderAnywhereRequest
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-RECON-' . uniqid(),
            'customer_name' => 'QA Customer',
            'customer_phone' => '5550000098',
            'request_details' => 'Go to Walmart and purchase these items.',
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'tip' => 0.00,
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'authorized_amount' => 133.00,
            'overage_threshold' => 5.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ]);

        app(UrbanGoodzPaymentService::class)->quoteOrderAnywhere($request, [
            'quote_amount' => 133.00,
            'final_amount' => 133.00,
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
            'driver_amount' => 15.00,
        ]);

        return $request->fresh();
    }

    /** Scenario 1 - actual equals estimate. */
    public function test_exact_receipt_reconciles_with_no_variance(): void
    {
        $request = $this->quotedRequest();

        $result = app(UrbanGoodzPaymentService::class)
            ->reconcileReceipt($request, ['receipt_amount' => 108.00]);

        $this->assertEquals(0.00, round((float) $result->receipt_difference, 2));
        $this->assertSame('auto_approved', $result->reconciliation_status);
    }

    /**
     * Scenario 2 - actual is LOWER than estimate. The difference is money the
     * customer overpaid and must be traceable, not absorbed.
     */
    public function test_underspend_records_a_negative_variance_and_auto_approves(): void
    {
        $request = $this->quotedRequest();

        $result = app(UrbanGoodzPaymentService::class)
            ->reconcileReceipt($request, ['receipt_amount' => 103.47]);

        $this->assertEquals(
            -4.53,
            round((float) $result->receipt_difference, 2),
            'Variance is measured against the $108.00 purchase authorisation, not the $133.00 customer charge.'
        );
        $this->assertSame('auto_approved', $result->reconciliation_status);
    }

    /**
     * Scenario 3 - actual is HIGHER than estimate. This is the case the old
     * implementation got backwards: measured against the $133 customer total a
     * $130 receipt looked like a $3 underspend and auto-approved, despite being
     * $22 over the authorised purchase budget.
     */
    public function test_overspend_is_never_auto_approved(): void
    {
        $request = $this->quotedRequest();

        $result = app(UrbanGoodzPaymentService::class)
            ->reconcileReceipt($request, ['receipt_amount' => 130.00]);

        $this->assertEquals(
            22.00,
            round((float) $result->receipt_difference, 2),
            'A $130 receipt against a $108 authorisation is a $22 overspend.'
        );
        $this->assertSame(
            'pending_review',
            $result->reconciliation_status,
            'An overspend must go to review even though $130 is within the $133 customer charge.'
        );
    }

    /**
     * A small overspend must still not slip through on tolerance. The
     * threshold absorbs rounding on an underspend; it is not an allowance to
     * exceed the budget.
     */
    public function test_small_overspend_within_tolerance_still_goes_to_review(): void
    {
        $request = $this->quotedRequest();

        $result = app(UrbanGoodzPaymentService::class)
            ->reconcileReceipt($request, ['receipt_amount' => 110.00]);

        $this->assertEquals(2.00, round((float) $result->receipt_difference, 2));
        $this->assertSame(
            'pending_review',
            $result->reconciliation_status,
            '$2.00 is inside the $5.00 threshold but is still an overspend.'
        );
    }

    /**
     * Reconciliation must never silently discard the original authorisation -
     * an order has to remain explainable after the fact.
     */
    public function test_reconciliation_preserves_the_original_purchase_authorisation(): void
    {
        $request = $this->quotedRequest();

        $result = app(UrbanGoodzPaymentService::class)
            ->reconcileReceipt($request, ['receipt_amount' => 103.47]);

        $this->assertEquals(108.00, (float) $result->merchant_purchase_amount, 'Authorisation is retained.');
        $this->assertEquals(103.47, (float) $result->receipt_amount, 'Actual is recorded separately.');
        $this->assertEquals(-4.53, round((float) $result->receipt_difference, 2), 'Variance is explicit.');
        $this->assertEquals(133.00, (float) $result->final_amount, 'What the customer paid is untouched.');
    }
}
