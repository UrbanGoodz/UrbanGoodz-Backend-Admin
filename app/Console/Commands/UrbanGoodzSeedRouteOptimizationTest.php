<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\DedicatedRouteOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Builds a dedicated route whose stops are loaded in a deliberately terrible
 * order, then optimises it, so the route optimiser can be seen to work on
 * real geography rather than trusted on faith.
 *
 * The stops zig-zag across greater Houston -- far west, back downtown, south
 * west, back downtown, far north, back downtown. Any optimiser worth the name
 * has to cluster the three central stops together and visit the outlying ones
 * in a sensible sweep, so the reordering is obvious rather than marginal.
 *
 *   php artisan urban-goodz:route-optimization-test
 *   php artisan urban-goodz:route-optimization-test --return-to-origin
 *   php artisan urban-goodz:route-optimization-test --end=katy
 *   php artisan urban-goodz:route-optimization-test --fresh
 */
class UrbanGoodzSeedRouteOptimizationTest extends Command
{
    protected $signature = 'urban-goodz:route-optimization-test
                            {--client=1 : Business client id to attach the route to}
                            {--return-to-origin : Finish back at the pickup point}
                            {--end= : Finish at a named stop (katy, sugarland, woodlands) instead}
                            {--fresh : Remove routes from previous runs first}
                            {--no-optimize : Build the route but do not optimise it}';

    protected $description = 'Seed a Houston dedicated route with deliberately mis-ordered stops and optimise it';

    /** Real greater-Houston coordinates. */
    private const PICKUP = ['label' => 'Urban Goodz Hub, Downtown', 'lat' => 29.7604, 'lng' => -95.3698];

    /**
     * Loaded in this order on purpose. It bounces between the far edges of the
     * metro and the centre, which is exactly the pattern a route optimiser
     * exists to fix.
     */
    private const STOPS = [
        ['key' => 'katy',      'name' => 'Katy Mills Area',      'address' => '5000 Katy Mills Cir, Katy, TX',        'lat' => 29.7858, 'lng' => -95.8244],
        ['key' => 'downtown',  'name' => 'Downtown Office',       'address' => '1000 Louisiana St, Houston, TX',       'lat' => 29.7589, 'lng' => -95.3677],
        ['key' => 'sugarland', 'name' => 'Sugar Land Town Sq',    'address' => '2711 Plaza Dr, Sugar Land, TX',        'lat' => 29.6197, 'lng' => -95.6349],
        ['key' => 'midtown',   'name' => 'Midtown Apartments',    'address' => '2800 Bagby St, Houston, TX',           'lat' => 29.7370, 'lng' => -95.3773],
        ['key' => 'woodlands', 'name' => 'The Woodlands Mall',    'address' => '1201 Lake Woodlands Dr, TX',           'lat' => 30.1658, 'lng' => -95.4613],
        ['key' => 'museum',    'name' => 'Museum District Condo', 'address' => '5100 Montrose Blvd, Houston, TX',      'lat' => 29.7256, 'lng' => -95.3903],
    ];

