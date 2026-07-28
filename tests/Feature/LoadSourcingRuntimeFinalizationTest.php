<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLoadSourcingSearch;
use App\Models\DispatcherSavedSearch;
use App\Models\ExternalLoad;
use App\Models\LoadSource;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\LoadSource\ExternalLoadPublisher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class LoadSourcingRuntimeFinalizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_runtime_fields_belong_to_board_table_not_external_loads(): void
    {
        $this->assertTrue(Schema::hasColumns('urban_goodz_load_board_loads', [
            'rate_per_mile',
            'assigned_driver_id',
        ]));
        $this->assertTrue(Schema::hasColumn('external_loads', 'rate_per_loaded_mile'));
        $this->assertFalse(Schema::hasColumn('external_loads', 'rate_per_mile'));
        $this->assertFalse(Schema::hasColumn('external_loads', 'assigned_driver_id'));

        $external = new ExternalLoad();
        $this->assertFalse(method_exists($external, 'assignedDriver'));

        $board = new UrbanGoodzLoadBoardLoad();
        $this->assertTrue(method_exists($board, 'assignedDriver'));
    }

    public function test_publish_maps_source_schema_to_board_schema_exactly(): void
    {
        $external = $this->makeExternalLoad([
            'origin_latitude' => '32.7767000',
            'origin_longitude' => '-96.7970000',
            'destination_latitude' => '29.7604000',
            'destination_longitude' => '-95.3698000',
            'distance_loaded' => '241.75',
            'gross_rate' => '725.25',
            'rate_per_loaded_mile' => '3.0000',
            'estimated_driver_net' => '612.44',
            'estimated_platform_fee' => '87.03',
            'weight' => '18000.00',
            'commodity' => 'Packaged goods',
        ]);

        $result = resolve(ExternalLoadPublisher::class)->publish($external);
        $board = $result['load']->fresh();

        $this->assertFalse($result['already_published']);
        $this->assertSame($external->fingerprint, $board->fingerprint);
        $this->assertSame($external->external_id, $board->external_id);
        $this->assertSame($external->source_id, $board->source_id);
        $this->assertEquals(241.75, $board->distance_miles);
        $this->assertSame('725.25', $board->payout_amount);
        $this->assertSame('3.00', $board->rate_per_mile);
        $this->assertSame('612.44', $board->driver_payout_amount);
        $this->assertSame('87.03', $board->processing_fee);
        $this->assertEquals(18000.00, $board->weight_lbs);
        $this->assertSame('Packaged goods', $board->commodity_description);
        $this->assertSame('booked', $external->fresh()->status);
        $this->assertSame(1, UrbanGoodzLoadBoardLoad::where('fingerprint', $external->fingerprint)->count());
    }

    public function test_publish_replay_returns_existing_board_load_without_duplicate(): void
    {
        $external = $this->makeExternalLoad();
        $publisher = resolve(ExternalLoadPublisher::class);

        $first = $publisher->publish($external);
        $second = $publisher->publish($external->fresh());

        $this->assertFalse($first['already_published']);
        $this->assertTrue($second['already_published']);
        $this->assertSame($first['load']->id, $second['load']->id);
        $this->assertSame(1, UrbanGoodzLoadBoardLoad::where('fingerprint', $external->fingerprint)->count());
    }

    public function test_fingerprint_collision_with_conflicting_identity_fails_closed(): void
    {
        $external = $this->makeExternalLoad();
        UrbanGoodzLoadBoardLoad::create([
            'external_id' => 'different-external-id',
            'provider' => 'manual_import',
            'source_id' => $external->source_id,
            'fingerprint' => $external->fingerprint,
            'status' => 'available',
            'payout_amount' => 100,
        ]);

        try {
            resolve(ExternalLoadPublisher::class)->publish($external);
            $this->fail('A conflicting fingerprint must not be treated as an existing publication.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('fingerprint collision', $exception->getMessage());
        }

        $this->assertSame('available', $external->fresh()->status);
        $this->assertSame(1, UrbanGoodzLoadBoardLoad::where('fingerprint', $external->fingerprint)->count());
    }

    public function test_overview_never_queries_board_only_columns_on_external_loads(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $controller = resolve(\App\Http\Controllers\Admin\UrbanGoodz\UrbanGoodzLoadSourcingController::class);
        $method = new \ReflectionMethod($controller, 'loadOverviewStats');
        $method->setAccessible(true);
        $stats = $method->invoke($controller);

        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));

        $this->assertStringNotContainsString('external_loads`.`rate_per_mile', $sql);
        $this->assertStringNotContainsString('external_loads`.`assigned_driver_id', $sql);
        $this->assertStringContainsString('rate_per_loaded_mile', $sql);
        $this->assertArrayHasKey('assigned', $stats);
        $this->assertArrayHasKey('unassigned_count', $stats);
    }

    public function test_business_sourcing_page_uses_canonical_sources_without_credentials(): void
    {
        $this->withoutMiddleware();
        [$client, $owner] = $this->makeBusinessUser('owner_admin');
        $this->makeExternalLoad();

        $response = $this->actingAs($owner, 'business')
            ->get(route('business.ai-logistics.load-sourcing.index'));

        $response->assertOk();
        $response->assertViewHas('sources', function ($sources): bool {
            $source = $sources->first();

            return $source !== null
                && !array_key_exists('encrypted_value', $source->getAttributes());
        });
        $response->assertViewHas('externalLoads');
    }

    public function test_business_sourcing_rejects_user_without_permission(): void
    {
        $this->withoutMiddleware();
        [, $restricted] = $this->makeBusinessUser('read_only_viewer');

        $this->actingAs($restricted, 'business')
            ->get(route('business.ai-logistics.load-sourcing.index'))
            ->assertForbidden();
    }

    public function test_scheduler_dispatches_due_saved_search_to_sourcing_queue(): void
    {
        Queue::fake();
        [, $owner] = $this->makeBusinessUser('owner_admin');

        DispatcherSavedSearch::create([
            'business_client_user_id' => $owner->id,
            'dispatch_company_id' => $owner->business_client_id,
            'name' => 'Due runtime search',
            'criteria' => ['origin_state' => 'TX'],
            'auto_alert' => true,
            'last_run_at' => null,
        ]);

        $this->artisan('run-scheduled-sourcing')->assertSuccessful();

        Queue::assertPushed(ExecuteLoadSourcingSearch::class, function ($job): bool {
            return $job->criteria === ['origin_state' => 'TX']
                && $job->userType === 'business_client';
        });
    }

    private function makeExternalLoad(array $overrides = []): ExternalLoad
    {
        $source = LoadSource::create([
            'source_key' => 'runtime_' . bin2hex(random_bytes(6)),
            'name' => 'Runtime Test Source',
            'type' => 'manual',
            'enabled' => true,
            'api_status' => 'connected',
            'partnership_status' => 'active',
        ]);

        $attributes = array_merge([
            'source_id' => $source->id,
            'external_id' => 'ext-' . bin2hex(random_bytes(6)),
            'fingerprint' => hash('sha256', random_bytes(16)),
            'origin_address' => '100 Main St',
            'origin_city' => 'Dallas',
            'origin_state' => 'TX',
            'destination_address' => '200 Market St',
            'destination_city' => 'Houston',
            'destination_state' => 'TX',
            'equipment_type' => 'dry_van',
            'distance_loaded' => '240.00',
            'gross_rate' => '600.00',
            'rate_per_loaded_mile' => '2.5000',
            'status' => 'available',
            'compliance_status' => 'authorized_partner',
            'is_duplicate' => false,
        ], $overrides);

        return ExternalLoad::create($attributes);
    }

    /**
     * @return array{UrbanGoodzBusinessClient, UrbanGoodzBusinessClientUser}
     */
    private function makeBusinessUser(string $role): array
    {
        $suffix = bin2hex(random_bytes(5));
        $client = UrbanGoodzBusinessClient::create([
            'company_name' => 'Runtime Client ' . $suffix,
            'email' => "runtime-client-{$suffix}@example.test",
            'status' => 'approved',
            'account_type' => 'business',
        ]);
        $user = UrbanGoodzBusinessClientUser::create([
            'business_client_id' => $client->id,
            'first_name' => 'Runtime',
            'last_name' => 'Owner',
            'email' => "runtime-owner-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => [],
            'is_active' => true,
            'status' => 'active',
        ]);

        return [$client, $user];
    }
}
