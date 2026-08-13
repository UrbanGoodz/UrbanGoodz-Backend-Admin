<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivationCheckMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fixture-free coverage for the driver duty-status toggle
 * (POST /api/v1/delivery-man/update-active-status), complementing
 * tests/Feature/StagingP0/DriverActiveStatusContractTest.php which requires
 * the "isolated staging" fixture database (delivery_men id 9001-9003) and
 * cannot run against a plain from-scratch migrated database.
 *
 * This test creates its own driver row so it can run in any environment
 * that has the schema migrated, per Lane 1 certification requirements.
 */
class DriverActiveStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/delivery-man/update-active-status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ActivationCheckMiddleware::class, ThrottleRequests::class]);
    }

    private function makeDriver(array $overrides = []): array
    {
        $token = 'test-token-'.Str::random(24);

        $id = DB::table('delivery_men')->insertGetId(array_merge([
            'f_name'          => 'Test',
            'l_name'          => 'Driver',
            'phone'           => '+1555'.random_int(1000000, 9999999),
            'password'        => bcrypt('password'),
            'identity_image'  => json_encode(['id-front.png']),
            'active'          => 1,
            'auth_token'      => $token,
            'application_status' => 'approved',
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $overrides));

        return [$id, $token];
    }

    public function test_bearer_authenticated_driver_can_toggle_duty_status(): void
    {
        [$id, $token] = $this->makeDriver(['active' => 1]);

        $response = $this->postJson(self::URI, [], ['Authorization' => 'Bearer '.$token]);

        $this->assertNotSame(500, $response->status(), 'Duty toggle 500s for a Bearer-authenticated driver.');
        $response->assertStatus(200);
        $this->assertSame(0, (int) DB::table('delivery_men')->where('id', $id)->value('active'));
    }

    public function test_explicit_active_flag_is_idempotent(): void
    {
        [$id, $token] = $this->makeDriver(['active' => 0]);

        $this->postJson(self::URI, ['active' => 1], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', $id)->value('active'));

        // A retry of the same intent must not flip the driver back offline.
        $this->postJson(self::URI, ['active' => 1], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', $id)->value('active'));
    }

    public function test_legacy_token_field_still_works(): void
    {
        [$id, $token] = $this->makeDriver(['active' => 1]);

        $this->postJson(self::URI, ['token' => $token, 'active' => 0])
            ->assertStatus(200);

        $this->assertSame(0, (int) DB::table('delivery_men')->where('id', $id)->value('active'));
    }

    public function test_unauthenticated_caller_is_rejected(): void
    {
        [$id] = $this->makeDriver(['active' => 1]);

        $response = $this->postJson(self::URI, []);

        $response->assertStatus(401);
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', $id)->value('active'));
    }

    public function test_invalid_active_value_is_rejected_without_changing_state(): void
    {
        [$id, $token] = $this->makeDriver(['active' => 1]);

        $response = $this->postJson(self::URI, ['active' => 'maybe'], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', $id)->value('active'));
    }

    public function test_forged_bearer_token_is_rejected(): void
    {
        $this->makeDriver();

        $response = $this->postJson(self::URI, [], ['Authorization' => 'Bearer not-a-real-token']);

        $response->assertStatus(401);
    }
}
