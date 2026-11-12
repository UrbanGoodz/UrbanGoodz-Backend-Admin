<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Which zones can actually fulfil an order?
 *
 * A zone is open to customers as soon as it has an active store in it. Nothing
 * checks that it also has a driver, and nothing refuses an order placed where
 * there is none - so a zone can take checkouts it cannot deliver, and the only
 * symptom is an order that never gets assigned. That went unnoticed until
 * someone counted by hand; this makes it a command anyone can run.
 *
 * Reports only. Opening or closing a zone is a business decision about where
 * Urban Goodz operates, not something to infer from a driver count.
 */
class UrbanGoodzZoneReadiness extends Command
{
    protected $signature = 'urban-goodz:zone-readiness
        {--open-only : Only list zones currently open to customers.}';

    protected $description = 'Report, per zone, whether there are drivers to deliver the orders its storefronts can take.';

    public function handle(): int
    {
        $zones = DB::table('zones')->orderBy('id')->get(['id', 'name', 'status']);

        if ($zones->isEmpty()) {
            $this->warn('No zones configured.');
            return self::SUCCESS;
        }

        $storesByZone = DB::table('stores')->where('status', 1)
            ->selectRaw('zone_id, count(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');
        $driversByZone = DB::table('delivery_men')->where('status', 1)
            ->selectRaw('zone_id, count(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');

        $rows = [];
        $exposed = 0;
        $exposedStores = 0;

        foreach ($zones as $zone) {
            $stores = (int) ($storesByZone[$zone->id] ?? 0);
            $drivers = (int) ($driversByZone[$zone->id] ?? 0);

            if ($this->option('open-only') && (!$zone->status || $stores === 0)) {
                continue;
            }

            // The state that matters: customers can order here, and nobody can
            // deliver it. Everything else is either closed or staffed.
            $canOrder = $zone->status && $stores > 0;
            $verdict = match (true) {
                !$zone->status => 'zone inactive',
                $stores === 0 => 'no storefronts',
                $drivers === 0 => '*** TAKES ORDERS, NO DRIVER ***',
                default => 'ok',
            };

            if ($canOrder && $drivers === 0) {
                $exposed++;
                $exposedStores += $stores;
            }

            $rows[] = [$zone->id, mb_strimwidth((string) $zone->name, 0, 40, '...'), $stores, $drivers, $verdict];
        }

        $this->table(['zone', 'name', 'active stores', 'active drivers', 'verdict'], $rows);

        if ($exposed > 0) {
            $this->newLine();
            $this->error("{$exposed} zone(s) are open to customers with no driver to deliver, covering {$exposedStores} storefront(s).");
            $this->line('An order placed in one of these is accepted and then cannot be assigned.');
            $this->line('Either staff the zone or deactivate it - nothing in the app refuses the order.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Every zone open to customers has at least one active driver.');

        return self::SUCCESS;
    }
}
