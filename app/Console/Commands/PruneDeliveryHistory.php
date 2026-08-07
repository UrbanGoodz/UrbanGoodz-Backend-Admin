<?php

namespace App\Console\Commands;

use App\Models\DeliveryHistory;
use Illuminate\Console\Command;

/**
 * Keeps the driver breadcrumb trail from growing without bound.
 *
 * Location pings arrive every few seconds per active driver. That is fine --
 * a trail is the point -- but it is unbounded, so old points are dropped once
 * they stop being useful.
 *
 * Two things are deliberately preserved:
 *
 *   - the most recent point for every driver, whatever its age, because
 *     DeliveryMan::last_location uses latestOfMany() and pruning it would
 *     blank the live map for anyone who has been idle;
 *   - points attached to an order, for longer, because those are the record
 *     of how a delivery actually went and are what a dispute is settled with.
 */
class PruneDeliveryHistory extends Command
{
    protected $signature = 'delivery-history:prune
                            {--days=14 : Drop unattached points older than this}
                            {--order-days=90 : Drop order-attached points older than this}
                            {--dry-run : Report what would be removed without deleting}';

    protected $description = 'Prune old driver location breadcrumbs, preserving each driver\'s latest point';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $orderDays = max($days, (int) $this->option('order-days'));
        $dry = (bool) $this->option('dry-run');

        // The newest point per driver is exempt regardless of age.
        $keepIds = DeliveryHistory::selectRaw('MAX(id) as id')
            ->groupBy('delivery_man_id')
            ->pluck('id')
            ->all();

        $unattached = DeliveryHistory::whereNull('order_id')
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('id', $keepIds);

        $attached = DeliveryHistory::whereNotNull('order_id')
            ->where('created_at', '<', now()->subDays($orderDays))
            ->whereNotIn('id', $keepIds);

        if ($dry) {
            $this->info('Would prune ' . $unattached->count() . " unattached points older than {$days} days");
            $this->info('Would prune ' . $attached->count() . " order-attached points older than {$orderDays} days");
            $this->info('Preserving ' . count($keepIds) . ' latest-per-driver points');
            return self::SUCCESS;
        }

        $a = $unattached->delete();
        $b = $attached->delete();

        $this->info("Pruned {$a} unattached and {$b} order-attached points; kept " . count($keepIds) . ' latest-per-driver.');

        return self::SUCCESS;
    }
}