    public function handle(DedicatedRouteOptimizationService $optimizer): int
    {
        $clientId = (int) $this->option('client');

        if ($this->option('fresh')) {
            $this->purgePreviousRuns();
        }

        $endLat = null;
        $endLng = null;
        $endLabel = null;

        if ($endKey = $this->option('end')) {
            $match = collect(self::STOPS)->firstWhere('key', $endKey);
            if (!$match) {
                $this->error("Unknown --end value '{$endKey}'. Use one of: " . collect(self::STOPS)->pluck('key')->join(', '));
                return self::FAILURE;
            }
            $endLat = $match['lat'];
            $endLng = $match['lng'];
            $endLabel = $match['address'];
        }

        $route = UrbanGoodzDedicatedRoute::create([
            'business_client_id' => $clientId,
            'route_name' => 'Optimizer Test ' . now()->format('m-d H:i'),
            'route_type' => 'dedicated',
            'source_module' => 'package_routes',
            'status' => 'active',
            'pickup_location' => self::PICKUP['label'],
            'pickup_lat' => self::PICKUP['lat'],
            'pickup_lng' => self::PICKUP['lng'],
            'end_location' => $endLabel,
            'end_lat' => $endLat,
            'end_lng' => $endLng,
            'return_to_origin' => (bool) $this->option('return-to-origin'),
            'total_packages' => count(self::STOPS),
            'optimization_status' => 'not_optimized',
            'scheduled_date' => now()->toDateString(),
        ]);

        foreach (self::STOPS as $i => $stop) {
            UrbanGoodzRoutePackage::create([
                'dedicated_route_id' => $route->id,
                'business_client_id' => $clientId,
                'tracking_id' => 'OPT-' . strtoupper(Str::random(8)),
                'source_module' => 'package_routes',
                'dropoff_name' => $stop['name'],
                'dropoff_address' => $stop['address'],
                'dropoff_city' => 'Houston',
                'dropoff_state' => 'TX',
                'dropoff_lat' => $stop['lat'],
                'dropoff_lng' => $stop['lng'],
                // The mis-ordering lives here: 1..6 in the zig-zag sequence.
                'stop_order' => $i + 1,
                'status' => 'pending',
                'package_type' => 'parcel',
                'priority' => 'normal',
            ]);
        }

        $this->info("Route #{$route->id} created with " . count(self::STOPS) . ' stops.');
        $this->line('  pickup: ' . self::PICKUP['label']);
        $this->line('  finish: ' . ($this->option('return-to-origin') ? 'back at pickup' : ($endLabel ?: 'last stop')));
        $this->newLine();

        $this->table(['#', 'Stop (as loaded)'], collect(self::STOPS)->map(fn ($s, $i) => [$i + 1, $s['name']])->all());

        if ($this->option('no-optimize')) {
            $this->comment('Skipped optimisation (--no-optimize).');
            return self::SUCCESS;
        }

        try {
            $result = $optimizer->optimize($route, (bool) $route->return_to_origin, 'admin', null);
        } catch (\Throwable $e) {
            $this->error('Optimizer threw: ' . get_class($e) . ': ' . $e->getMessage());
            return self::FAILURE;
        }

        $after = UrbanGoodzRoutePackage::where('dedicated_route_id', $route->id)
            ->orderBy('stop_order')
            ->get(['dropoff_name', 'stop_order']);

        $this->newLine();
        $this->info('Optimised order:');
        $this->table(['#', 'Stop (after)'], $after->map(fn ($p) => [$p->stop_order, $p->dropoff_name])->all());

        $fresh = $route->fresh();
        $this->newLine();
        $this->line('  changed:  ' . var_export($result['changed'] ?? null, true));
        $this->line('  original: ' . ($fresh->original_distance_miles ?? '-') . ' mi / ' . ($fresh->original_duration_minutes ?? '-') . ' min');
        $this->line('  optimized:' . ($fresh->optimized_distance_miles ?? '-') . ' mi / ' . ($fresh->optimized_duration_minutes ?? '-') . ' min');
        $this->line('  status:   ' . $fresh->optimization_status);
        $this->line('  method:   ' . ($fresh->optimization_method ?? '-') . ' via ' . ($fresh->optimization_provider ?? '-'));

        return self::SUCCESS;
    }

    /**
     * Only ever removes routes this command created. Anything else on the
     * system is left alone.
     */
    private function purgePreviousRuns(): void
    {
        $ids = UrbanGoodzDedicatedRoute::withTrashed()
            ->where('route_name', 'like', 'Optimizer Test %')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        UrbanGoodzRoutePackage::whereIn('dedicated_route_id', $ids)->delete();
        UrbanGoodzDedicatedRoute::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->comment("Removed {$ids->count()} route(s) from previous runs.");
    }
}
