<?php

namespace App\Console\Commands;

use App\Services\UrbanGoodz\LoadBoard\DatAdapter;
use App\Services\UrbanGoodz\LoadBoard\TruckstopAdapter;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLoadBoard extends Command
{
    protected $signature = 'sync-load-board
                            {--provider= : Sync a specific provider only (dat, truckstop). Empty = all enabled.}
                            {--max=250 : Max loads per provider}
                            {--dry-run : Preview without writing to DB}
                            {--state= : Filter by origin state}';

    protected $description = 'Sync loads from external providers (DAT, Truckstop) into the load board';

    private UrbanGoodzLoadBoardService $service;

    public function __construct(UrbanGoodzLoadBoardService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        if (!config('urban_goodz_load_board.sync.enabled', true)) {
            $this->info('Load board sync is disabled in config.');
            return self::SUCCESS;
        }

        $providerFilter = $this->option('provider');
        $maxResults = (int) $this->option('max');
        $dryRun = $this->option('dry-run');
        $stateFilter = $this->option('state');

        $adapters = $this->buildAdapters($providerFilter);

        if (empty($adapters)) {
            $this->warn('No enabled providers found. Set provider credentials in config/urban_goodz_load_board.php and .env');
            return self::SUCCESS;
        }

        $this->info('Load Board Sync — ' . ($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->newLine();

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($adapters as $slug => $adapter) {
            $this->info("Syncing from: {$slug}");

            if (!$adapter->isConfigured()) {
                $this->warn("  ⚠ {$slug} is not configured — skipping");
                continue;
            }

            $filters = config("urban_goodz_load_board.providers.{$slug}.default_filters", []);
            if ($stateFilter) {
                $filters['origin_state'] = $stateFilter;
            }

            try {
                $loads = $adapter->fetchLoads($filters, $maxResults);
                $this->info("  Fetched " . count($loads) . " loads from {$slug}");

                if ($dryRun) {
                    $this->table(
                        ['#', 'External ID', 'Origin', 'Destination', 'Payout', 'Equipment'],
                        array_map(fn($l, $i) => [
                            $i + 1,
                            $l['external_id'],
                            ($l['origin_city'] ?? '?') . ', ' . ($l['origin_state'] ?? '?'),
                            ($l['destination_city'] ?? '?') . ', ' . ($l['destination_state'] ?? '?'),
                            '$' . number_format($l['payout_amount'] ?? 0, 2),
                            $l['equipment_type'] ?? '?',
                        ], array_slice($loads, 0, 20), range(0, min(count($loads) - 1, 19)))
                    );
                    if (count($loads) > 20) {
                        $this->info("  ... and " . (count($loads) - 20) . " more loads (truncated for display)");
                    }
                } else {
                    $synced = $this->service->syncFromProvider($slug, $loads);
                    $totalSynced += $synced;
                    $this->info("  Synced {$synced} loads to database");

                    Log::info("Load board sync completed for {$slug}", [
                        'fetched' => count($loads),
                        'synced' => $synced,
                    ]);
                }
            } catch (\Exception $e) {
                $totalErrors++;
                $this->error("  ✖ {$slug} sync failed: {$e->getMessage()}");
                Log::error("Load board sync failed for {$slug}", ['error' => $e->getMessage()]);
            }

            $this->newLine();
        }

        $this->info("Sync complete. Total synced: {$totalSynced} | Errors: {$totalErrors}");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildAdapters(?string $providerFilter): array
    {
        $adapters = [];
        $allProviders = config('urban_goodz_load_board.providers', []);

        foreach ($allProviders as $slug => $config) {
            if ($providerFilter && $slug !== $providerFilter) {
                continue;
            }

            if (empty($config['enabled'])) {
                continue;
            }

            match ($slug) {
                'dat' => $adapters['dat'] = new DatAdapter($config),
                'truckstop' => $adapters['truckstop'] = new TruckstopAdapter($config),
                default => null,
            };
        }

        return $adapters;
    }
}
