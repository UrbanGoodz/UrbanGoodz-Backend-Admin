<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OrderAnywhereRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The admin screens that drive pricing and automatic dispatch are actually wired to the backend. */
class OrderAnywhereAdminWiringTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->admin = Admin::create([
            'f_name' => 'Wire', 'l_name' => 'Admin', 'email' => 'wire-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'wire-token',
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function as()
    {
        return $this->actingAs($this->admin, 'admin')->withSession(['login_remember_token' => 'wire-token']);
    }

    private function req(array $over = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'status' => 'sourcing', 'payment_status' => 'awaiting_quote',
            'fulfillment_type' => 'external_merchant',
        ], $over));
    }

    public function test_the_list_page_shows_and_saves_the_dispatch_settings(): void
    {
        $this->as()->get('/admin/urban-goodz/order-anywhere')->assertOk()->assertSee('Automatic dispatch');

        $this->as()->put('/admin/urban-goodz/order-anywhere/dispatch-settings', [
            'auto_dispatch' => '1', 'radius_miles' => 12, 'offer_minutes' => 4, 'escalate_after_minutes' => 8, 'cancel_after_minutes' => 20,
        ])->assertRedirect();

        $this->assertSame(12.0, \App\Services\OrderAnywhereDispatchSettings::get('radius_miles'));
        $this->assertSame(4, \App\Services\OrderAnywhereDispatchSettings::get('offer_minutes'));

        // An unchecked switch means "off".
        $this->as()->put('/admin/urban-goodz/order-anywhere/dispatch-settings', [
            'radius_miles' => 12, 'offer_minutes' => 4, 'escalate_after_minutes' => 8, 'cancel_after_minutes' => 20,
        ])->assertRedirect();
        $this->assertFalse(\App\Services\OrderAnywhereDispatchSettings::get('auto_dispatch'));
    }

    public function test_quoting_from_the_sourced_item_cost_applies_the_markup_and_records_the_pickup(): void
    {
        $r = $this->req();

        $this->as()->put("/admin/urban-goodz/order-anywhere/{$r->id}/quote", [
            'item_cost' => 100, 'markup_percent' => 20, 'delivery_fee' => 8, 'tax' => 7.5,
            'pickup_address' => '1 Merchant Way', 'pickup_latitude' => 40.7580, 'pickup_longitude' => -73.9855,
        ])->assertRedirect();

        $fresh = $r->fresh();
        $this->assertSame(100.0, (float) $fresh->item_subtotal);
        $this->assertSame(20.0, (float) $fresh->service_fee, 'the markup is the platform profit');
        $this->assertSame(135.5, (float) $fresh->quote_amount);       // 100 + 20 + 8 + 7.5
        $this->assertSame('quoted', $fresh->payment_status);
        $this->assertEqualsWithDelta(40.7580, (float) $fresh->pickup_latitude, 0.0001);
        $this->assertSame('1 Merchant Way', $fresh->pickup_address);
    }

    public function test_a_typed_total_still_works_and_nothing_at_all_is_refused(): void
    {
        $r = $this->req();
        $this->as()->put("/admin/urban-goodz/order-anywhere/{$r->id}/quote", ['quote_amount' => 61.25])->assertRedirect();
        $this->assertSame(61.25, (float) $r->fresh()->quote_amount);

        $r2 = $this->req();
        $this->as()->from('/x')->put("/admin/urban-goodz/order-anywhere/{$r2->id}/quote", [])->assertSessionHasErrors();
    }

    public function test_the_detail_page_renders_the_new_fields_and_flags_a_job_nobody_has_taken(): void
    {
        $r = $this->req(['metadata' => ['needs_manual_dispatch_at' => now()->toIso8601String()]]);

        $this->as()->get("/admin/urban-goodz/order-anywhere/{$r->id}")
            ->assertOk()
            ->assertSee('Sourced item cost')
            ->assertSee('Pickup latitude')
            ->assertSee('No driver has accepted this yet');
    }

    public function test_the_reimbursement_page_lists_and_approves_a_claim_once(): void
    {
        $req = $this->req(['status' => 'shopping']);
        $d = \App\Models\DeliveryMan::create([
            'f_name' => 'Rb', 'l_name' => 'Drv', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'rbd-'.uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => 'tk-'.uniqid(), 'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
        $claim = \App\Models\UrbanGoodzOrderAnywhereReimbursement::create([
            'order_anywhere_request_id' => $req->id, 'delivery_man_id' => $d->id,
            'claimed_total' => 40, 'authorized_total' => 40, 'cap_total' => 44, 'status' => 'pending',
        ]);

        $this->as()->get('/admin/urban-goodz/order-anywhere')->assertOk()->assertSee('Purchase reimbursements');
        $this->as()->get('/admin/urban-goodz/order-anywhere-reimbursements')->assertOk()->assertSee('$40.00', false);

        $this->as()->post("/admin/urban-goodz/order-anywhere-reimbursements/{$claim->id}/approve", [])->assertRedirect();
        $this->as()->post("/admin/urban-goodz/order-anywhere-reimbursements/{$claim->id}/approve", [])->assertRedirect();
        $this->assertSame(1, \App\Models\UrbanGoodzDriverEarning::where('earning_type', 'order_anywhere_reimbursement')->count());
        // State changes are POST only.
        $this->assertContains($this->as()->get("/admin/urban-goodz/order-anywhere-reimbursements/{$claim->id}/approve")->status(), [405, 302]);
    }
}
