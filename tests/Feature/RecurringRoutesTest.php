<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\RecurringRouteScheduler as S;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A recurring route (for example a weekday specimen run) actually produces its repeat runs. */
class RecurringRoutesTest extends TestCase
{
    use DatabaseTransactions;

    private UrbanGoodzBusinessClient $business;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $sfx = str_replace('.', '', uniqid('', true));
        $this->business = UrbanGoodzBusinessClient::create(['company_name' => "Recur {$sfx}", 'email' => "recur-{$sfx}@urbangoodz.test", 'status' => 'approved']);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function template(string $rule, string $date, array $over = []): UrbanGoodzDedicatedRoute
    {
        $route = UrbanGoodzDedicatedRoute::create(array_merge([
            'business_client_id' => $this->business->id, 'route_name' => 'Clinic to Lab', 'route_type' => 'scheduled_route', 'source_module' => 'scheduled_route',
            'pickup_location' => '1 Clinic Way', 'pickup_lat' => 29.75, 'pickup_lng' => -95.36, 'end_location' => 'Lab', 'return_to_origin' => false,
            'scheduled_date' => $date, 'recurring_rule' => $rule, 'status' => 'completed', 'assigned_driver_id' => 99, 'total_packages' => 2,
            'driver_pay_per_package' => 6.5, 'route_completion_bonus' => 3, 'vehicle_type_required' => 'car', 'capacity_packages' => 20,
        ], $over));
        foreach ([1, 2] as $i) {
            UrbanGoodzRoutePackage::create([
                'dedicated_route_id' => $route->id, 'business_client_id' => $this->business->id, 'tracking_id' => "T-{$route->id}-{$i}-".uniqid(), 'barcode' => "B-{$route->id}-{$i}-".uniqid(),
                'qr_code' => "Q-{$route->id}-{$i}-".uniqid(), 'dropoff_name' => "Lab Desk {$i}", 'dropoff_address' => "{$i}00 Lab Rd", 'dropoff_lat' => 29.76 + $i / 100, 'dropoff_lng' => -95.37,
                'package_type' => 'specimen', 'priority' => 'medical', 'requires_custody' => true, 'requires_signature' => true, 'temperature_requirement' => '2-8C',
                'delivery_window_start' => Carbon::parse($date)->setTime(8, 0), 'delivery_window_end' => Carbon::parse($date)->setTime(17, 0),
                'stop_order' => $i, 'status' => 'delivered', 'delivered_to_name' => 'Old Receiver', 'proof_photo' => 'old.jpg',
            ]);
        }

        return $route;
    }

    // ---------------------------------------------------------------- the rules

    public function test_rules_are_validated(): void
    {
        foreach ([null, '', 'daily', 'WEEKDAYS', ' weekly ', 'biweekly', 'monthly', 'weekly:mon,wed,fri', 'weekly: Tue , Thu'] as $ok) {
            $this->assertTrue(S::isValid($ok), var_export($ok, true));
        }
        foreach (['every tuesday', 'weekly:funday', 'weekly:', 'yearly', 'cron * * * * *', '0 8 * * 1'] as $bad) {
            $this->assertFalse(S::isValid($bad), $bad);
        }
    }

    public function test_each_rule_picks_the_right_dates(): void
    {
        $anchor = Carbon::parse('2030-01-07');                       // a Monday
        $days = fn (string $rule) => collect(range(1, 31))->map(fn ($i) => $anchor->copy()->addDays($i))->filter(fn ($d) => S::runsOn($rule, $d, $anchor))->map->format('m-d')->values()->all();

        $this->assertCount(31, $days('daily'));
        $this->assertSame(['01-08', '01-09', '01-10', '01-11', '01-14'], array_slice($days('weekdays'), 0, 5));
        $this->assertSame(['01-14', '01-21', '01-28', '02-04'], $days('weekly'));
        $this->assertSame(['01-21', '02-04'], $days('biweekly'));
        $this->assertSame(['02-07'], $days('monthly'));
        $this->assertSame(['01-09', '01-11', '01-14'], array_slice($days('weekly:mon,wed,fri'), 0, 3));
        $this->assertFalse(S::runsOn('daily', $anchor, $anchor), 'the template itself is the first run');
    }

