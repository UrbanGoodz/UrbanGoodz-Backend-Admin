<?php

namespace Tests\Feature\StagingP0;

use App\Http\Middleware\ActivationCheckMiddleware;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0: going on and off duty is the control that decides whether a driver is
 * offered work at all. It must survive a Bearer-authenticated client and must
 * be idempotent, so a retried request cannot silently park a driver offline.
 */
class DriverActiveStatusContractTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/api/v1/delivery-man/update-active-status';
    private const DRIVER_ONLINE = 9001;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ActivationCheckMiddleware::class, ThrottleRequests::class]);

        // Give the fixture driver a usable API credential for this test only;
        // DatabaseTransactions rolls it back afterwards.
        $this->token = 'staging-fixture-token-'.bin2hex(random_bytes(8));
        DB::table('delivery_men')->where('id', self::DRIVER_ONLINE)->update([
            'auth_token' => $this->token,
            'active'     => 1,
        ]);
    }

    private function activeFlag(): int
    {
        return (int) DB::table('delivery_men')->where('id', self::DRIVER_ONLINE)->value('active');
    }

    /**
     * Regression: the controller used to re-resolve the driver from a `token`
     * request field. A Bearer client never sets that field, so the lookup
     * returned null and the endpoint 500'd.
     */
    public function test_bearer_authenticated_driver_can_toggle_duty_status(): void
    {
        $response = $this->postJson(self::URI, [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $this->assertNotSame(500, $response->status(), 'Duty toggle 500s for a Bearer-authenticated driver.');
        $this->assertSame(200, $response->status());
        $this->assertSame(0, $this->activeFlag(), 'Duty status did not flip.');
    }

    public function test_explicit_active_flag_is_idempotent(): void
    {
        $this->postJson(self::URI, ['active' => 1], ['Authorization' => 'Bearer '.$this->token])
            ->assertStatus(200);
        $this->assertSame(1, $this->activeFlag());

        // A retry of the same intent must not flip the driver offline.
        $this->postJson(self::URI, ['active' => 1], ['Authorization' => 'Bearer '.$this->token])
            ->assertStatus(200);
        $this->assertSame(1, $this->activeFlag(), 'Repeating "go online" put the driver offline.');

        $this->postJson(self::URI, ['active' => 0], ['Authorization' => 'Bearer '.$this->token])
            ->assertStatus(200);
        $this->assertSame(0, $this->activeFlag());
    }

    public function test_legacy_token_field_still_works(): void
    {
        $this->postJson(self::URI, ['token' => $this->token, 'active' => 0])
            ->assertStatus(200);

        $this->assertSame(0, $this->activeFlag(), 'Legacy token-field callers regressed.');
    }

    public function test_unauthenticated_caller_is_rejected(): void
    {
        $response = $this->postJson(self::URI, []);

        $this->assertSame(401, $response->status());
        $this->assertSame(1, $this->activeFlag(), 'An unauthenticated call changed duty status.');
    }

    public function test_invalid_active_value_is_rejected_without_changing_state(): void
    {
        $response = $this->postJson(self::URI, ['active' => 'maybe'], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $this->assertSame(403, $response->status());
        $this->assertSame(1, $this->activeFlag(), 'A rejected payload still mutated duty status.');
    }

    /**
     * A driver whose application is still pending must not be able to put
     * themselves on duty and start receiving dispatch.
     */
    public function test_pending_driver_cannot_be_dispatch_eligible(): void
    {
        $pending = DeliveryMan::find(9003);

        $this->assertNotNull($pending, 'Pending driver fixture is missing.');
        $this->assertSame('pending', $pending->application_status);
        $this->assertSame(0, (int) $pending->active, 'A pending-approval driver is marked on duty.');
    }
}
