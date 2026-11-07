<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Customer order endpoints must enforce ownership server-side.
 *
 * Found during customer certification:
 *  - GET customer/order/details skipped the ownership filter for guests, so any
 *    caller could read any order by incrementing order_id.
 *  - POST customer/order/wallet-payment accepted any order_id from any caller and
 *    debited that order's owner's wallet.
 */
class CustomerOrderOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    // Guest ids are numeric (guests table) and share a number space with customer ids.
    private const GUEST_A = 880001;
    private const GUEST_B = 880002;

    private User $owner;
    private User $other;
    private Order $ownersOrder;
    private Order $guestOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->makeUser('owner');
        $this->other = $this->makeUser('other');

        $this->ownersOrder = $this->makeOrder(['user_id' => $this->owner->id, 'is_guest' => 0]);
        $this->guestOrder = $this->makeOrder(['user_id' => self::GUEST_A, 'is_guest' => 1]);
    }

    private function makeUser(string $tag): User
    {
        return User::create([
            'f_name' => 'Own', 'l_name' => $tag,
            'email' => "ownership-{$tag}-".uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'password' => bcrypt('password'), 'is_active' => 1, 'is_verified' => 1,
        ]);
    }

    private function makeOrder(array $attrs): Order
    {
        // A parcel order carries its own payload, so details/ returns 200 for the owner
        // without needing line items.
        return Order::create(array_merge([
            'order_amount' => 25.00, 'delivery_charge' => 5.00,
            'order_status' => 'pending', 'payment_status' => 'unpaid',
            'payment_method' => 'wallet', 'order_type' => 'parcel',
            'delivery_address' => json_encode(['contact_person_name' => 'Secret Name', 'address' => '1 Private Rd']),
        ], $attrs));
    }

    private function asUser(User $user)
    {
        return $this->actingAs($user, 'api')->withHeader('Authorization', 'Bearer test-token');
    }

    public function test_owner_can_read_their_own_order(): void
    {
        $this->asUser($this->owner)
            ->getJson('/api/v1/customer/order/details?order_id='.$this->ownersOrder->id)
            ->assertOk();
    }

    public function test_another_customer_cannot_read_someone_elses_order(): void
    {
        $this->asUser($this->other)
            ->getJson('/api/v1/customer/order/details?order_id='.$this->ownersOrder->id)
            ->assertNotFound()
            ->assertDontSee('Secret Name');
    }

    public function test_unauthenticated_caller_without_guest_id_cannot_read_any_order(): void
    {
        $this->getJson('/api/v1/customer/order/details?order_id='.$this->ownersOrder->id)
            ->assertStatus(401)
            ->assertDontSee('Secret Name');
    }

    public function test_a_guest_cannot_read_a_signed_in_customers_order(): void
    {
        $this->getJson('/api/v1/customer/order/details?order_id='.$this->ownersOrder->id.'&guest_id='.self::GUEST_A)
            ->assertNotFound()
            ->assertDontSee('Secret Name');
    }

    public function test_a_guest_cannot_read_another_guests_order(): void
    {
        $this->getJson('/api/v1/customer/order/details?order_id='.$this->guestOrder->id.'&guest_id='.self::GUEST_B)
            ->assertNotFound()
            ->assertDontSee('Secret Name');
    }

    public function test_a_guest_can_read_their_own_order(): void
    {
        $this->getJson('/api/v1/customer/order/details?order_id='.$this->guestOrder->id.'&guest_id='.self::GUEST_A)
            ->assertOk();
    }

    public function test_wallet_payment_requires_authentication(): void
    {
        $this->postJson('/api/v1/customer/order/wallet-payment', ['order_id' => $this->ownersOrder->id, 'guest_id' => ''.self::GUEST_A.''])
            ->assertUnauthorized();

        $this->assertSame('unpaid', $this->ownersOrder->fresh()->payment_status);
    }

    public function test_wallet_payment_cannot_be_triggered_for_someone_elses_order(): void
    {
        $before = (float) $this->owner->fresh()->wallet_balance;

        $this->asUser($this->other)
            ->postJson('/api/v1/customer/order/wallet-payment', ['order_id' => $this->ownersOrder->id])
            ->assertNotFound();

        $order = $this->ownersOrder->fresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame($before, (float) $this->owner->fresh()->wallet_balance);
    }

    public function test_wallet_payment_for_an_unknown_order_is_a_404_not_a_server_error(): void
    {
        $this->asUser($this->owner)
            ->postJson('/api/v1/customer/order/wallet-payment', ['order_id' => 999999999])
            ->assertNotFound();
    }

    public function test_wallet_payment_refuses_a_canceled_order(): void
    {
        $this->ownersOrder->update(['order_status' => 'canceled']);

        $this->asUser($this->owner)
            ->postJson('/api/v1/customer/order/wallet-payment', ['order_id' => $this->ownersOrder->id])
            ->assertStatus(400);

        $this->assertSame('unpaid', $this->ownersOrder->fresh()->payment_status);
    }

    // --- guest id == customer id collisions (the numeric id space is shared) ---

    public function test_a_guest_whose_id_equals_a_customers_id_cannot_read_that_customers_order(): void
    {
        $this->getJson('/api/v1/customer/order/details?order_id='.$this->ownersOrder->id.'&guest_id='.$this->owner->id)
            ->assertNotFound()
            ->assertDontSee('Secret Name');
    }

    public function test_a_colliding_guest_cannot_see_the_customers_orders_in_lists(): void
    {
        foreach (['list?limit=10&offset=1', 'running-orders?limit=10&offset=1', 'all-running-orders'] as $path) {
            $response = $this->getJson('/api/v1/customer/order/'.$path.(str_contains($path, '?') ? '&' : '?').'guest_id='.$this->owner->id);
            $this->assertStringNotContainsString((string) $this->ownersOrder->id.'"', json_encode($response->json('orders') ?? $response->json() ?? []).'"');
            $this->assertSame([], (array) ($response->json('orders') ?? []), "{$path} leaked orders to a colliding guest");
        }
    }

    public function test_a_colliding_guest_cannot_cancel_or_change_payment_of_the_customers_order(): void
    {
        $this->putJson('/api/v1/customer/order/cancel', [
            'order_id' => $this->ownersOrder->id, 'guest_id' => $this->owner->id, 'reason' => 'x',
        ]);
        $this->putJson('/api/v1/customer/order/payment-method', [
            'order_id' => $this->ownersOrder->id, 'guest_id' => $this->owner->id, 'payment_method' => 'cash_on_delivery',
        ]);

        $order = $this->ownersOrder->fresh();
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('wallet', $order->payment_method);
    }

    public function test_offline_payment_and_payment_failed_are_scoped_to_the_owner(): void
    {
        // The endpoint refuses early when offline payments are switched off.
        config()->set('offline_payment_status_conf', ['value' => 1]);
        $this->asUser($this->other)
            ->putJson('/api/v1/customer/order/offline-payment', ['order_id' => $this->ownersOrder->id, 'method_id' => 1])
            ->assertNotFound();

        $this->asUser($this->other)
            ->getJson('/api/v1/customer/order/payment-failed?order_id='.$this->ownersOrder->id)
            ->assertOk()
            ->assertExactJson([]);

        $this->getJson('/api/v1/customer/order/payment-failed?order_id='.$this->ownersOrder->id.'&guest_id='.$this->owner->id)
            ->assertOk()
            ->assertExactJson([]);
    }
}
