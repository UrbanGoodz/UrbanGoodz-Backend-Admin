<?php

namespace Tests\Unit;

use App\Models\Store;
use PHPUnit\Framework\TestCase;

/**
 * Businesses provisioned by urban-goodz:phase3-provision never signed up, so a
 * customer checkout must route through Order Anywhere - Urban Goodz fulfils on
 * the customer's behalf - rather than being handed to a vendor dashboard nobody
 * is watching. PlaceNewOrder branches on Store::shouldRouteToOrderAnywhere().
 *
 * The provisioned row used to say can_direct_checkout = 1 and fulfillment_mode
 * = 'direct_vendor_order', and only routed correctly because the store also
 * failed isUrbanGoodzPartner(). These assertions pin the intent directly.
 */
class SourcedStoreOrderAnywhereRoutingTest extends TestCase
{
    /** The store-row shape urban-goodz:phase3-provision writes. */
    private function provisionedSourcedStore(): Store
    {
        return (new Store)->forceFill([
            'is_public_sourced' => 1,
            'is_claimed' => 0,
            'is_partner' => 0,
            'can_direct_checkout' => 0,
            'requires_admin_quote' => 0,
            'business_status' => 'public_sourced',
            'contract_status' => 'not_contracted',
            'vendor_admin_status' => 'unclaimed',
            'banking_status' => 'pending',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
            'fulfillment_mode' => 'order_anywhere_backend',
        ]);
    }

    public function test_a_provisioned_sourced_store_is_never_a_partner(): void
    {
        $this->assertFalse($this->provisionedSourcedStore()->isUrbanGoodzPartner());
    }

    public function test_a_provisioned_sourced_store_routes_checkout_to_order_anywhere(): void
    {
        $this->assertTrue($this->provisionedSourcedStore()->shouldRouteToOrderAnywhere());
    }

    /**
     * Each clause stands on its own, so one field drifting back cannot silently
     * restore direct-to-vendor checkout for a business that never signed up.
     */
    public function test_each_order_anywhere_clause_holds_independently(): void
    {
        foreach ([
            'business_status' => 'active_partner',
            'contract_status' => 'contracted',
            'can_direct_checkout' => 1,
            'fulfillment_mode' => 'direct_vendor_order',
        ] as $field => $partnerishValue) {
            $store = $this->provisionedSourcedStore()->forceFill([$field => $partnerishValue]);

            $this->assertTrue(
                $store->shouldRouteToOrderAnywhere(),
                "Reverting {$field} alone stopped this store routing to Order Anywhere."
            );
        }
    }

    public function test_the_customer_sees_it_labelled_as_order_anywhere(): void
    {
        $this->assertSame(
            'Order Anywhere Available',
            $this->provisionedSourcedStore()->customerBadgeLabel()
        );
    }

    /** The converse: a genuine contracted partner still checks out directly. */
    public function test_a_contracted_partner_still_checks_out_directly(): void
    {
        $partner = (new Store)->forceFill([
            'business_status' => 'active_partner',
            'contract_status' => 'contracted',
            'vendor_admin_status' => 'active',
            'banking_status' => 'active',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
            'can_direct_checkout' => 1,
            'partner_badge_enabled' => 1,
            'fulfillment_mode' => 'direct_vendor_order',
        ]);

        $this->assertTrue($partner->isUrbanGoodzPartner());
        $this->assertFalse($partner->shouldRouteToOrderAnywhere());
    }
}
