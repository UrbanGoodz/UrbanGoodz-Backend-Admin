<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Regression cover for the Driver order handshake.
 *
 * The dm.api middleware authenticates from the Bearer header, but every action
 * in DeliverymanController resolves the driver with
 * DeliveryMan::where(['auth_token' => $request['token']]). When `token` is not
 * present as request input that compiles to `auth_token IS NULL`, which
 * resolves to an unrelated driver instead of failing closed. These tests pin
 * the contract: a Bearer-only request must resolve to the bearer's own driver.
 */
class UrbanGoodzDeliverymanOrderHandshakeTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryMan $driver;
    private DeliveryMan $otherDriver;
    private DeliveryMan $tokenlessDriver;
    private Store $store;
    private User $customer;
    private string $driverToken = 'UG_TEST_DM_TOKEN_PRIMARY';
    private string $otherDriverToken = 'UG_TEST_DM_TOKEN_SECONDARY';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('order_confirmation_model', 'deliveryman');
        Config::set('canceled_by_deliveryman', 1);
        Config::set('order_delivery_verification', 0);
        Config::set('dm_maximum_orders', 5);

        $this->store = Store::withoutGlobalScopes()->where('status', 1)->firstOrFail();
        $this->customer = User::firstOrFail();

        // A driver with no auth_token at all. `auth_token IS NULL` must never
        // resolve to this row on behalf of an authenticated bearer.
        $this->tokenlessDriver = $this->makeDriver('Tokenless', null);
        $this->driver = $this->makeDriver('Primary', $this->driverToken);
        $this->otherDriver = $this->makeDriver('Secondary', $this->otherDriverToken);
    }

    private function makeDriver(string $label, ?string $token, array $overrides = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'UGTest',
            'l_name' => $label,
            'phone' => '+1555' . random_int(1000000, 9999999),
            'email' => 'ugtest.' . strtolower($label) . '.' . random_int(1000, 9999) . '@urbangoodz.test',
            'password' => bcrypt('password'),
            'type' => 'zone_wise',
            'zone_id' => $this->store->zone_id,
            'status' => 1,
            'active' => 1,
            'application_status' => 'approved',
            'earning' => 1,
            'current_orders' => 0,
            'auth_token' => $token,
        ], $overrides));
    }

    private function makeOrder(array $overrides = []): Order
    {
        $order = new Order();
        $order->forceFill(array_merge([
            'user_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'zone_id' => $this->store->zone_id,
            'order_type' => 'delivery',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cash_on_delivery',
            'order_amount' => 28.69,
            'otp' => '1234',
            'delivery_man_id' => $this->driver->id,
            'scheduled' => 0,
            'checked' => 0,
        ], $overrides));
        $order->save();

        return $order;
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    private function updateStatus(string $token, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api/v1/delivery-man/update-order-status', $payload, $this->bearer($token));
    }

    private function freshStatus(int $orderId): ?string
    {
        return Order::withoutGlobalScopes()->whereKey($orderId)->value('order_status');
    }

    /** The PII-leak regression: a Bearer request must resolve to its own driver. */
    public function test_profile_with_bearer_token_returns_the_authenticated_driver(): void
    {
        $response = $this->getJson('/api/v1/delivery-man/profile', $this->bearer($this->driverToken));

        $response->assertOk();
        $this->assertSame($this->driver->id, $response->json('id'));
        $this->assertNotSame($this->tokenlessDriver->id, $response->json('id'));
    }

    /** A token supplied as input must never override the authenticated bearer. */
    public function test_token_input_cannot_override_the_bearer_identity(): void
    {
        $response = $this->getJson(
            '/api/v1/delivery-man/profile?token=' . $this->otherDriverToken,
            $this->bearer($this->driverToken)
        );

        $response->assertOk();
        $this->assertSame($this->driver->id, $response->json('id'));
    }

    public function test_request_without_any_token_is_rejected(): void
    {
        $this->getJson('/api/v1/delivery-man/profile', ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'unauthorized');
    }

    public function test_request_with_unknown_token_is_rejected(): void
    {
        $this->getJson('/api/v1/delivery-man/profile', $this->bearer('NOT_A_REAL_TOKEN'))
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'unauthorized');
    }

    /** The P1: the assigned, active driver could not confirm over Bearer auth. */
    public function test_assigned_active_driver_can_confirm_with_bearer_token(): void
    {
        $order = $this->makeOrder();

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])
            ->assertOk();

        $this->assertSame('confirmed', $this->freshStatus($order->id));
        $this->assertNotNull(Order::withoutGlobalScopes()->whereKey($order->id)->value('confirmed'));
    }

    public function test_driver_not_assigned_to_the_order_cannot_change_its_status(): void
    {
        $order = $this->makeOrder();

        $this->updateStatus($this->otherDriverToken, ['order_id' => $order->id, 'status' => 'confirmed'])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'not_found');

        $this->assertSame('pending', $this->freshStatus($order->id));
    }

    public function test_driver_cannot_change_status_of_an_unassigned_order(): void
    {
        $order = $this->makeOrder(['delivery_man_id' => null]);

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'not_found');

        $this->assertSame('pending', $this->freshStatus($order->id));
    }

    public function test_repeated_confirmation_is_idempotent(): void
    {
        $order = $this->makeOrder();

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])->assertOk();
        $firstConfirmedAt = Order::withoutGlobalScopes()->whereKey($order->id)->value('confirmed');

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])->assertOk();

        $this->assertSame('confirmed', $this->freshStatus($order->id));
        $this->assertNotNull($firstConfirmedAt);
        $this->assertSame(
            1,
            Order::withoutGlobalScopes()->whereKey($order->id)->count(),
            'Repeated confirmation must not duplicate the order row.'
        );
    }

    /** A replay must not rewrite when the order actually reached that status. */
    public function test_replayed_status_preserves_the_original_timestamp(): void
    {
        $originalConfirmedAt = now()->subMinutes(17)->startOfSecond();
        $order = $this->makeOrder([
            'order_status' => 'confirmed',
            'confirmed' => $originalConfirmedAt,
        ]);

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])
            ->assertOk();

        $this->assertSame(
            $originalConfirmedAt->toDateTimeString(),
            (string) Order::withoutGlobalScopes()->whereKey($order->id)->value('confirmed'),
            'A replayed status must not overwrite the original transition timestamp.'
        );
    }

    public function test_driver_confirmation_is_rejected_under_the_store_confirmation_model(): void
    {
        Config::set('order_confirmation_model', 'store');
        $order = $this->makeOrder();

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'confirmed'])
            ->assertStatus(403)
            ->assertJsonPath('errors.0.code', 'order-confirmation-model');

        $this->assertSame('pending', $this->freshStatus($order->id));
    }

    public function test_confirmed_order_moves_to_picked_up(): void
    {
        $order = $this->makeOrder(['order_status' => 'confirmed', 'confirmed' => now()]);

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'picked_up'])
            ->assertOk();

        $this->assertSame('picked_up', $this->freshStatus($order->id));
    }

    public function test_offline_driver_cannot_accept_an_order(): void
    {
        $offlineToken = 'UG_TEST_DM_TOKEN_OFFLINE';
        $this->makeDriver('Offline', $offlineToken, ['active' => 0]);
        $order = $this->makeOrder(['delivery_man_id' => null]);

        $this->putJson(
            '/api/v1/delivery-man/accept-order',
            ['order_id' => $order->id],
            $this->bearer($offlineToken)
        )->assertStatus(404)->assertJsonPath('errors.0.code', 'active_status');

        $this->assertNull(Order::withoutGlobalScopes()->whereKey($order->id)->value('delivery_man_id'));
    }

    public function test_already_assigned_order_cannot_be_accepted_by_another_driver(): void
    {
        $order = $this->makeOrder();

        $this->putJson(
            '/api/v1/delivery-man/accept-order',
            ['order_id' => $order->id],
            $this->bearer($this->otherDriverToken)
        )->assertStatus(404)->assertJsonPath('errors.0.code', 'order');

        $this->assertSame(
            $this->driver->id,
            Order::withoutGlobalScopes()->whereKey($order->id)->value('delivery_man_id')
        );
    }

    /** Replaying `delivered` must settle the order once, money and counters alike. */
    public function test_duplicate_completion_does_not_double_count(): void
    {
        $order = $this->makeOrder([
            'order_status' => 'picked_up',
            'confirmed' => now(),
            'processing' => now(),
            'handover' => now(),
            'picked_up' => now(),
        ]);
        $this->driver->forceFill(['current_orders' => 1])->save();

        $payload = ['order_id' => $order->id, 'status' => 'delivered', 'otp' => $order->otp];

        $this->updateStatus($this->driverToken, $payload)->assertOk();

        $driverOrderCount = DeliveryMan::whereKey($this->driver->id)->value('order_count');
        $storeOrderCount = Store::withoutGlobalScopes()->whereKey($this->store->id)->value('order_count');
        $customerOrderCount = User::whereKey($this->customer->id)->value('order_count');
        $transactionCount = \App\Models\OrderTransaction::where('order_id', $order->id)->count();

        $this->updateStatus($this->driverToken, $payload)->assertOk();

        $this->assertSame('delivered', $this->freshStatus($order->id));
        $this->assertSame(
            $driverOrderCount,
            DeliveryMan::whereKey($this->driver->id)->value('order_count'),
            'Driver order_count must not increment on a replayed delivery.'
        );
        $this->assertSame(
            $storeOrderCount,
            Store::withoutGlobalScopes()->whereKey($this->store->id)->value('order_count'),
            'Store order_count must not increment on a replayed delivery.'
        );
        $this->assertSame(
            $customerOrderCount,
            User::whereKey($this->customer->id)->value('order_count'),
            'Customer order_count must not increment on a replayed delivery.'
        );
        $this->assertSame(
            $transactionCount,
            \App\Models\OrderTransaction::where('order_id', $order->id)->count(),
            'A replayed delivery must not create a second order transaction.'
        );
    }

    public function test_status_value_outside_the_canonical_enum_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->updateStatus($this->driverToken, ['order_id' => $order->id, 'status' => 'accepted'])
            ->assertStatus(403);

        $this->assertSame('pending', $this->freshStatus($order->id));
    }
}
