<?php

namespace Tests\Feature;

use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzOrderAnywhereItemSubstitution;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Substitutions on an Order Anywhere purchase.
 *
 * The rule being pinned: a substitution moves MERCHANDISE money. It changes
 * what the customer owes for goods and therefore what the driver is authorised
 * to spend. It never changes driver earnings and never changes Urban Goodz
 * revenue - a driver must not profit by swapping to a pricier brand, nor absorb
 * the saving on a cheaper one.
 *
 * Baseline throughout: $100 items + $8 tax = $108.00 purchase authorisation.
 */
class OrderAnywhereSubstitutionTest extends TestCase
{
    use DatabaseTransactions;

    private function quotedRequest(): OrderAnywhereRequest
    {
        $request = OrderAnywhereRequest::create([
            'request_number' => 'OA-SUB-' . uniqid(),
            'customer_name' => 'QA Customer',
            'customer_phone' => '5550000097',
            'request_details' => 'Go to Walmart and purchase these items.',
            'item_subtotal' => 100.00,
            'tax' => 8.00,
            'delivery_fee' => 15.00,
            'service_fee' => 10.00,
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

    /** The requirement: keep both sides, do not overwrite the original line. */
    public function test_substitution_preserves_the_original_requested_item(): void
    {
        $request = $this->quotedRequest();

        $sub = app(UrbanGoodzPaymentService::class)->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Brand B',
            'substituted_actual_price' => 12.00,
            'reason' => UrbanGoodzOrderAnywhereItemSubstitution::REASON_UNAVAILABLE,
        ]);

        $this->assertSame('Brand A', $sub->original_item_name, 'The original request must survive the swap.');
        $this->assertSame('Brand B', $sub->substituted_item_name);
        $this->assertEquals(10.00, (float) $sub->original_estimated_price);
        $this->assertEquals(12.00, (float) $sub->substituted_actual_price);
        $this->assertEquals(2.00, (float) $sub->price_delta, 'The $2.00 difference is explicit, not implied.');
    }

    /** A dearer swap inside tolerance raises the purchase authorisation. */
    public function test_approved_dearer_substitution_raises_the_purchase_authorisation(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $this->assertEquals(108.00, $payments->substitutionAdjustedPurchaseAuthorization($request));

        $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Brand B',
            'substituted_actual_price' => 12.00,
        ]);

        $this->assertEquals(
            110.00,
            $payments->substitutionAdjustedPurchaseAuthorization($request->fresh()),
            'An agreed $2.00 swap means the driver may now spend $110.00.'
        );
    }

    /**
     * The important one: an agreed swap must not later read as driver
     * overspend. Without folding the delta into the authorisation, a $110
     * receipt against a $108 baseline would be flagged.
     */
    public function test_receipt_matching_an_approved_substitution_is_not_treated_as_overspend(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Brand B',
            'substituted_actual_price' => 12.00,
        ]);

        $result = $payments->reconcileReceipt($request->fresh(), ['receipt_amount' => 110.00]);

        $this->assertEquals(0.00, round((float) $result->receipt_difference, 2));
        $this->assertSame('auto_approved', $result->reconciliation_status);
    }

    /**
     * A swap beyond tolerance is not spendable until the customer agrees, so it
     * must NOT move the authorisation while it sits proposed.
     */
    public function test_unapproved_expensive_substitution_does_not_move_the_authorisation(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $sub = $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Premium Brand',
            'substituted_actual_price' => 30.00,
        ]);

        $this->assertTrue($sub->requires_customer_approval, 'A $20.00 increase exceeds the $5.00 tolerance.');
        $this->assertSame(UrbanGoodzOrderAnywhereItemSubstitution::STATUS_PROPOSED, $sub->status);
        $this->assertEquals(
            108.00,
            $payments->substitutionAdjustedPurchaseAuthorization($request->fresh()),
            'Money the customer has not agreed to must not become spendable.'
        );
    }

    /** Partial fulfilment: item unavailable and nothing bought in its place. */
    public function test_removed_item_lowers_the_authorisation_by_its_full_estimate(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $sub = $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => null,
            'substituted_actual_price' => null,
            'reason' => UrbanGoodzOrderAnywhereItemSubstitution::REASON_REMOVED,
        ]);

        $this->assertTrue($sub->isRemoval());
        $this->assertEquals(-10.00, (float) $sub->price_delta);
        $this->assertEquals(
            98.00,
            $payments->substitutionAdjustedPurchaseAuthorization($request->fresh()),
            'A removed item reduces what the customer owes for goods.'
        );
    }

    /** Purchase funds move; driver earnings and platform revenue do not. */
    public function test_substitution_does_not_change_driver_earnings_or_platform_revenue(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $driverBefore = (float) $request->driver_payout_amount;
        $revenueBefore = (float) $request->urban_goodz_revenue;

        $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Brand B',
            'substituted_actual_price' => 12.00,
        ]);

        $after = $request->fresh();

        $this->assertEquals($driverBefore, (float) $after->driver_payout_amount, 'Driver earnings are untouched by a swap.');
        $this->assertEquals($revenueBefore, (float) $after->urban_goodz_revenue, 'Platform revenue is untouched by a swap.');
    }

    /** Several swaps must aggregate, not overwrite one another. */
    public function test_multiple_substitutions_aggregate(): void
    {
        $request = $this->quotedRequest();
        $payments = app(UrbanGoodzPaymentService::class);

        $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand A',
            'original_estimated_price' => 10.00,
            'substituted_item_name' => 'Brand B',
            'substituted_actual_price' => 12.00,
        ]);
        $payments->recordSubstitution($request, [
            'original_item_name' => 'Brand C',
            'original_estimated_price' => 20.00,
            'substituted_item_name' => 'Brand D',
            'substituted_actual_price' => 17.50,
        ]);

        $this->assertCount(2, $request->fresh()->itemSubstitutions);
        $this->assertEquals(
            107.50,
            $payments->substitutionAdjustedPurchaseAuthorization($request->fresh()),
            '108.00 + 2.00 - 2.50'
        );
    }
}
