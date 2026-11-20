<?php

namespace Tests\Unit;

use App\Models\OrderAnywhereRequest;
use Tests\TestCase;

class LivePaymentGuardTest extends TestCase
{
    public function test_live_controlled_is_capped_and_allowlisted_by_default(): void
    {
        config(['urban_goodz_payments.mode' => 'live_controlled',
            'urban_goodz_payments.live_controlled.unrestricted' => false,
            'urban_goodz_payments.live_controlled.max_amount' => 50.0,
            'urban_goodz_payments.live_controlled.allowed_customers' => ['7']]);

        $this->assertFalse(OrderAnywhereRequest::liveUnrestricted());
        $this->assertSame(50.0, OrderAnywhereRequest::liveMaxAmount());
        $this->assertTrue(OrderAnywhereRequest::isLiveCustomerAllowed(7));
        $this->assertFalse(OrderAnywhereRequest::isLiveCustomerAllowed(8));
    }

    public function test_unrestricted_opens_live_to_everyone_with_a_sanity_cap(): void
    {
        config(['urban_goodz_payments.mode' => 'live_controlled',
            'urban_goodz_payments.live_controlled.unrestricted' => true,
            'urban_goodz_payments.live_controlled.unrestricted_max_amount' => 500.0,
            'urban_goodz_payments.live_controlled.allowed_customers' => []]);

        $this->assertTrue(OrderAnywhereRequest::liveUnrestricted());
        $this->assertSame(500.0, OrderAnywhereRequest::liveMaxAmount());
        $this->assertTrue(OrderAnywhereRequest::isLiveCustomerAllowed(8));
    }

    public function test_the_switch_does_nothing_outside_live_mode(): void
    {
        config(['urban_goodz_payments.mode' => 'sandbox', 'urban_goodz_payments.live_controlled.unrestricted' => true]);

        $this->assertFalse(OrderAnywhereRequest::liveUnrestricted());
    }
}
