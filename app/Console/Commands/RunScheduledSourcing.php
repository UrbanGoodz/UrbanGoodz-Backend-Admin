<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteLoadSourcingSearch;
use App\Models\DispatcherSavedSearch;
use App\Models\ExternalLoad;
use App\Models\LoadSource;
use App\Models\LoadSourcingSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledSourcing extends Command
{
    protected $signature = 'run-scheduled-sourcing
                            {--dry-run : Preview actions without dispatching jobs}';

    protected $description = 'Run scheduled sourcing searches, per-source syncs, and expire stale loads';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('Scheduled Sourcing Runner — ' . ($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->newLine();

        $savedSearchesDispatched = $this->processSavedSearches($dryRun);
        $sourceSyncsDispatched = $this->processSourceSyncs($dryRun);
        $expiredCount = $this->expireStaleLoads($dryRun);

        $this->newLine();
        $this->info("Done. Saved searches dispatched: {$savedSearchesDispatched} | Source syncs dispatched: {$sourceSyncsDispatched} | Loads expired: {$expiredCount}");

        Log::info('Scheduled sourcing runner completed', [
            'saved_searches_dispatched' => $savedSearchesDispatched,
            'source_syncs_dispatched' => $sourceSyncsDispatched,
            'loads_expired' => $expiredCount,
        ]);

        return self::SUCCESS;
    }

    private function processSavedSearches(bool $dryRun): int
    {
        $refreshMinutes = (int) LoadSourcingSetting::get('saved_search_refresh_minutes', 30);
        $cutoff = now()->subMinutes($refreshMinutes);

        $searches = DispatcherSavedSearch::where('auto_alert', true)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_run_at')
                    ->orWhere('last_run_at', '<', $cutoff);
            })
            ->get();

        $this->info("Saved searches eligible for auto-run: {$searches->count()}");

        $dispatched = 0;

        foreach ($searches as $search) {
            $this->line("  -> Dispatching saved search: {$search->name} (ID: {$search->id})");

            if (!$dryRun) {
                ExecuteLoadSourcingSearch::dispatch(
                    $search->criteria ?? [],
                    $search->business_client_user_id ?? 0,
                    'business_client'
                );

                $search->update(['last_run_at' => now()]);
            }

            $dispatched++;
        }

        return $dispatched;
    }

    private function processSourceSyncs(bool $dryRun): int
    {
        $sources = LoadSource::enabled()->where('api_status', 'connected')->get();

        $this->info("Enabled connected sources: {$sources->count()}");

        $dispatched = 0;

        foreach ($sources as $source) {
            $refreshMinutes = $this->getSourceRefreshInterval($source);
            $cutoff = now()->subMinutes($refreshMinutes);

            if ($source->last_sync_at && $source->last_sync_at->greaterThan($cutoff)) {
                $this->line("  -> {$source->name}: last sync {$source->last_sync_at->diffForHumans()} — within interval, skipping");
                continue;
            }

            $this->line("  -> Dispatching source sync: {$source->name} ({$source->source_key})");

            if (!$dryRun) {
                ExecuteLoadSourcingSearch::dispatch(
                    ['preferred_sources' => [$source->source_key]],
                    0,
                    'system'
                );
            }

            $dispatched++;
        }

        return $dispatched;
    }

    private function expireStaleLoads(bool $dryRun): int
    {
        $maxAgeHours = (int) LoadSourcingSetting::get('max_load_age_hours', 72);
        $cutoff = now()->subHours($maxAgeHours);

        $staleLoads = ExternalLoad::whereNotIn('status', ['expired', 'cancelled', 'booked'])
            ->where('created_at', '<', $cutoff)
            ->where('is_duplicate', false)
            ->get();

        $this->info("Stale loads eligible for expiration: {$staleLoads->count()}");

        if (!$dryRun && $staleLoads->isNotEmpty()) {
            $expiredIds = $staleLoads->pluck('id');
            ExternalLoad::whereIn('id', $expiredIds)->update(['status' => 'expired']);

            $this->line("  -> Expired {$expiredIds->count()} loads older than {$maxAgeHours} hours");
        }

        return $staleLoads->count();
    }

    private function getSourceRefreshInterval(LoadSource $source): int
    {
        $metadata = $source->metadata ?? [];
        return $metadata['refresh_interval_minutes'] ?? (int) LoadSourcingSetting::get('default_source_refresh_minutes', 30);
    }
}
