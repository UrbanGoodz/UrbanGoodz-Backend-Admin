<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Close the zones that take orders nobody can deliver.
 *
 * urban-goodz:zone-readiness reports the problem; this is the one-step fix for
 * it. A zone with active storefronts and no active driver accepts a checkout
 * and then cannot assign it, which is worse for a customer than never being
 * listed in their city at all - the first is an order that silently goes
 * nowhere, the second is recoverable.
 *
 * Deliberately conservative:
 *  - reports by default and writes only with --apply;
 *  - never touches a zone that has at least one active driver;
 *  - never touches a zone that is already inactive;
 *  - --keep lets you hold a zone open deliberately (a launch market being
 *    staffed this week, say) without editing the command;
 *  - writes a restore file so reopening is one statement per zone.
 *
 * Reopening is just `status = 1`, so this is reversible at any time. Staff a
 * zone, run zone-readiness to confirm, then reopen it.
 */
class UrbanGoodzCloseUnstaffedZones extends Command
{
    protected $signature = 'urban-goodz:close-unstaffed-zones
        {--apply : Write the change. Without this the command only reports.}
        {--keep=* : Zone ids to leave open even with no driver.}';

    protected $description = 'Deactivate zones that have storefronts but no active driver, so they stop accepting orders that cannot be delivered.';

    public function handle(): int
    {
        $keep = array_map('intval', array_filter((array) $this->option('keep')));

        $storesByZone = DB::table('stores')->where('status', 1)
            ->selectRaw('zone_id, count(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');
        $driversByZone = DB::table('delivery_men')->where('status', 1)
            ->selectRaw('zone_id, count(*) c')->groupBy('zone_id')->pluck('c', 'zone_id');

        $targets = [];

        foreach (DB::table('zones')->where('status', 1)->orderBy('id')->get(['id', 'name']) as $zone) {
            $stores = (int) ($storesByZone[$zone->id] ?? 0);
            $drivers = (int) ($driversByZone[$zone->id] ?? 0);

            if ($stores === 0 || $drivers > 0) {
                continue;
            }

            if (in_array((int) $zone->id, $keep, true)) {
                $this->line("  keeping zone {$zone->id} ({$zone->name}) open as instructed, with no driver.");
                continue;
            }

            $targets[] = ['id' => $zone->id, 'name' => $zone->name, 'stores' => $stores];
        }

        if (!$targets) {
            $this->info('No zone is open to customers without a driver. Nothing to close.');
            return self::SUCCESS;
        }

        $this->warn(count($targets) . ' zone(s) accept orders with no driver behind them:');
        $this->table(
            ['zone', 'name', 'active storefronts'],
            array_map(fn ($t) => [$t['id'], mb_strimwidth($t['name'], 0, 40, '...'), $t['stores']], $targets)
        );

        $storefronts = array_sum(array_column($targets, 'stores'));
        $this->line("Closing these hides {$storefronts} storefront(s) from customers until the zone is staffed.");

        if (!$this->option('apply')) {
            $this->newLine();
            $this->info('Dry run. Nothing was written. Re-run with --apply to close them.');
            $this->line('Hold one open with --keep=<zone id>.');
            return self::SUCCESS;
        }

        $ids = array_column($targets, 'id');

        $restore = "-- Reopen zones closed by urban-goodz:close-unstaffed-zones\n";
        foreach ($targets as $t) {
            $restore .= "UPDATE zones SET status=1 WHERE id={$t['id']}; -- {$t['name']}\n";
        }
        $path = storage_path('app/reopen_zones_' . date('Ymd_His') . '.sql');
        @file_put_contents($path, $restore);

        $written = DB::table('zones')->whereIn('id', $ids)->update(['status' => 0]);

        $this->newLine();
        $this->info("Closed {$written} zone(s).");
        $this->line("Reopen with: {$path}");
        $this->line('Staff a zone, confirm with urban-goodz:zone-readiness, then reopen it.');

        return self::SUCCESS;
    }
}
