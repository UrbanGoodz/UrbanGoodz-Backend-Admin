<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Signing out must end the session on the server, not just on the handset.
 *
 * The delivery-man API had no logout endpoint — the Vendor app has had one all
 * along — so the app cleared its local copy of the token while
 * `delivery_men.auth_token` stayed valid forever. A lost, stolen or resold
 * phone kept a working bearer, and the stored FCM token kept delivering that
 * driver's assignments to it.
 */
class UrbanGoodzDeliverymanLogoutTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryMan $driver;
    private string $token = 'UG_TEST_LOGOUT_TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::withoutGlobalScopes()->where('status', 1)->firstOrFail();

        $this->driver = DeliveryMan::create([
            'f_name' => 'UGTest',
            'l_name' => 'Logout',
            'phone' => '+1555' . random_int(1000000, 9999999),
            'email' => 'ugtest.logout.' . random_int(1000, 9999) . '@urbangoodz.test',
            'password' => bcrypt('password'),
            'type' => 'zone_wise',
            'zone_id' => $store->zone_id,
            'status' => 1,
            'active' => 1,
            'application_status' => 'approved',
            'earning' => 1,
            'current_orders' => 0,
            'auth_token' => $this->token,
            'fcm_token' => 'FAKE_FCM_TOKEN',
        ]);
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    public function test_logout_clears_the_server_side_session(): void
    {
        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out');

        $this->assertNull(
            DeliveryMan::whereKey($this->driver->id)->value('auth_token'),
            'auth_token must be cleared so the old bearer stops working'
        );
    }

    /** A logged-out device must not keep receiving that driver's assignments. */
    public function test_logout_clears_the_push_token(): void
    {
        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertOk();

        $this->assertNull(DeliveryMan::whereKey($this->driver->id)->value('fcm_token'));
    }

    public function test_the_old_token_is_rejected_after_logout(): void
    {
        $this->getJson('/api/v1/delivery-man/profile', $this->bearer($this->token))->assertOk();

        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertOk();

        $this->getJson('/api/v1/delivery-man/profile', $this->bearer($this->token))
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'unauthorized');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/delivery-man/logout', [], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_logout_does_not_disturb_another_driver(): void
    {
        $other = DeliveryMan::create([
            'f_name' => 'UGTest',
            'l_name' => 'Bystander',
            'phone' => '+1555' . random_int(1000000, 9999999),
            'email' => 'ugtest.bystander.' . random_int(1000, 9999) . '@urbangoodz.test',
            'password' => bcrypt('password'),
            'type' => 'zone_wise',
            'status' => 1,
            'active' => 1,
            'application_status' => 'approved',
            'auth_token' => 'UG_TEST_OTHER_TOKEN',
            'fcm_token' => 'OTHER_FCM',
        ]);

        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertOk();

        $this->assertSame('UG_TEST_OTHER_TOKEN', DeliveryMan::whereKey($other->id)->value('auth_token'));
        $this->assertSame('OTHER_FCM', DeliveryMan::whereKey($other->id)->value('fcm_token'));
    }

    /** Signing out twice is not an error; the second call simply 401s. */
    public function test_logout_is_safe_to_repeat(): void
    {
        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertOk();
        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertStatus(401);

        $this->assertNull(DeliveryMan::whereKey($this->driver->id)->value('auth_token'));
    }

    /** Logging out must not touch the account, only the session. */
    public function test_logout_preserves_the_driver_account(): void
    {
        $this->postJson('/api/v1/delivery-man/logout', [], $this->bearer($this->token))->assertOk();

        $row = DeliveryMan::whereKey($this->driver->id)->first();

        $this->assertNotNull($row, 'the account must survive a logout');
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('approved', $row->application_status);
    }
}
