<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerRefundRequestTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $other;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::updateOrInsert(['key' => 'refund_active_status'], ['value' => '1']);
        $mk = fn (string $t) => User::create(['f_name' => 'Rf', 'l_name' => $t, 'email' => "rf-{$t}-".uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1]);
        $this->owner = $mk('owner');
        $this->other = $mk('other');
        $this->order = Order::create([
            'user_id' => $this->owner->id, 'is_guest' => 0, 'order_amount' => 40, 'delivery_charge' => 5, 'dm_tips' => 0,
            'order_status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'digital_payment', 'order_type' => 'parcel',
        ]);
    }

    private function ask(User $u, array $over = [])
    {
        return $this->actingAs($u, 'api')->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/v1/customer/order/refund-request', array_merge(['order_id' => $this->order->id, 'customer_reason' => 'Item damaged'], $over));
    }

    public function test_the_owner_gets_exactly_one_refund_request_even_when_they_ask_twice(): void
    {
        $this->ask($this->owner)->assertOk();
        $this->ask($this->owner)->assertStatus(403);

        $this->assertSame(1, Refund::where('order_id', $this->order->id)->count(), 'a repeat tap must not create a second refund to approve');
        $this->assertSame('refund_requested', $this->order->fresh()->order_status);
    }

    public function test_someone_else_cannot_request_a_refund_on_my_order(): void
    {
        $this->ask($this->other)->assertNotFound();

        $this->assertSame(0, Refund::where('order_id', $this->order->id)->count());
        $this->assertSame('delivered', $this->order->fresh()->order_status);
    }

    public function test_a_caller_without_an_account_gets_a_clean_refusal_not_a_server_error(): void
    {
        $res = $this->postJson('/api/v1/customer/order/refund-request', ['order_id' => $this->order->id, 'customer_reason' => 'x', 'guest_id' => '880077']);

        $this->assertContains($res->status(), [401, 403], 'status was '.$res->status());
        $this->assertSame(0, Refund::where('order_id', $this->order->id)->count());
    }
}
