<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzNotification;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * stranded:dispatch-tick and delivery-history:prune were never on the live schedule (the schedule file was not
 * read), so the first production run meets months of accumulated data. It must not message people about old
 * emergencies, punish responders for offers they never had a fair chance to answer, or issue one giant DELETE.
 */
class FirstRunOfRevivedJobsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (UrbanGoodzStrandedService::count() === 0) {
            $this->seed(\Database\Seeders\UrbanGoodzStrandedServiceSeeder::class);
        }
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function user(string $t): User
    {
        return User::create(['f_name' => 'Tick', 'l_name' => $t, 'email' => "tick-{$t}-".Str::random(8).'@urbangoodz.test', 'phone' => '1'.random_int(1000000000, 1999999999), 'password' => bcrypt('x')]);
    }

    private function request(User $u, string $status, int $hoursOld): UrbanGoodzStrandedRequest
    {
        $service = UrbanGoodzStrandedService::where('slug', 'dead-battery')->firstOrFail();
        $r = UrbanGoodzStrandedRequest::create([
            'uuid' => (string) Str::uuid(), 'request_number' => 'ST-T-'.strtoupper(Str::random(6)), 'user_id' => $u->id, 'service_id' => $service->id, 'service_slug' => $service->slug,
            'status' => $status, 'latitude' => 40.758, 'longitude' => -73.9855, 'allow_samaritans' => true, 'help_request_fee_minor' => 500, 'help_request_fee_status' => 'paid',
            'currency' => 'USD', 'broadcast_radius_miles' => 5, 'broadcast_at' => now()->subHours($hoursOld), 'broadcast_expires_at' => now()->subHours($hoursOld)->addMinutes(2),
            'escalation_due_at' => now()->subHours($hoursOld)->addMinutes(10),
        ]);
        UrbanGoodzStrandedRequest::whereKey($r->id)->update(['created_at' => now()->subHours($hoursOld)]);

        return $r->fresh();
    }

    public function test_requests_stuck_for_days_are_closed_quietly_not_widened_or_announced(): void
    {
        $stale = $this->request($this->user('stale'), 'broadcasting', 72);
        $stuckNoResponders = $this->request($this->user('stuck'), 'no_responders', 30);
        $fresh = $this->request($this->user('fresh'), 'broadcasting', 0);

        $before = UrbanGoodzNotification::count() + DB::table('user_notifications')->count();
        Artisan::call('stranded:dispatch-tick');

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('expired', $stuckNoResponders->fresh()->status);
        $this->assertNotSame('expired', $fresh->fresh()->status, 'a live request must still be worked');
        $this->assertNull($stale->fresh()->escalated_at, 'an old emergency must not be escalated now');
        $this->assertSame($before, UrbanGoodzNotification::count() + DB::table('user_notifications')->count() - $this->noticesAbout($fresh), 'nobody is messaged about the stale ones');
    }

    /** Notices the genuinely live request may legitimately cause. */
    private function noticesAbout(UrbanGoodzStrandedRequest $r): int
    {
        return UrbanGoodzNotification::where('data', 'like', '%'.$r->request_number.'%')->count()
            + DB::table('user_notifications')->where('data', 'like', '%'.$r->request_number.'%')->count()
            + DB::table('user_notifications')->where('user_id', $r->user_id)->count();
    }

    public function test_only_recently_lapsed_offers_count_as_missed_jobs(): void
    {
        $oldHelper = $this->user('oldhelper');
        $newHelper = $this->user('newhelper');
        foreach ([$oldHelper, $newHelper] as $h) {
            UrbanGoodzStrandedResponder::create(['user_id' => $h->id, 'responder_type' => 'samaritan', 'is_online' => true, 'max_travel_miles' => 25, 'missed_jobs' => 0]);
        }
        $request = $this->request($this->user('cust'), 'awaiting_selection', 0);
        $offer = fn (User $h, int $minutesAgo) => UrbanGoodzStrandedOffer::create([
            'request_id' => $request->id, 'responder_id' => $h->id, 'responder_type' => 'samaritan', 'status' => 'offered', 'expires_at' => now()->subMinutes($minutesAgo),
        ]);
        $offer($oldHelper, 60 * 24 * 10);   // lapsed ten days ago, before the tick ever ran
        $offer($newHelper, 1);              // lapsed a minute ago

        Artisan::call('stranded:dispatch-tick');

        $this->assertSame(0, (int) UrbanGoodzStrandedResponder::where('user_id', $oldHelper->id)->value('missed_jobs'), 'no penalty for a lapse nobody was watching');
        $this->assertSame(1, (int) UrbanGoodzStrandedResponder::where('user_id', $newHelper->id)->value('missed_jobs'));
        $this->assertSame(2, UrbanGoodzStrandedOffer::where('request_id', $request->id)->where('status', 'expired')->count(), 'both are still closed');
    }

    public function test_pruning_a_large_backlog_works_in_batches_and_keeps_each_drivers_latest_point(): void
    {
        $driver = DB::table('delivery_men')->insertGetId(array_merge(['f_name' => 'P', 'l_name' => 'D', 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'p-'.uniqid().'@urbangoodz.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()], $this->requiredDriverColumns()));
        $old = now()->subDays(40);
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = ['delivery_man_id' => $driver, 'order_id' => null, 'time' => $old, 'latitude' => '1', 'longitude' => '1', 'created_at' => $old, 'updated_at' => $old];
        }
        DB::table('delivery_histories')->insert($rows);
        $keepId = DB::table('delivery_histories')->where('delivery_man_id', $driver)->max('id');

        // A run with a small per-run cap removes only part of the backlog (the rest goes on the next night)...
        Artisan::call('delivery-history:prune', ['--chunk' => 100, '--max' => 100]);
        $left = DB::table('delivery_histories')->where('delivery_man_id', $driver)->count();

        // ...and a normal run finishes the job.
        Artisan::call('delivery-history:prune');
        $this->assertSame([$keepId], DB::table('delivery_histories')->where('delivery_man_id', $driver)->pluck('id')->all(), 'only the latest point survives, whatever its age');
        $this->assertLessThanOrEqual(25, $left);
    }

    private function requiredDriverColumns(): array
    {
        $extra = [];
        foreach (\Illuminate\Support\Facades\Schema::getColumns('delivery_men') as $col) {
            if (! $col['nullable'] && $col['default'] === null && ! $col['auto_increment']
                && ! in_array($col['name'], ['f_name', 'l_name', 'phone', 'email', 'password', 'created_at', 'updated_at'], true)) {
                $extra[$col['name']] = str_contains(strtolower($col['type_name']), 'int') || in_array(strtolower($col['type_name']), ['decimal', 'float', 'double'], true) ? 0 : 'x';
            }
        }

        return $extra;
    }
}
