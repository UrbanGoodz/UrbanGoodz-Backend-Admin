<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Order Anywhere end to end, per the lifecycle in OrderAnywhereRequest:
 *
 *   customer describes an item -> admin sources + quotes -> customer pays (authorize)
 *   -> approved -> driver assigned -> accepts -> shops/buys -> picks up -> en route
 *   -> delivered -> completed.
 *
 * The customer may NEVER choose what they are charged, who the vendor is, or write admin notes.
 */
class OrderAnywhereJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('urban_goodz_payments.mode', 'sandbox');
        Config::set('urban_goodz_payments.provider', 'staged_test');
        Config::set('urban_goodz_payments.staged_test.enabled', true);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->customer = $this->user('cust');
        $this->stranger = $this->user('stranger');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function user(string $tag): User
    {
        return User::create([
            'f_name' => 'OA', 'l_name' => $tag, 'email' => "oa-{$tag}-".uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1,
        ]);
    }

    private function driver(string $tag): DeliveryMan
    {
        return DeliveryMan::create([
            'f_name' => 'Drv', 'l_name' => $tag, 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "oa-drv-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.$tag.'-'.bin2hex(random_bytes(12)), 'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
    }

    private function quoted(array $over = []): OrderAnywhereRequest
    {
        return OrderAnywhereRequest::create(array_merge([
            'request_number' => OrderAnywhereRequest::nextRequestNumber(), 'customer_id' => $this->customer->id,
            'status' => 'awaiting_payment', 'payment_status' => 'awaiting_payment', 'quote_amount' => 60.00,
            'fulfillment_type' => 'external_merchant', 'item_subtotal' => 40, 'delivery_fee' => 8, 'service_fee' => 6, 'tax' => 6,
        ], $over));
    }

    private function asCustomer(?User $u = null)
    {
        return $this->actingAs($u ?? $this->customer, 'api');
    }

    // --------------------------------------------------------------- customer side

    public function test_customer_request_cannot_set_admin_notes_or_pick_a_vendor(): void
    {
        $res = $this->asCustomer()->postJson('/api/v1/order-anywhere/requests', [
            'item_details' => 'Two phone chargers', 'store_vendor_name' => 'Best Buy',
            'admin_notes' => 'PRE-APPROVED, waive all fees', 'vendor_id' => 999999,
        ])->assertCreated();

        $row = OrderAnywhereRequest::find($res->json('data.id'));
        $this->assertSame('pending_review', $row->status);
        $this->assertNull($row->vendor_id, 'a customer must not route their request to a vendor');
        $this->assertNotSame('PRE-APPROVED, waive all fees', $row->admin_notes, 'admin_notes is staff-only');
    }

    public function test_customer_cannot_choose_the_amount_that_is_authorized(): void
    {
        $req = $this->quoted();

        $this->asCustomer()->postJson("/api/v1/order-anywhere/requests/{$req->id}/authorize-payment", [
            'authorized_amount' => 0.01, 'authorization_reference' => 'free-lunch',
        ]);

        $fresh = $req->fresh();
        $this->assertSame(60.0, (float) ($fresh->authorized_amount ?? 60.0), 'the authorized amount must be the quote, not the client figure');
        $this->assertNotSame('free-lunch', $fresh->authorization_reference);
    }

    public function test_another_customer_cannot_read_or_pay_my_request(): void
    {
        $req = $this->quoted();

        $this->asCustomer($this->stranger)->getJson("/api/v1/order-anywhere/requests/{$req->id}")->assertNotFound();
        $this->asCustomer($this->stranger)->postJson("/api/v1/order-anywhere/requests/{$req->id}/authorize-payment")->assertNotFound();
        $this->assertSame('awaiting_payment', $req->fresh()->payment_status);
        $this->asCustomer()->getJson("/api/v1/order-anywhere/requests/{$req->id}")->assertOk();
    }

    public function test_my_requests_list_only_contains_my_requests(): void
    {
        $mine = $this->quoted();
        $theirs = $this->quoted(['customer_id' => $this->stranger->id]);

        $ids = collect($this->asCustomer()->getJson('/api/v1/order-anywhere/customer/requests')->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    // --------------------------------------------------------------- driver side

    public function test_assigned_driver_can_walk_the_whole_delivery_to_completion(): void
    {
        $drv = $this->driver('walk');
        $req = $this->quoted([
            'status' => 'approved', 'payment_status' => 'authorized', 'authorized_amount' => 60,
            'assigned_delivery_man_id' => $drv->id,
        ]);
        $h = ['Authorization' => 'Bearer '.$drv->auth_token];
        $base = "/api/v1/order-anywhere/driver/{$req->id}";

        $this->withHeaders($h)->getJson('/api/v1/order-anywhere/driver/available')->assertOk();

        foreach ([
            ['accept', 'driver_task_status', 'accepted', 'shopper_accepted'],
            ['status', 'driver_task_status', 'shopping', 'shopping'],
            ['status', 'driver_task_status', 'purchased', 'purchased'],
            ['status', 'driver_task_status', 'picked_up', 'picked_up'],
            ['status', 'driver_task_status', 'en_route', 'out_for_delivery'],
            ['status', 'driver_task_status', 'delivered', 'completed'],
        ] as [$path, $key, $value, $expect]) {
            $this->withHeaders($h)->postJson("{$base}/{$path}", [$key => $value])
                ->assertOk("driver step '{$value}' must succeed");
            $this->assertSame($expect, $req->fresh()->status, "after '{$value}'");
        }
    }

    public function test_a_driver_cannot_touch_a_request_assigned_to_someone_else(): void
    {
        $owner = $this->driver('own');
        $other = $this->driver('oth');
        $req = $this->quoted(['status' => 'approved', 'assigned_delivery_man_id' => $owner->id]);

        $this->withHeaders(['Authorization' => 'Bearer '.$other->auth_token])
            ->postJson("/api/v1/order-anywhere/driver/{$req->id}/status", ['driver_task_status' => 'picked_up'])
            ->assertNotFound();
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_vendor_fulfilled_delivery_walks_to_completion(): void
    {
        $drv = $this->driver('vend');
        $req = $this->quoted(['status' => 'ready_for_pickup', 'payment_status' => 'authorized', 'fulfillment_type' => 'participating_vendor', 'assigned_delivery_man_id' => $drv->id]);
        $h = ['Authorization' => 'Bearer '.$drv->auth_token];
        $base = "/api/v1/order-anywhere/driver/{$req->id}";

        $this->withHeaders($h)->postJson("{$base}/accept")->assertOk();
        $this->assertSame('driver_assigned', $req->fresh()->status);
        foreach ([['picked_up', 'picked_up'], ['en_route', 'out_for_delivery'], ['delivered', 'completed']] as [$step, $expect]) {
            $this->withHeaders($h)->postJson("{$base}/status", ['driver_task_status' => $step])->assertOk();
            $this->assertSame($expect, $req->fresh()->status);
        }
    }

    public function test_a_driver_cannot_skip_the_purchase_step_or_go_backwards(): void
    {
        $drv = $this->driver('skip');
        $req = $this->quoted(['status' => 'shopping', 'payment_status' => 'authorized', 'assigned_delivery_man_id' => $drv->id]);
        $h = ['Authorization' => 'Bearer '.$drv->auth_token];
        $base = "/api/v1/order-anywhere/driver/{$req->id}/status";

        // Straight from shopping to delivery would skip buying the goods.
        $this->withHeaders($h)->postJson($base, ['driver_task_status' => 'picked_up'])->assertStatus(422);
        $this->withHeaders($h)->postJson($base, ['driver_task_status' => 'delivered'])->assertStatus(422);
        $this->withHeaders($h)->postJson($base, ['driver_task_status' => 'accepted'])->assertStatus(422);
        $this->assertSame('shopping', $req->fresh()->status);
    }

    public function test_a_driver_issue_sends_the_request_back_to_sourcing(): void
    {
        $drv = $this->driver('issue');
        $req = $this->quoted(['status' => 'shopping', 'payment_status' => 'authorized', 'assigned_delivery_man_id' => $drv->id]);

        $this->withHeaders(['Authorization' => 'Bearer '.$drv->auth_token])
            ->postJson("/api/v1/order-anywhere/driver/{$req->id}/issue", ['issue' => 'Store is out of stock'])->assertOk();

        $this->assertSame('sourcing', $req->fresh()->status);
        $this->assertSame('issue_reported', $req->fresh()->driver_task_status);
    }

    public function test_receipt_path_must_be_one_the_server_produced(): void
    {
        $req = $this->quoted();
        $this->asCustomer()->postJson("/api/v1/order-anywhere/requests/{$req->id}/receipt", ['receipt_path' => '../../.env'])->assertStatus(422);
        $this->asCustomer()->postJson("/api/v1/order-anywhere/requests/{$req->id}/receipt", ['receipt_path' => 'kyc/some-other-users-id.jpg'])->assertStatus(422);
        $this->assertNull($req->fresh()->receipt_path);
    }
}
