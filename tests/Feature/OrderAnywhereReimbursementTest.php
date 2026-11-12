<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzOrderAnywhereReimbursement;
use App\Services\OrderAnywhereDriverFlow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Reimbursement purchasing: driver pays, uploads receipt, admin approves, earnings carry the payback. */
class OrderAnywhereReimbursementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Storage::fake('local');
        config(['urban_goodz_payments.issuing.purchase_method' => 'reimburse', 'urban_goodz_payments.issuing.driver_card_buffer_percent' => 10]);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function driver(string $tag): DeliveryMan
    {
        return DeliveryMan::create([
            'f_name' => 'Drv', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "reimb-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.$tag.'-'.bin2hex(random_bytes(10)),
            'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
    }

    private function order(DeliveryMan $d, string $status = 'shopping'): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create([
            'request_number' => 'OA-RB-'.uniqid(), 'customer_name' => 'QA', 'customer_phone' => '5550000001',
            'request_details' => 'Buy items', 'item_subtotal' => 100.00, 'tax' => 8.00,
            'delivery_fee' => 15.00, 'service_fee' => 10.00, 'quote_amount' => 133.00, 'final_amount' => 133.00,
            'authorized_amount' => 133.00, 'merchant_purchase_amount' => 108.00,
            'status' => $status, 'payment_status' => 'captured',
            'fulfillment_type' => OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT,
            'assigned_delivery_man_id' => $d->id,
        ]);
    }

    private function submit(DeliveryMan $d, OrderAnywhereRequest $o, float $total)
    {
        return $this->postJson("/api/v1/order-anywhere/driver/{$o->id}/reimbursement", [
            'total' => $total,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Authorization' => 'Bearer '.$d->auth_token]);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'f_name' => 'Ops', 'l_name' => 'Boss', 'email' => 'rb-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'rb-token',
        ]);
    }

    public function test_driver_can_submit_a_receipt_within_the_limit(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);

        $this->submit($d, $o, 108.00)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, UrbanGoodzOrderAnywhereReimbursement::count());
    }

    public function test_claim_above_the_buffered_cap_is_refused(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d); // 108 + 10% = 118.80

        $this->submit($d, $o, 118.81)->assertStatus(422);
        $this->assertSame(0, UrbanGoodzOrderAnywhereReimbursement::count());
        $r = $this->submit($d, $o, 118.80); $this->assertSame(200, $r->status(), $r->getContent());
    }

    public function test_only_the_assigned_driver_can_claim(): void
    {
        $a = $this->driver('a');
        $b = $this->driver('b');
        $o = $this->order($a);

        $this->submit($b, $o, 50)->assertStatus(404);
        $this->assertSame(0, UrbanGoodzOrderAnywhereReimbursement::count());
    }

    public function test_a_second_live_claim_is_refused(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);
        $this->submit($d, $o, 100)->assertOk();
        $this->submit($d, $o, 100)->assertStatus(422);
        $this->assertSame(1, UrbanGoodzOrderAnywhereReimbursement::count());
    }

    public function test_disabled_mode_refuses_claims(): void
    {
        config(['urban_goodz_payments.issuing.purchase_method' => 'card']);
        $d = $this->driver('a');
        $this->submit($d, $this->order($d), 50)->assertStatus(409);
    }

    public function test_purchased_requires_a_receipt_claim_in_reimburse_mode(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);
        $flow = app(OrderAnywhereDriverFlow::class);

        try {
            $flow->advance($o, 'purchased');
            $this->fail('Expected the purchase gate to refuse.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('receipt', $e->getMessage());
        }

        $this->submit($d, $o, 100)->assertOk();
        $this->assertSame('purchased', $flow->advance($o->fresh(), 'purchased')->status);
    }

    public function test_approval_credits_the_driver_once_even_if_double_clicked(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);
        $claimId = $this->submit($d, $o, 100)->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/reimbursements/{$claimId}/approve")->assertOk();
        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/reimbursements/{$claimId}/approve")->assertOk();

        $earnings = UrbanGoodzDriverEarning::where('delivery_man_id', $d->id)->where('earning_type', 'order_anywhere_reimbursement')->get();
        $this->assertCount(1, $earnings);
        $this->assertEquals(100.00, (float) $earnings->first()->amount);
        $this->assertSame('pending', $earnings->first()->status);
    }

    public function test_admin_can_approve_a_lower_amount_but_not_a_higher_one(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);
        $claimId = $this->submit($d, $o, 100)->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/reimbursements/{$claimId}/approve", ['approved_total' => 101])->assertStatus(422);
        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/reimbursements/{$claimId}/approve", ['approved_total' => 90])->assertOk();
        $this->assertEquals(90.00, (float) UrbanGoodzDriverEarning::where('delivery_man_id', $d->id)->value('amount'));
    }

    public function test_rejected_claim_pays_nothing_and_can_be_resubmitted(): void
    {
        $d = $this->driver('a');
        $o = $this->order($d);
        $claimId = $this->submit($d, $o, 100)->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson("/api/v1/order-anywhere/admin/reimbursements/{$claimId}/reject", ['notes' => 'Blurry'])->assertOk();
        $this->assertSame(0, UrbanGoodzDriverEarning::where('delivery_man_id', $d->id)->count());
        $this->submit($d, $o, 95)->assertOk();
    }

    public function test_reimbursement_admin_endpoints_reject_non_admins(): void
    {
        $d = $this->driver('a');
        $this->getJson('/api/v1/order-anywhere/admin/reimbursements', ['Authorization' => 'Bearer '.$d->auth_token])->assertUnauthorized();
    }
}
