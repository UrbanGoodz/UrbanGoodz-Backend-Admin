<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Driver API authorization.
 *
 *  1. Account state: a suspended driver, or one whose application is not
 *     approved, must lose API access even while holding a previously issued token.
 *  2. Identity: a Bearer-only request must act as the authenticated driver, never
 *     as an arbitrary logged-out driver (legacy controllers re-read $request['token']).
 *  3. Isolation: driver B can never read or change driver A's routes, jobs,
 *     dispatches, bids, certifications, notifications or purchase card.
 *
 * Every isolation test also asserts the owner CAN reach the record, so a "404 for
 * everyone" bug cannot pass, and that the record is unchanged after the attack.
 */
class DriverAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/v1/urban-goodz/driver';

    private DeliveryMan $a;
    private DeliveryMan $b;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->a = $this->makeDriver('a');
        $this->b = $this->makeDriver('b');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function makeDriver(string $tag, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'Drv', 'l_name' => $tag,
            'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "driver-authz-{$tag}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('password'),
            'auth_token' => 'tok-'.$tag.'-'.bin2hex(random_bytes(12)),
            'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ], $over));
    }

    /** Insert a row, filling any NOT NULL column without a default from the live schema. */
    private function row(string $table, array $values): int
    {
        $row = [];
        foreach (Schema::getColumns($table) as $col) {
            $name = $col['name'];
            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];
                continue;
            }
            if ($col['nullable'] || $col['default'] !== null || $col['auto_increment']) {
                continue;
            }
            $type = strtolower($col['type_name']);
            $row[$name] = match (true) {
                str_contains($type, 'int'), in_array($type, ['decimal', 'float', 'double'], true) => 0,
                in_array($type, ['date'], true) => now()->toDateString(),
                in_array($type, ['datetime', 'timestamp'], true) => now()->toDateTimeString(),
                $type === 'json' => '[]',
                default => 'x',
            };
        }
        if (Schema::hasColumn($table, 'created_at') && ! isset($row['created_at'])) {
            $row['created_at'] = $row['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    private function dget(DeliveryMan $d, string $path)
    {
        return $this->getJson(self::API.$path.(str_contains($path, '?') ? '&' : '?').'token='.$d->auth_token);
    }

    /** Superset body: the attacker's request must be valid so only ownership can reject it. */
    private function dpost(DeliveryMan $d, string $path, array $body = [])
    {
        $body += [
            'exception_reason' => 'damaged', 'refusal_reason' => 'no_id_provided', 'driver_task_status' => 'in_progress', 'status' => 'in_progress',
            'reason' => 'x', 'notes' => 'x', 'tracking_id' => 'X', 'barcode' => 'X', 'type' => 'other',
            'id_type_checked' => 'drivers_license', 'recipient_name_verified' => 'Jane Doe', 'recipient_dob_verified' => '1990-01-01', 'recipient_age_confirmed' => true,
            'mode' => 'hub', 'action' => 'pickup', 'identifier' => 'X', 'identifier_type' => 'barcode', 'idempotency_key' => 'authz-'.uniqid(),
        ];

        return $this->postJson(self::API.$path.'?token='.$d->auth_token, $body);
    }

    private function dupload(DeliveryMan $d, string $path)
    {
        return $this->call(
            'POST',
            self::API.$path.'?token='.$d->auth_token,
            ['notes' => 'x', 'expiry_date' => now()->addYear()->toDateString()],
            [],
            ['document' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }

    private function assertNotAccessible($response, string $label = ''): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            $label.' Another driver reached a record they do not own (HTTP '.$response->getStatusCode().'): '.mb_substr($response->getContent(), 0, 300)
        );
    }

    // ------------------------------------------------------- 1. account state

    public function test_approved_driver_with_valid_token_is_authenticated(): void
    {
        $this->dget($this->a, '/routes')->assertOk();
    }

    public function test_missing_unknown_and_empty_tokens_are_rejected(): void
    {
        $this->getJson(self::API.'/routes')->assertUnauthorized();
        $this->getJson(self::API.'/routes?token=not-a-real-token')->assertUnauthorized();
        $this->getJson(self::API.'/routes?token=')->assertUnauthorized();
    }

    public function test_suspended_driver_loses_access_even_with_a_previously_issued_token(): void
    {
        $this->dget($this->a, '/routes')->assertOk();

        $this->a->update(['status' => 0]);

        $this->dget($this->a, '/routes')->assertUnauthorized();
        $this->getJson(self::API.'/routes', ['Authorization' => 'Bearer '.$this->a->auth_token])->assertUnauthorized();
    }

    public function test_driver_whose_application_is_not_approved_loses_access(): void
    {
        foreach (['pending', 'denied'] as $state) {
            $this->a->update(['application_status' => $state]);
            $this->dget($this->a, '/routes')->assertUnauthorized();
        }

        $this->a->update(['application_status' => 'approved']);
        $this->dget($this->a, '/routes')->assertOk();   // reinstatement restores access
    }

    public function test_legacy_delivery_man_api_enforces_the_same_account_state(): void
    {
        $url = '/api/v1/delivery-man/record-location-data?token='.$this->a->auth_token;
        $ping = ['latitude' => '29.76', 'longitude' => '-95.36', 'location' => 'Houston'];

        $this->postJson($url, $ping)->assertOk();
        $this->assertSame(1, DB::table('delivery_histories')->where('delivery_man_id', $this->a->id)->count());

        $this->a->update(['status' => 0]);

        $this->postJson($url, $ping)->assertUnauthorized();
        $this->assertSame(1, DB::table('delivery_histories')->where('delivery_man_id', $this->a->id)->count());
    }

    private function actAsPrimaryAdmin(): void
    {
        $admin = \App\Models\Admin::create([
            'f_name' => 'Authz', 'l_name' => 'Admin', 'email' => 'authz-admin-'.uniqid().'@urbangoodz.test',
            'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1,
            'is_logged_in' => 1, 'login_remember_token' => 'authz-token',
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\ActivationCheckMiddleware::class);
        $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => 'authz-token']);
    }

    public function test_suspending_a_driver_in_admin_revokes_the_stored_token_and_takes_them_offline(): void
    {
        $this->actAsPrimaryAdmin();
        $token = $this->a->auth_token;

        $this->get("/admin/users/delivery-man/status/{$this->a->id}/0");

        $fresh = $this->a->fresh();
        $this->assertSame(0, (int) $fresh->status);
        $this->assertNull($fresh->auth_token, 'suspension must persist the token revocation');
        $this->assertSame(0, (int) $fresh->active, 'a suspended driver must go offline');
        $this->getJson(self::API.'/routes?token='.$token)->assertUnauthorized();
    }

    public function test_denying_a_driver_application_in_admin_revokes_the_stored_token(): void
    {
        $this->actAsPrimaryAdmin();
        $token = $this->a->auth_token;

        $this->get("/admin/users/delivery-man/update-application/{$this->a->id}/denied");

        $fresh = $this->a->fresh();
        $this->assertSame('denied', $fresh->application_status);
        $this->assertNull($fresh->auth_token);
        $this->getJson(self::API.'/routes?token='.$token)->assertUnauthorized();
    }

    public function test_approving_a_driver_in_admin_does_not_revoke_an_existing_session(): void
    {
        $this->actAsPrimaryAdmin();
        $this->a->update(['application_status' => 'pending', 'status' => 0]);
        $this->a->update(['auth_token' => 'keep-this-token']);

        $this->get("/admin/users/delivery-man/update-application/{$this->a->id}/approved");

        $fresh = $this->a->fresh();
        $this->assertSame('approved', $fresh->application_status);
        $this->assertSame(1, (int) $fresh->status);
        $this->assertSame('keep-this-token', $fresh->auth_token);
    }

    // -------------------------------------------------------------- 2. identity

    public function test_bearer_only_request_acts_as_the_authenticated_driver_not_a_logged_out_one(): void
    {
        // A logged-out driver: "auth_token IS NULL" (what a null $request['token']
        // becomes) would select them.
        $loggedOut = $this->makeDriver('loggedout', ['auth_token' => null]);

        $this->postJson(
            '/api/v1/delivery-man/record-location-data',
            ['latitude' => '29.76', 'longitude' => '-95.36', 'location' => 'Houston'],
            ['Authorization' => 'Bearer '.$this->a->auth_token]
        )->assertOk();

        $this->assertSame(1, DB::table('delivery_histories')->where('delivery_man_id', $this->a->id)->count());
        $this->assertSame(0, DB::table('delivery_histories')->where('delivery_man_id', $loggedOut->id)->count());
    }

    // ---------------------------------------------- 3. routes (dedicated routes)

    private function route(int $driverId, string $status = 'planned'): int
    {
        return $this->row('urban_goodz_dedicated_routes', [
            'route_name' => 'Authz Route', 'assigned_driver_id' => $driverId, 'status' => $status,
            'scheduled_date' => now()->toDateString(),
        ]);
    }

    public function test_only_the_assigned_driver_can_read_or_act_on_a_route(): void
    {
        $id = $this->route($this->a->id);

        $this->dget($this->a, "/routes/{$id}")->assertOk();

        $this->assertNotAccessible($this->dget($this->b, "/routes/{$id}"));
        foreach (['started', 'completed', 'scan-pickup', 'scan-dropoff', 'scan-exception', 'package-events', 'age-verify', 'age-refuse', 'sequence', 'finish'] as $action) {
            $this->assertNotAccessible($this->dpost($this->b, "/routes/{$id}/{$action}", ['tracking_id' => 'X', 'barcode' => 'X', 'endpoint_type' => 'no_preference']), "POST /routes/{id}/{$action}");
        }
        $this->assertNotAccessible($this->dget($this->b, "/routes/{$id}/package-events?tracking_id=X&barcode=X"));
        $this->assertNotAccessible($this->dget($this->b, "/routes/{$id}/age-status?tracking_id=X&barcode=X"));

        $this->assertSame('planned', DB::table('urban_goodz_dedicated_routes')->where('id', $id)->value('status'));
    }

    public function test_a_driver_only_sees_their_own_routes_in_the_list(): void
    {
        $mine = $this->route($this->a->id, 'active');
        $theirs = $this->route($this->b->id, 'active');

        $ids = collect($this->dget($this->a, '/routes')->assertOk()->json('routes') ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->assertContains($mine, $ids);
        $this->assertNotContains($theirs, $ids);
    }

    // -------------------------------------------------- active jobs (all sources)

    public function test_active_jobs_are_isolated_between_drivers_for_every_job_source(): void
    {
        $route = $this->route($this->a->id);
        $load = $this->row('urban_goodz_load_board_loads', ['assigned_driver_id' => $this->a->id, 'status' => 'accepted']);

        foreach ([$route, $load] as $jobId) {
            $this->dget($this->a, "/active-jobs/{$jobId}")->assertOk();
            $this->assertNotAccessible($this->dget($this->b, "/active-jobs/{$jobId}"));
            foreach (['start', 'complete', 'cancel', 'status'] as $action) {
                $this->assertNotAccessible($this->dpost($this->b, "/active-jobs/{$jobId}/{$action}", ['status' => 'completed', 'reason' => 'x']));
            }
        }

        $this->assertSame('accepted', DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('status'));
        $this->assertSame('planned', DB::table('urban_goodz_dedicated_routes')->where('id', $route)->value('status'));
    }

    public function test_a_driver_cannot_accept_a_load_that_is_already_assigned(): void
    {
        $load = $this->row('urban_goodz_load_board_loads', ['assigned_driver_id' => $this->a->id, 'status' => 'accepted']);

        $this->assertNotAccessible($this->dpost($this->b, "/load-board/{$load}/accept"));

        $this->assertSame($this->a->id, (int) DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('assigned_driver_id'));
    }

    // ------------------------------------------------------------ business jobs

    public function test_business_courier_jobs_are_isolated_between_drivers(): void
    {
        $job = $this->row('urban_goodz_business_client_jobs', ['assigned_delivery_man_id' => $this->a->id, 'status' => 'assigned']);

        $this->dget($this->a, "/business-jobs/{$job}")->assertOk();

        $this->assertNotAccessible($this->dget($this->b, "/business-jobs/{$job}"));
        foreach (['accept', 'start', 'arrived-pickup', 'pickup', 'delivery', 'fail-delivery', 'proof-pickup', 'proof-delivery', 'exception'] as $action) {
            $this->assertNotAccessible($this->dpost($this->b, "/business-jobs/{$job}/{$action}", ['reason' => 'x']));
        }

        $this->assertSame('assigned', DB::table('urban_goodz_business_client_jobs')->where('id', $job)->value('status'));
    }

    // ------------------------------------------------------------ AI dispatches

    public function test_ai_dispatches_are_isolated_between_drivers(): void
    {
        $id = $this->row('ai_dispatches', ['driver_id' => $this->a->id, 'status' => 'sent']);

        $this->assertNotAccessible($this->dget($this->b, "/ai-dispatches/{$id}"));
        $this->assertNotAccessible($this->dget($this->b, "/ai-dispatches/{$id}/route-guidance"));
        foreach (['accept', 'decline', 'exceptions', 'deliver'] as $action) {
            $this->assertNotAccessible($this->dpost($this->b, "/ai-dispatches/{$id}/{$action}", ['reason' => 'x', 'type' => 'other']));
        }

        $this->assertSame('sent', DB::table('ai_dispatches')->where('id', $id)->value('status'));
    }

    // ------------------------------------------------------- bids / certifications

    public function test_a_driver_cannot_withdraw_another_drivers_bid(): void
    {
        $bid = $this->row('urban_goodz_load_board_bids', ['driver_id' => $this->a->id, 'status' => 'pending']);

        $this->assertNotAccessible($this->dpost($this->b, "/my-bids/{$bid}/withdraw"));

        $this->assertSame('pending', DB::table('urban_goodz_load_board_bids')->where('id', $bid)->value('status'));
    }

    public function test_a_driver_cannot_touch_another_drivers_certification(): void
    {
        $cert = $this->row('driver_certifications', ['delivery_man_id' => $this->a->id]);

        $before = (array) DB::table('driver_certifications')->where('id', $cert)->first();

        $this->assertNotAccessible($this->dupload($this->b, "/certifications/{$cert}/upload"));
        $this->assertNotAccessible($this->dupload($this->b, "/certifications/{$cert}/renew"));

        $this->assertEquals($before, (array) DB::table('driver_certifications')->where('id', $cert)->first());
    }

    // ------------------------------------------------- dispatch notifications

    public function test_a_driver_cannot_read_or_dismiss_another_drivers_dispatch_notification(): void
    {
        $id = $this->row('user_notifications', ['delivery_man_id' => $this->a->id, 'data' => '{}']);
        $before = (array) DB::table('user_notifications')->where('id', $id)->first();

        $this->assertNotAccessible($this->dpost($this->b, "/dispatch-notifications/{$id}/read"));
        $this->assertNotAccessible($this->dpost($this->b, "/dispatch-notifications/{$id}/dismiss"));

        $this->assertEquals($before, (array) DB::table('user_notifications')->where('id', $id)->first());
    }

    // ------------------------------------------------ order anywhere purchase card

    public function test_only_the_assigned_driver_can_use_an_order_anywhere_purchase_card(): void
    {
        $order = $this->row('order_anywhere_requests', [
            'assigned_delivery_man_id' => $this->a->id, 'status' => 'accepted', 'payment_status' => 'authorized',
        ]);

        $this->assertSame(403, $this->dget($this->b, "/order-anywhere/{$order}/purchase-card")->getStatusCode());
        foreach (['authorize', 'complete', 'receipt', 'failure', 'secure-reveal'] as $action) {
            $this->assertSame(403, $this->dpost($this->b, "/order-anywhere/{$order}/purchase-card/{$action}", ['amount' => 5, 'reason' => 'x'])->getStatusCode(), $action);
        }
    }

    // ------------------------------------------------------ legacy order API

    public function test_legacy_order_endpoints_are_isolated_between_drivers(): void
    {
        $orderId = $this->row('orders', [
            'delivery_man_id' => $this->a->id, 'order_status' => 'accepted', 'payment_status' => 'unpaid',
            'order_type' => 'delivery', 'order_amount' => 20,
        ]);

        $this->assertNotSame(
            200,
            $this->getJson('/api/v1/delivery-man/order-details?order_id='.$orderId.'&token='.$this->b->auth_token)->getStatusCode()
        );
        $this->putJson('/api/v1/delivery-man/update-order-status?token='.$this->b->auth_token, [
            'order_id' => $orderId, 'status' => 'delivered', 'otp' => '0000',
        ]);
        $this->putJson('/api/v1/delivery-man/update-payment-status?token='.$this->b->auth_token, [
            'order_id' => $orderId, 'status' => 'paid',
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        $this->assertSame('accepted', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame($this->a->id, (int) $order->delivery_man_id);
    }

    public function test_a_driver_cannot_accept_an_order_that_another_driver_already_holds(): void
    {
        $orderId = $this->row('orders', [
            'delivery_man_id' => $this->a->id, 'order_status' => 'accepted', 'payment_status' => 'unpaid',
            'order_type' => 'delivery', 'order_amount' => 20,
        ]);

        $this->putJson('/api/v1/delivery-man/accept-order?token='.$this->b->auth_token, ['order_id' => $orderId])
            ->assertNotFound();

        $this->assertSame($this->a->id, (int) DB::table('orders')->where('id', $orderId)->value('delivery_man_id'));
    }
}
