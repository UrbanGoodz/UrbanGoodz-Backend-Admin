<?php

namespace Tests\Unit;

use App\Console\Commands\UrbanGoodzCorrectSourcedStoreClaims as Correct;
use App\Models\Store;
use PHPUnit\Framework\TestCase;

/**
 * Sourcing batches imported before the policy was encoded took the stores
 * table's column defaults and ended up claiming to be contracted partners.
 * These assertions pin what the correction must produce, using the same model
 * predicates the storefront and PlaceNewOrder branch on.
 */
class CorrectedSourcedStoreClaimTest extends TestCase
{
    private function correctedStore(): Store
    {
        return (new Store)->forceFill(Correct::CORRECTED + [
            'vendor_admin_status' => 'unclaimed',
            'banking_status' => 'pending',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
        ]);
    }

    public function test_a_corrected_store_no_longer_claims_to_be_a_partner(): void
    {
        $this->assertFalse($this->correctedStore()->isUrbanGoodzPartner());
    }

    public function test_a_corrected_store_routes_checkout_to_order_anywhere(): void
    {
        $this->assertTrue($this->correctedStore()->shouldRouteToOrderAnywhere());
    }

    public function test_the_customer_is_not_shown_a_partner_badge(): void
    {
        $this->assertNotSame('Urban Goodz Partner', $this->correctedStore()->customerBadgeLabel());
        $this->assertSame('Order Anywhere Available', $this->correctedStore()->customerBadgeLabel());
    }

    /**
     * The badge is a grant, not a default. Leaving partner_badge_enabled at 1
     * means the badge reappears the moment any other field drifts back, with
     * nobody having granted it.
     */
    public function test_the_badge_toggle_is_off_so_it_can_only_come_back_by_being_granted(): void
    {
        $this->assertSame(0, Correct::CORRECTED['partner_badge_enabled']);
        $this->assertFalse($this->correctedStore()->canShowUrbanGoodzPartnerBadge());
    }

    /** What the rows looked like before, so the regression is pinned too. */
    public function test_the_uncorrected_shape_was_the_thing_being_fixed(): void
    {
        $before = (new Store)->forceFill([
            'business_status' => 'active_partner',
            'contract_status' => 'contracted',
            'fulfillment_mode' => 'direct_vendor_order',
            'badge_status' => 'urban_goodz_partner',
            'can_direct_checkout' => 1,
            'partner_badge_enabled' => 1,
            'vendor_admin_status' => 'active',
            'banking_status' => 'active',
            'subscription_status' => 'active',
            'admin_approval_status' => 'approved',
        ]);

        $this->assertTrue($before->isUrbanGoodzPartner(), 'These rows really did claim partner status.');
        $this->assertFalse($before->shouldRouteToOrderAnywhere(), 'And really did skip Order Anywhere.');
    }

    public function test_the_correction_matches_what_phase3_provisioning_writes_for_a_new_store(): void
    {
        foreach (['business_status' => 'public_sourced',
                  'contract_status' => 'not_contracted',
                  'fulfillment_mode' => 'order_anywhere_backend',
                  'badge_status' => 'public_listing',
                  'can_direct_checkout' => 0] as $field => $expected) {
            $this->assertSame($expected, Correct::CORRECTED[$field], "{$field} drifted from the provisioning convention");
        }
    }
}