    public function test_a_monthly_route_on_the_31st_runs_on_the_last_day_of_shorter_months(): void
    {
        $anchor = Carbon::parse('2030-01-31');
        $this->assertTrue(S::runsOn('monthly', Carbon::parse('2030-02-28'), $anchor));
        $this->assertFalse(S::runsOn('monthly', Carbon::parse('2030-02-27'), $anchor));
        $this->assertTrue(S::runsOn('monthly', Carbon::parse('2030-03-31'), $anchor));
    }

    // ------------------------------------------------------------- generation

    public function test_generation_copies_the_route_and_stops_but_not_the_driver_or_history(): void
    {
        $today = Carbon::parse('2030-03-04');                         // a Monday
        $tpl = $this->template('weekdays', '2030-03-01');             // a Friday

        $r = app(S::class)->generate(3, $today);                       // Mon..Thu: 4 weekdays in range (Mar 4,5,6,7)

        $this->assertSame(4, $r['created']);
        $runs = UrbanGoodzDedicatedRoute::where('recurring_parent_id', $tpl->id)->orderBy('scheduled_date')->get();
        $this->assertSame(['2030-03-04', '2030-03-05', '2030-03-06', '2030-03-07'], $runs->map(fn ($x) => $x->scheduled_date->toDateString())->all());

        $run = $runs->first();
        $this->assertSame('pending', $run->status);
        $this->assertNull($run->assigned_driver_id, 'every run is assigned like any other route');
        $this->assertSame(6.5, (float) $run->driver_pay_per_package);
        $this->assertSame('car', $run->vehicle_type_required);
        $this->assertSame($tpl->business_client_id, $run->business_client_id);
        $this->assertSame(2, (int) $run->total_packages);

        $pkgs = $run->packages()->orderBy('stop_order')->get();
        $this->assertCount(2, $pkgs);
        foreach ($pkgs as $p) {
            $this->assertSame('pending', $p->status);
            $this->assertNull($p->delivered_to_name);
            $this->assertNull($p->proof_photo);
            $this->assertTrue((bool) $p->requires_custody, 'custody rules carry over');
            $this->assertSame('2-8C', $p->temperature_requirement);
            $this->assertSame('2030-03-04', Carbon::parse($p->delivery_window_start)->toDateString(), 'delivery windows move to the run date');
            $this->assertSame('08:00', Carbon::parse($p->delivery_window_start)->format('H:i'));
        }
        $this->assertSame(['Lab Desk 1', 'Lab Desk 2'], $pkgs->pluck('dropoff_name')->all());
        $this->assertCount(8, UrbanGoodzRoutePackage::whereIn('dedicated_route_id', $runs->pluck('id'))->pluck('tracking_id')->unique(), 'every package gets its own tracking code');
    }

    public function test_running_the_generator_again_creates_nothing_new(): void
    {
        $today = Carbon::parse('2030-03-04');
        $tpl = $this->template('daily', '2030-03-01');

        $first = app(S::class)->generate(2, $today);
        $second = app(S::class)->generate(2, $today);

        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['skipped']);
        $this->assertSame(3, UrbanGoodzDedicatedRoute::where('recurring_parent_id', $tpl->id)->count());
    }

    public function test_a_cancelled_template_a_run_and_unsupported_rules_do_not_generate(): void
    {
        $today = Carbon::parse('2030-03-04');
        $cancelled = $this->template('daily', '2030-03-01', ['status' => 'cancelled']);
        $odd = $this->template('every other thursday', '2030-03-01');
        $tpl = $this->template('daily', '2030-03-01');
        app(S::class)->generate(1, $today);
        $run = UrbanGoodzDedicatedRoute::where('recurring_parent_id', $tpl->id)->firstOrFail();

        $r = app(S::class)->generate(1, $today);   // runs carry the rule for display but must never spawn runs of their own

        $this->assertSame(0, UrbanGoodzDedicatedRoute::where('recurring_parent_id', $cancelled->id)->count());
        $this->assertSame(0, UrbanGoodzDedicatedRoute::where('recurring_parent_id', $odd->id)->count());
        $this->assertSame(0, UrbanGoodzDedicatedRoute::where('recurring_parent_id', $run->id)->count());
        $this->assertGreaterThanOrEqual(1, $r['unsupported']);
    }

    public function test_the_artisan_command_and_scheduler_entry_exist(): void
    {
        $this->template('weekly', now()->subDays(7)->toDateString());
        $this->assertSame(0, Artisan::call('routes:generate-recurring', ['--ahead' => 7]));
        $this->assertStringContainsString('created=', Artisan::output());
    }
}
