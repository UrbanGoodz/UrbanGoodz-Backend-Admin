<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * OTR load board and medical courier: authorization, eligibility, lifecycle, payouts and
 * chain of custody.
 *
 * Both used to be reachable by any logged-in CUSTOMER account (customer Passport guard),
 * with the customer's user id used as the driver id. They are driver-only now, the acting
 * driver always comes from the token, and every transition is atomic.
 */
class OtrAndMedicalCourierTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private const DRIVER_API = '/api/v1/urban-goodz/driver';
    private const LOADS = '/api/v1/urban-goodz/load-board';
    private const MEDICAL = '/api/v1/urban-goodz/medical-courier';

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ----------------------------------------------------------------- fixtures

    private function driver(string $tag, array $over = []): DeliveryMan
    {
        return DeliveryMan::create(array_merge([
            'f_name' => 'Drv'.$tag, 'l_name' => 'Test', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => "otr-{$tag}-".uniqid().'@urbangoodz.test', 'password' => bcrypt('x'),
            'auth_token' => 'tok-'.$tag.'-'.bin2hex(random_bytes(10)),
            'status' => 1, 'active' => 1, 'application_status' => 'approved',
            'load_board_eligible' => 1, 'has_hazmat' => 0, 'current_orders' => 0,
            'has_medical_courier_training' => 0,
        ], $over));
    }

    private function load(array $over = []): int
    {
        return $this->insertRow('urban_goodz_load_board_loads', array_merge([
            'status' => 'available', 'load_number' => 'L-'.uniqid(), 'origin_city' => 'Houston', 'origin_state' => 'TX',
            'destination_city' => 'Dallas', 'destination_state' => 'TX', 'payout_amount' => 900, 'customer_price' => 1500,
            'platform_margin' => 400, 'source_cost' => 123, 'shipper_name' => 'SECRET-SHIPPER', 'shipper_phone' => '555-0100',
            'consignee_name' => 'SECRET-CONSIGNEE', 'consignee_phone' => '555-0199', 'is_hazmat' => 0,
        ], $over));
    }

    private function job(array $over = []): int
    {
        return $this->insertRow('urban_goodz_medical_courier_jobs', array_merge([
            'job_number' => 'MC-'.uniqid(), 'status' => 'pending', 'specimen_type' => 'blood', 'priority' => 'urgent',
            'pickup_location' => '1 Lab Way', 'pickup_facility_name' => 'City Lab', 'pickup_contact_name' => 'SECRET-CONTACT',
            'pickup_contact_phone' => '555-0111', 'delivery_location' => '2 Hospital Rd', 'delivery_facility_name' => 'General Hospital',
            'delivery_contact_name' => 'SECRET-RECIPIENT', 'delivery_contact_phone' => '555-0122', 'payout_amount' => 80,
            'admin_notes' => 'SECRET-ADMIN-NOTE',
        ], $over));
    }

    private function d(DeliveryMan $d, string $path): string
    {
        return $path.(str_contains($path, '?') ? '&' : '?').'token='.$d->auth_token;
    }

    private function loadStatus(int $id): string
    {
        return (string) DB::table('urban_goodz_load_board_loads')->where('id', $id)->value('status');
    }

    private function medicalDriver(string $tag = 'med'): DeliveryMan
    {
        return $this->driver($tag, ['has_medical_courier_training' => 1]);
    }

    // =============================================================== OTR: access

    public function test_a_customer_account_cannot_use_the_load_board_or_medical_apis(): void
    {
        $customer = User::create([
            'f_name' => 'Cust', 'l_name' => 'Omer', 'email' => 'cust-'.uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1,
        ]);
        $load = $this->load();
        $job = $this->job();

        $this->actingAs($customer, 'api');
        foreach ([
            ['GET', self::LOADS.'/loads'], ['GET', self::LOADS."/loads/{$load}"], ['POST', self::LOADS."/loads/{$load}/accept"],
            ['POST', self::LOADS."/loads/{$load}/status"], ['GET', self::MEDICAL.'/jobs'], ['GET', self::MEDICAL."/jobs/{$job}"],
            ['POST', self::MEDICAL."/jobs/{$job}/accept"], ['POST', self::MEDICAL."/jobs/{$job}/status"], ['POST', self::MEDICAL."/jobs/{$job}/custody"],
        ] as [$method, $uri]) {
            $status = $this->json($method, $uri, ['status' => 'delivered', 'driver_id' => 1])->getStatusCode();
            $this->assertSame(401, $status, "{$method} {$uri} answered {$status} to a customer account");
        }

        $this->assertSame('available', $this->loadStatus($load));
        $this->assertSame('pending', DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('status'));
    }

    // ============================================================ OTR: information

    public function test_drivers_never_see_margins_costs_or_shipper_contacts_before_assignment(): void
    {
        $this->load();
        $driver = $this->driver('a');

        $list = $this->getJson($this->d($driver, self::DRIVER_API.'/load-board'))->assertOk();
        $family = $this->getJson($this->d($driver, self::LOADS.'/loads'))->assertOk();

        foreach ([$list->getContent(), $family->getContent()] as $body) {
            foreach (['platform_margin', 'customer_price', 'source_cost', 'SECRET-SHIPPER', 'SECRET-CONSIGNEE', '555-0100', 'raw_source_payload'] as $leak) {
                $this->assertStringNotContainsString($leak, $body, "driver load list leaks {$leak}");
            }
        }
    }

    public function test_contacts_appear_only_for_the_driver_the_load_is_assigned_to(): void
    {
        $mine = $this->driver('mine');
        $other = $this->driver('other');
        $load = $this->load(['status' => 'assigned', 'assigned_driver_id' => $mine->id]);

        $this->getJson($this->d($mine, self::LOADS."/loads/{$load}"))->assertOk()->assertSee('SECRET-SHIPPER');
        $this->getJson($this->d($other, self::LOADS."/loads/{$load}"))->assertNotFound()->assertDontSee('SECRET-SHIPPER');
    }

    // ============================================================= OTR: eligibility

    public function test_a_driver_without_a_hazmat_endorsement_cannot_take_or_bid_on_a_hazmat_load(): void
    {
        $load = $this->load(['is_hazmat' => 1]);
        $plain = $this->driver('plain');
        $certified = $this->driver('haz', ['has_hazmat' => 1]);

        $this->postJson($this->d($plain, self::DRIVER_API."/load-board/{$load}/accept"))->assertForbidden();
        $this->postJson($this->d($plain, self::LOADS."/loads/{$load}/accept"))->assertForbidden();
        $this->postJson($this->d($plain, self::DRIVER_API."/load-board/{$load}/bid"), ['bid_amount' => 500])->assertForbidden();
        $this->assertSame('available', $this->loadStatus($load));

        $this->postJson($this->d($certified, self::DRIVER_API."/load-board/{$load}/accept"))->assertOk();
        $this->assertSame('assigned', $this->loadStatus($load));
    }

    public function test_the_service_recommendation_query_no_longer_crashes_on_hazmat_loads(): void
    {
        $load = \App\Models\UrbanGoodzLoadBoardLoad::find($this->load(['is_hazmat' => 1]));
        $haz = $this->driver('haz', ['has_hazmat' => 1]);
        $this->driver('plain');

        $eligible = app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class)->getEligibleDriversForLoad($load);

        $this->assertTrue($eligible->contains('id', $haz->id));
        $this->assertFalse($eligible->contains('name', 'x'));
        $this->assertSame(1, $eligible->where('has_hazmat', 1)->where('id', $haz->id)->count());
    }

    public function test_hazmat_is_enforced_even_when_a_dispatcher_assigns(): void
    {
        $load = $this->load(['is_hazmat' => 1]);
        $plain = $this->driver('plain');

        $this->assertNull(app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class)->acceptLoad($load, $plain->id, 7));
        $this->assertSame('available', $this->loadStatus($load));
    }

    public function test_load_board_vetting_and_declared_equipment_are_enforced_for_self_service(): void
    {
        $load = $this->load(['equipment_type' => 'Reefer']);
        $unvetted = $this->driver('unvetted', ['load_board_eligible' => 0]);
        $wrongTruck = $this->driver('van', ['vehicle_type' => 'Cargo Van']);
        $rightTruck = $this->driver('reefer', ['vehicle_type' => 'Reefer']);

        $this->postJson($this->d($unvetted, self::DRIVER_API."/load-board/{$load}/accept"))->assertForbidden();
        $this->postJson($this->d($wrongTruck, self::DRIVER_API."/load-board/{$load}/accept"))->assertForbidden();
        $this->postJson($this->d($rightTruck, self::DRIVER_API."/load-board/{$load}/accept"))->assertOk();
    }

    // ================================================================= OTR: bidding

    public function test_a_bid_is_actually_saved_and_cannot_be_duplicated_or_zero(): void
    {
        $load = $this->load();
        $driver = $this->driver('bidder');

        $this->postJson($this->d($driver, self::DRIVER_API."/load-board/{$load}/bid"), ['bid_amount' => 750])->assertOk();
        $this->assertSame(1, DB::table('urban_goodz_load_board_bids')->where('load_id', $load)->where('driver_id', $driver->id)->count(), 'the API said "submitted" but nothing was saved');
        $this->assertEquals(750, DB::table('urban_goodz_load_board_bids')->where('load_id', $load)->value('bid_amount'));

        $this->postJson($this->d($driver, self::DRIVER_API."/load-board/{$load}/bid"), ['bid_amount' => 700])->assertStatus(409);
        $this->assertSame(1, DB::table('urban_goodz_load_board_bids')->where('load_id', $load)->count());

        foreach ([0, -5] as $bad) {
            $this->postJson($this->d($this->driver('bad'.abs($bad)), self::DRIVER_API."/load-board/{$load}/bid"), ['bid_amount' => $bad])->assertStatus(422);
        }
    }

    // ============================================================== OTR: lifecycle

    public function test_a_load_goes_assigned_in_transit_delivered_in_order_with_an_audit_trail(): void
    {
        $load = $this->load();
        $driver = $this->driver('runner');

        $this->postJson($this->d($driver, self::DRIVER_API."/load-board/{$load}/accept"))->assertOk();
        $this->assertSame('assigned', $this->loadStatus($load));
        $this->assertSame(1, (int) $driver->fresh()->current_orders);

        // cannot deliver before it has started
        $this->postJson($this->d($driver, self::DRIVER_API."/active-jobs/{$load}/complete"))->assertStatus(409);
        $this->assertSame('assigned', $this->loadStatus($load));

        $this->postJson($this->d($driver, self::DRIVER_API."/active-jobs/{$load}/start"))->assertOk();
        $this->assertSame('in_transit', $this->loadStatus($load));

        $this->postJson($this->d($driver, self::DRIVER_API."/active-jobs/{$load}/complete"))->assertOk();
        $this->assertSame('delivered', $this->loadStatus($load));
        $this->assertNotNull(DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('delivered_at'));

        $events = DB::table('urban_goodz_load_board_audit_logs')->where('load_id', $load)->pluck('new_value')->all();
        foreach (['assigned', 'in_transit', 'picked_up', 'delivered'] as $step) {
            $this->assertContains($step, $events, "no audit event for {$step}");
        }
    }

    public function test_a_delivered_or_moving_load_cannot_be_released_back_to_the_board(): void
    {
        $driver = $this->driver('releaser');

        foreach (['in_transit', 'picked_up', 'delivered', 'completed'] as $state) {
            $load = $this->load(['status' => $state, 'assigned_driver_id' => $driver->id]);
            $this->postJson($this->d($driver, self::DRIVER_API."/active-jobs/{$load}/cancel"))->assertStatus(409);
            $this->assertSame($state, $this->loadStatus($load), "a {$state} load was reopened");
            $this->assertSame($driver->id, (int) DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('assigned_driver_id'));
        }
    }

    public function test_a_driver_can_hand_back_a_load_before_starting_it(): void
    {
        $load = $this->load();
        $driver = $this->driver('handback');
        $this->postJson($this->d($driver, self::DRIVER_API."/load-board/{$load}/accept"))->assertOk();

        $this->postJson($this->d($driver, self::DRIVER_API."/active-jobs/{$load}/cancel"))->assertOk();

        $this->assertSame('available', $this->loadStatus($load));
        $this->assertNull(DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('assigned_driver_id'));
        $this->assertSame(0, (int) $driver->fresh()->current_orders);
    }

    public function test_the_status_endpoint_cannot_cancel_and_only_the_assigned_driver_can_use_it(): void
    {
        $owner = $this->driver('owner');
        $intruder = $this->driver('intruder');
        $load = $this->load(['status' => 'assigned', 'assigned_driver_id' => $owner->id]);

        $this->postJson($this->d($owner, self::LOADS."/loads/{$load}/status"), ['status' => 'cancelled'])->assertStatus(422);
        $this->postJson($this->d($intruder, self::LOADS."/loads/{$load}/status"), ['status' => 'in_transit'])->assertNotFound();
        $this->assertSame('assigned', $this->loadStatus($load));

        $this->postJson($this->d($owner, self::LOADS."/loads/{$load}/status"), ['status' => 'in_transit'])->assertOk();
        $this->assertSame('in_transit', $this->loadStatus($load));
        // skipping a step is refused
        $this->postJson($this->d($owner, self::LOADS."/loads/{$load}/status"), ['status' => 'delivered'])->assertStatus(409);
    }

    // =========================================================== OTR: concurrency

    public function test_a_load_cannot_be_assigned_twice_even_if_the_check_races(): void
    {
        $load = $this->load();
        $first = $this->driver('first');
        $second = $this->driver('second');
        $service = app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class);

        // Simulate the second caller having read the load while it was still available: the stale
        // object passes the early check, but the atomic claim must still refuse it.
        $this->assertNotNull($service->acceptLoad($load, $first->id, null, true));
        DB::table('urban_goodz_load_board_loads')->where('id', $load)->update(['status' => 'available']); // stale read window
        DB::table('urban_goodz_load_board_loads')->where('id', $load)->update(['assigned_driver_id' => $first->id]);

        $this->assertNull($service->acceptLoad($load, $second->id, null, true), 'a load that already has an assignee was assigned again');
        $this->assertSame($first->id, (int) DB::table('urban_goodz_load_board_loads')->where('id', $load)->value('assigned_driver_id'));
    }

    // ========================================================== MEDICAL: access

    public function test_only_trained_approved_drivers_can_work_medical_jobs(): void
    {
        $job = $this->job();
        $untrained = $this->driver('untrained');
        $suspended = $this->medicalDriver('susp');
        $suspended->update(['application_status' => 'denied']);

        foreach ([$untrained, $suspended] as $driver) {
            $status = $this->getJson($this->d($driver, self::MEDICAL.'/jobs'))->getStatusCode();
            $this->assertContains($status, [401, 403], 'an unqualified driver listed medical jobs');
            $this->assertContains($this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/accept"))->getStatusCode(), [401, 403]);
        }
        $this->assertSame('pending', DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('status'));

        $this->getJson($this->d($this->medicalDriver(), self::MEDICAL.'/jobs'))->assertOk()->assertSee('City Lab');
    }

    public function test_accepting_uses_the_authenticated_driver_not_a_driver_id_from_the_body(): void
    {
        $job = $this->job();
        $me = $this->medicalDriver('me');
        $victim = $this->medicalDriver('victim');

        $this->postJson($this->d($me, self::MEDICAL."/jobs/{$job}/accept"), ['driver_id' => $victim->id])->assertOk();

        $this->assertSame($me->id, (int) DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('assigned_driver_id'));
        $this->postJson($this->d($victim, self::MEDICAL."/jobs/{$job}/accept"))->assertStatus(409);
    }

    public function test_specimen_job_details_are_limited_until_the_job_is_yours(): void
    {
        $job = $this->job();
        $me = $this->medicalDriver('me');
        $other = $this->medicalDriver('other');

        $pending = $this->getJson($this->d($other, self::MEDICAL."/jobs/{$job}"))->assertOk()->getContent();
        foreach (['SECRET-CONTACT', 'SECRET-RECIPIENT', '555-0111', 'SECRET-ADMIN-NOTE'] as $leak) {
            $this->assertStringNotContainsString($leak, $pending, "an unassigned job exposes {$leak}");
        }

        $this->postJson($this->d($me, self::MEDICAL."/jobs/{$job}/accept"))->assertOk();
        $mine = $this->getJson($this->d($me, self::MEDICAL."/jobs/{$job}"))->assertOk()->getContent();
        $this->assertStringContainsString('SECRET-CONTACT', $mine);
        $this->assertStringNotContainsString('SECRET-ADMIN-NOTE', $mine);

        $this->getJson($this->d($other, self::MEDICAL."/jobs/{$job}"))->assertNotFound();
    }

    // ====================================================== MEDICAL: custody + money

    public function test_a_specimen_cannot_be_marked_delivered_without_naming_who_received_it(): void
    {
        $job = $this->job();
        $driver = $this->medicalDriver('pod');
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/accept"))->assertOk();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'picked_up'])->assertOk();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'in_transit'])->assertOk();

        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'delivered'])->assertStatus(422);
        $this->assertSame('in_transit', DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('status'), 'no proof, no delivery');
        $this->assertSame(0, DB::table('urban_goodz_driver_earnings')->where('delivery_man_id', $driver->id)->count(), 'and no payout');

        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'delivered', 'recipient_name' => 'Dr. Patel, Lab 3'])->assertOk();
        $entry = DB::table('urban_goodz_medical_courier_custody_logs')->where('job_id', $job)->where('action', 'delivered')->first();
        $this->assertStringContainsString('Received by: Dr. Patel, Lab 3', (string) $entry->notes, 'the receiver is part of the custody trail');
    }

    public function test_a_full_run_records_a_custody_trail_under_the_real_driver_and_pays_exactly_once(): void
    {
        $job = $this->job();
        $driver = $this->medicalDriver('runner');

        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/accept"))->assertOk();
        // a step cannot be skipped
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'delivered', 'recipient_name' => 'Lab Front Desk'])->assertStatus(409);

        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'picked_up'])->assertOk();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/custody"), ['action' => 'temperature_check', 'notes' => '4C', 'handler_name' => 'FORGED NAME'])->assertOk();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'in_transit'])->assertOk();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'delivered', 'recipient_name' => 'Lab Front Desk'])->assertOk();

        $log = DB::table('urban_goodz_medical_courier_custody_logs')->where('job_id', $job)->orderBy('id')->get();
        $this->assertSame(['assigned', 'picked_up', 'temperature_check', 'in_transit', 'delivered'], $log->pluck('action')->all());
        foreach ($log as $entry) {
            $this->assertSame($driver->id, (int) $entry->handler_id, "custody entry '{$entry->action}' is not attributed to the driver");
            $this->assertSame('driver', $entry->handler_role);
            $this->assertStringNotContainsString('FORGED', (string) $entry->handler_name);
        }

        $this->assertSame(1, DB::table('urban_goodz_driver_earnings')->where('delivery_man_id', $driver->id)->where('earning_type', 'medical_courier')->count(), 'delivery must pay exactly once');

        // a repeated "delivered" is refused and cannot pay again
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'delivered', 'recipient_name' => 'Lab Front Desk'])->assertStatus(409);
        $this->assertSame(1, DB::table('urban_goodz_driver_earnings')->where('delivery_man_id', $driver->id)->where('earning_type', 'medical_courier')->count());
    }

    public function test_only_the_assigned_driver_can_change_status_or_write_custody(): void
    {
        $job = $this->job();
        $owner = $this->medicalDriver('owner');
        $other = $this->medicalDriver('other');
        $this->postJson($this->d($owner, self::MEDICAL."/jobs/{$job}/accept"))->assertOk();
        $before = DB::table('urban_goodz_medical_courier_custody_logs')->where('job_id', $job)->count();

        $this->postJson($this->d($other, self::MEDICAL."/jobs/{$job}/status"), ['status' => 'picked_up'])->assertNotFound();
        $this->postJson($this->d($other, self::MEDICAL."/jobs/{$job}/custody"), ['action' => 'handoff'])->assertNotFound();

        $this->assertSame('assigned', DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('status'));
        $this->assertSame($before, DB::table('urban_goodz_medical_courier_custody_logs')->where('job_id', $job)->count(), 'a custody entry was written by a driver who does not hold the specimen');
    }

    public function test_custody_cannot_be_written_for_pending_or_finished_jobs_and_a_driver_cannot_cancel(): void
    {
        $driver = $this->medicalDriver('d');
        $pending = $this->job();
        $delivered = $this->job(['status' => 'delivered', 'assigned_driver_id' => $driver->id]);
        $mine = $this->job(['status' => 'in_transit', 'assigned_driver_id' => $driver->id]);

        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$pending}/custody"), ['action' => 'x'])->assertNotFound();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$delivered}/custody"), ['action' => 'x'])->assertNotFound();
        $this->postJson($this->d($driver, self::MEDICAL."/jobs/{$mine}/status"), ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame('in_transit', DB::table('urban_goodz_medical_courier_jobs')->where('id', $mine)->value('status'));
    }

    public function test_two_drivers_cannot_both_accept_the_same_specimen_job(): void
    {
        $job = $this->job();
        $a = $this->medicalDriver('a');
        $b = $this->medicalDriver('b');
        $service = app(\App\Services\UrbanGoodz\UrbanGoodzMedicalCourierService::class);

        $this->assertNotNull($service->assignDriver($job, $a->id));
        // simulate the stale read that passed the pending check just before the first claim committed
        $this->assertNull($service->assignDriver($job, $b->id));
        DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->update(['status' => 'pending']);
        $this->assertNull($service->assignDriver($job, $b->id), 'a job that already has a driver was assigned again');
        $this->assertSame($a->id, (int) DB::table('urban_goodz_medical_courier_jobs')->where('id', $job)->value('assigned_driver_id'));
    }
}
