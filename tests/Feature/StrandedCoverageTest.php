<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Services\StrandedCoverageService;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StrandedCoverageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('urban_goodz_stranded_offers')->delete();
        DB::table('urban_goodz_stranded_requests')->delete();
        DB::table('urban_goodz_stranded_responders')->delete();
        DB::table('urban_goodz_stranded_nearby_presence')->delete();
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function req(float $lat, float $lng, string $status, ?int $offer = null, ?string $created = null): int
    {
        return DB::table('urban_goodz_stranded_requests')->insertGetId([
            'uuid' => (string) Str::uuid(), 'request_number' => 'ST-COV-'.strtoupper(Str::random(6)), 'user_id' => 1, 'service_id' => 1, 'service_slug' => 'dead-battery',
            'status' => $status, 'latitude' => $lat, 'longitude' => $lng, 'selected_offer_id' => $offer, 'currency' => 'USD', 'allow_samaritans' => 1,
            'created_at' => $created ?? now()->subHours(2), 'updated_at' => now(),
        ]);
    }

    public function test_it_finds_the_area_where_people_are_asking_and_nobody_answers(): void
    {
        // A well-served area: 3 requests, all answered, a helper online. A gap: 3 requests, none answered, nobody there.
        foreach (range(1, 3) as $i) {
            $this->req(40.71, -74.01, 'completed', 9000 + $i);
        }
        DB::table('urban_goodz_stranded_responders')->insert(['user_id' => 5, 'responder_type' => 'samaritan', 'is_online' => 1, 'last_latitude' => 40.71, 'last_longitude' => -74.01, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(1, 3) as $i) {
            $this->req(41.31, -73.21, 'no_responders');
        }

        $r = app(StrandedCoverageService::class)->analyze(30);

        $this->assertSame(6, $r['summary']['requests']);
        $this->assertEquals(50, $r['summary']['answer_rate']);
        $this->assertSame(1, $r['summary']['online_helpers_now']);
        $top = $r['cells'][0];
        $this->assertSame(3, $top['unanswered'], 'the worst gap is listed first');
        $this->assertEqualsWithDelta(41.35, $top['lat'], 0.06);
        $this->assertGreaterThan(0, $top['priority']);
        $served = collect($r['cells'])->firstWhere('answered', 3);
        $this->assertSame(0, $served['priority'], 'a served area is not a gap');
    }

    public function test_nearby_opted_in_neighbours_count_as_supply_and_old_requests_are_ignored(): void
    {
        $this->req(40.71, -74.01, 'no_responders');
        $this->req(40.71, -74.01, 'no_responders', null, now()->subDays(60)->toDateTimeString());
        DB::table('urban_goodz_stranded_nearby_presence')->insert(['user_id' => 77, 'latitude' => 40.71, 'longitude' => -74.01, 'reported_at' => now(), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

        $r = app(StrandedCoverageService::class)->analyze(30);

        $this->assertSame(1, $r['summary']['requests'], 'the 60-day-old request is outside the window');
        $this->assertSame(1, $r['summary']['opted_in_neighbours_now']);
        $this->assertSame(3, $r['cells'][0]['priority'], 'unanswered but a neighbour is nearby: unanswered weight only, no "no supply" bonus');
    }

    public function test_the_admin_page_loads_and_shows_the_gap(): void
    {
        $this->req(41.31, -73.21, 'no_responders');
        $admin = Admin::create(['f_name' => 'C', 'l_name' => 'Ov', 'email' => 'cov-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1, 'is_logged_in' => 1, 'login_remember_token' => 'cov-token']);

        $this->actingAs($admin, 'admin')->withSession(['login_remember_token' => 'cov-token'])->get('/admin/urban-goodz/stranded/coverage')
            ->assertOk()->assertSee('Coverage')->assertSee('Gap areas');
    }

    public function test_monique_flags_unanswered_requests_for_recruiting(): void
    {
        config(['urban_goodz_ai.monique_proactive.email_alerts' => false]);
        $this->req(41.31, -73.21, 'no_responders', null, now()->subHours(3)->toDateTimeString());
        $admin = Admin::create(['f_name' => 'C', 'l_name' => 'Ov', 'email' => 'cov2-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'role_id' => 1]);

        app(MoniqueProactiveAttentionService::class)->observeAndAct('admin', $admin->id);

        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'stranded_coverage_gap')->first();
        $this->assertNotNull($note);
        $this->assertNotContains('let_monique_handle_it', array_column((array) $note->actions, 'action'), 'it needs recruiting, not a button');
    }
}
