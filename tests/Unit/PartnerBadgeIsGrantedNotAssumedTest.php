<?php

namespace Tests\Unit;

use App\Models\Store;
use PHPUnit\Framework\TestCase;

/**
 * "The badges will come after WE actually give them, so we should be able to
 * toggle a badge."
 *
 * Two things stood in the way of that. stores.partner_badge_enabled defaulted
 * to 1, so every store created through any path claimed the badge before
 * anyone granted it; and partner_badge_enabled fed isUrbanGoodzPartner(),
 * whose negation is shouldRouteToOrderAnywhere() - so withholding a real
 * partner's badge would have diverted their orders away from their own
 * dashboard. These pin both.
 */
class PartnerBadgeIsGrantedNotAssumedTest extends TestCase
{
    /** A fully contracted partner, badge flag supplied by the caller. */
    private function contractedPartner(?int $badge): Store
    {
        return (new Store)->forceFill([
            'business_status' => 'active_partner',
            'contract_status' => 'contracted',
            'fulfillment_mode' => 'direct_vendor_order',
            'vendor_admin_status' => 'active',
            'banking_status' => 'active',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
            'can_direct_checkout' => 1,
            'partner_badge_enabled' => $badge,
        ]);
    }

    public function test_withholding_a_badge_does_not_reroute_a_contracted_partners_orders(): void
    {
        $store = $this->contractedPartner(0);

        $this->assertTrue($store->isUrbanGoodzPartner());
        $this->assertFalse(
            $store->shouldRouteToOrderAnywhere(),
            'A partner without a badge still fulfils their own orders.'
        );
    }

    public function test_a_store_without_a_granted_badge_does_not_show_one(): void
    {
        $store = $this->contractedPartner(0);

        $this->assertFalse($store->canShowUrbanGoodzPartnerBadge());
        $this->assertNotSame('Urban Goodz Partner', $store->customerBadgeLabel());
    }

    /**
     * A partner who fulfils their own orders must not be advertised to
     * customers as Order Anywhere just because their badge is not granted yet.
     */
    public function test_a_partner_without_a_badge_is_not_advertised_as_order_anywhere(): void
    {
        $store = $this->contractedPartner(0);

        $this->assertFalse($store->shouldRouteToOrderAnywhere());
        $this->assertNotSame('Order Anywhere Available', $store->customerBadgeLabel());
        $this->assertSame('none', $store->getBadgeStatusAttribute());
    }

    public function test_a_granted_badge_shows_once_the_store_is_also_contracted(): void
    {
        $store = $this->contractedPartner(1);

        $this->assertTrue($store->canShowUrbanGoodzPartnerBadge());
        $this->assertSame('Urban Goodz Partner', $store->customerBadgeLabel());
    }

    /**
     * An unknown flag must read as "not granted". The old `?? true` meant a
     * store we knew nothing about claimed to be a partner.
     */
    public function test_an_unknown_badge_flag_is_treated_as_not_granted(): void
    {
        $store = $this->contractedPartner(null);

        $this->assertFalse($store->canShowUrbanGoodzPartnerBadge());
        $this->assertNotSame('Urban Goodz Partner', $store->customerBadgeLabel());
    }

    /**
     * A badge granted to a store that never signed a contract stays invisible.
     * The toggle records the grant; the contract checks still gate display.
     */
    public function test_granting_a_badge_to_an_unsigned_business_shows_nothing(): void
    {
        $store = (new Store)->forceFill([
            'business_status' => 'public_sourced',
            'contract_status' => 'not_contracted',
            'fulfillment_mode' => 'order_anywhere_backend',
            'vendor_admin_status' => 'unclaimed',
            'banking_status' => 'pending',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
            'can_direct_checkout' => 0,
            'partner_badge_enabled' => 1,
        ]);

        $this->assertFalse($store->canShowUrbanGoodzPartnerBadge());
        $this->assertNotSame('Urban Goodz Partner', $store->customerBadgeLabel());
        $this->assertTrue($store->shouldRouteToOrderAnywhere());
    }
}
