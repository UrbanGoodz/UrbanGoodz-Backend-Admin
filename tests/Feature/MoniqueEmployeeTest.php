<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Models\AiMoniqueSubscription;
use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodz\Agent\MoniqueEntitlementService;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Monique as an employee: always on for the platform team, watches Urban Goodz operations, tells people. */
class MoniqueEmployeeTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): Admin
    {
        return Admin::create([
            'f_name' => 'Boss', 'l_name' => 'Owner', 'email' => 'boss-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1,
        ]);
    }

    public function test_platform_admins_never_lapse_after_a_trial(): void
    {
        $admin = $this->admin();
        // An expired trial row must not matter for the platform team.
        AiMoniqueSubscription::create([
            'account_type' => 'admin', 'admin_id' => $admin->id, 'status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED,
            'trial_start_at' => now()->subDays(60), 'trial_ends_at' => now()->subDays(30), 'price_per_month' => 49,
            'post_trial_policy' => 'explicit_opt_in',
        ]);

        $result = app(MoniqueEntitlementService::class)->checkEntitlement('admin', $admin->id);
        $this->assertTrue($result['allowed']);
        $this->assertSame('platform_owned', $result['status']);
    }

    public function test_vendors_still_follow_the_trial_rules(): void
    {
        config(['urban_goodz_ai.monique_pricing.platform_admins_exempt' => true]);
        $vendor = \App\Models\Vendor::create([
            'f_name' => 'V', 'l_name' => 'Vendor', 'email' => 'v-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'),
        ]);
        AiMoniqueSubscription::create([
            'account_type' => 'vendor', 'vendor_id' => $vendor->id, 'status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED,
            'trial_start_at' => now()->subDays(60), 'trial_ends_at' => now()->subDays(30), 'price_per_month' => 49, 'post_trial_policy' => 'explicit_opt_in',
        ]);

        $this->assertFalse(app(MoniqueEntitlementService::class)->checkEntitlement('vendor', $vendor->id)['allowed']);
    }

    public function test_she_flags_a_paid_order_anywhere_request_nobody_took(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $stale = OrderAnywhereRequest::create([
            'request_number' => 'OA-MQ-'.uniqid(), 'customer_name' => 'QA', 'customer_phone' => '5550000009', 'request_details' => 'x',
            'status' => 'approved', 'payment_status' => 'captured', 'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ]);
        DB::table('order_anywhere_requests')->where('id', $stale->id)->update(['updated_at' => now()->subHour()]);

        $result = app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'order_anywhere_unassigned')->first();
        $this->assertNotNull($note, 'Monique did not notice the unassigned Order Anywhere request');
        $this->assertSame(AiMoniqueNotification::PRIORITY_URGENT, $note->priority);
        $this->assertGreaterThanOrEqual(1, $result['notifications_created']);
        // She can act on this one (widen the search), so it carries the button; payouts do not.
        $this->assertContains('let_monique_handle_it', array_column((array) $note->actions, 'action'));
    }

    public function test_urgent_findings_are_emailed_and_the_channel_is_recorded_only_when_sent(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $stale = OrderAnywhereRequest::create([
            'request_number' => 'OA-MQ-'.uniqid(), 'customer_name' => 'QA', 'customer_phone' => '5550000010', 'request_details' => 'x',
            'status' => 'approved', 'payment_status' => 'captured', 'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ]);
        DB::table('order_anywhere_requests')->where('id', $stale->id)->update(['updated_at' => now()->subHour()]);

        app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'order_anywhere_unassigned')->firstOrFail();
        $this->assertContains('email', (array) $note->delivered_channels);
    }

    public function test_a_broken_mailer_never_stops_the_loop_or_claims_an_email(): void
    {
        $admin = $this->admin();
        $stale = OrderAnywhereRequest::create([
            'request_number' => 'OA-MQ-'.uniqid(), 'customer_name' => 'QA', 'customer_phone' => '5550000011', 'request_details' => 'x',
            'status' => 'approved', 'payment_status' => 'captured', 'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ]);
        DB::table('order_anywhere_requests')->where('id', $stale->id)->update(['updated_at' => now()->subHour()]);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $result = app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'order_anywhere_unassigned')->firstOrFail();
        $this->assertNotContains('email', (array) $note->delivered_channels);
        $this->assertGreaterThanOrEqual(1, $result['notifications_created']);
    }

    public function test_email_alerts_can_be_switched_off(): void
    {
        Mail::fake();
        config(['urban_goodz_ai.monique_proactive.email_alerts' => false]);
        $admin = $this->admin();
        $stale = OrderAnywhereRequest::create([
            'request_number' => 'OA-MQ-'.uniqid(), 'customer_name' => 'QA', 'customer_phone' => '5550000012', 'request_details' => 'x',
            'status' => 'approved', 'payment_status' => 'captured', 'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
        ]);
        DB::table('order_anywhere_requests')->where('id', $stale->id)->update(['updated_at' => now()->subHour()]);

        app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        Mail::assertNothingSent();
    }

}
