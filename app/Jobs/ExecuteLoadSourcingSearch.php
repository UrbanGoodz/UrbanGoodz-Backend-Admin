<?php

namespace App\Jobs;

use App\Models\ExternalLoad;
use App\Models\LoadSource;
use App\Models\LoadSourceError;
use App\Models\LoadSourceSyncRun;
use App\Services\UrbanGoodz\LoadSource\LoadNormalizer;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteLoadSourcingSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public array $criteria,
        public int $userId,
        public string $userType = 'admin'
    ) {
        $this->onQueue('sourcing');
        $this->afterCommit();
    }

    public function handle(LoadSourcingService $sourcingService, LoadNormalizer $normalizer): void
    {
        $startTime = microtime(true);

        $syncRun = LoadSourceSyncRun::create([
            'source_id' => null,
            'status' => 'running',
            'search_criteria' => $this->criteria,
            'loads_found' => 0,
            'loads_new' => 0,
            'loads_updated' => 0,
            'loads_duplicate' => 0,
            'loads_expired' => 0,
        ]);

        try {
            $sources = $this->resolveSources();

            $totalFound = 0;
            $totalNew = 0;
            $totalDuplicates = 0;

            foreach ($sources as $source) {
                $adapter = $this->resolveAdapter($source->source_key);
                if (!$adapter || !$adapter->isConfigured()) {
                    continue;
                }

                $result = $adapter->search($this->criteria);

                if ($result['success'] && !empty($result['loads'])) {
                    $sourceNew = 0;
                    $sourceDuplicate = 0;

                    foreach ($result['loads'] as $loadData) {
                        $normalized = $normalizer->normalize($loadData, $source->id);
                        $externalLoad = $normalizer->persistNormalized($normalized);

                        if ($externalLoad->is_duplicate) {
                            $sourceDuplicate++;
                        } else {
                            $sourceNew++;
                        }
                    }

                    $source->recordSync(
                        count($result['loads']),
                        $sourceNew,
                        0,
                        $sourceDuplicate,
                        0
                    );

                    $totalFound += count($result['loads']);
                    $totalNew += $sourceNew;
                    $totalDuplicates += $sourceDuplicate;
                } elseif (!$result['success']) {
                    $source->recordError($result['error'] ?? 'Search failed');

                    LoadSourceError::create([
                        'source_id' => $source->id,
                        'sync_run_id' => $syncRun->id,
                        'error_code' => 'SEARCH_FAILED',
                        'error_message' => $result['error'] ?? 'Search failed',
                        'context' => $this->criteria,
                    ]);
                }
            }

            $durationMs = (microtime(true) - $startTime) * 1000;

            $syncRun->update([
                'status' => 'completed',
                'loads_found' => $totalFound,
                'loads_new' => $totalNew,
                'loads_duplicate' => $totalDuplicates,
                'duration_ms' => round($durationMs, 2),
                'metadata' => [
                    'user_id' => $this->userId,
                    'user_type' => $this->userType,
                    'sources_searched' => $sources->count(),
                ],
            ]);

            Log::info('Load sourcing search completed', [
                'sync_run_id' => $syncRun->id,
                'loads_found' => $totalFound,
                'loads_new' => $totalNew,
                'loads_duplicate' => $totalDuplicates,
                'duration_ms' => round($durationMs, 2),
            ]);
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;

            $syncRun->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => round($durationMs, 2),
            ]);

            LoadSourceError::create([
                'source_id' => null,
                'sync_run_id' => $syncRun->id,
                'error_code' => 'SYNC_RUN_FAILED',
                'error_message' => $e->getMessage(),
                'context' => array_merge($this->criteria, [
                    'exception' => $e::class,
                    'trace' => $e->getTraceAsString(),
                ]),
            ]);

            Log::error('Load sourcing search failed', [
                'sync_run_id' => $syncRun->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ExecuteLoadSourcingSearch job exhausted retries', [
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    private function resolveSources(): \Illuminate\Support\Collection
    {
        $query = LoadSource::enabled()->withApiAccess();

        if (!empty($this->criteria['preferred_sources']) && is_array($this->criteria['preferred_sources'])) {
            $query->whereIn('source_key', $this->criteria['preferred_sources']);
        }

        return $query->get();
    }

    private function resolveAdapter(string $sourceKey): ?\App\Contracts\LoadSource\LoadSourceAdapter
    {
        $adapters = [
            'urban_goodz_internal' => \App\Services\UrbanGoodz\LoadSource\UrbanGoodzInternalLoadSourceAdapter::class,
            'email_inbox' => \App\Services\UrbanGoodz\LoadSource\EmailLoadSourceAdapter::class,
            'manual_import' => \App\Services\UrbanGoodz\LoadSource\ManualLoadSourceAdapter::class,
            'dat' => \App\Services\UrbanGoodz\LoadSource\DatLoadSourceAdapter::class,
            'truckstop' => \App\Services\UrbanGoodz\LoadSource\TruckstopLoadSourceAdapter::class,
            'trulos' => \App\Services\UrbanGoodz\LoadSource\TrulosLoadSourceAdapter::class,
            'tb_load' => \App\Services\UrbanGoodz\LoadSource\TbLoadLoadSourceAdapter::class,
            'direct_freight' => \App\Services\UrbanGoodz\LoadSource\DirectFreightLoadSourceAdapter::class,
            'trucker_path' => \App\Services\UrbanGoodz\LoadSource\TruckerPathLoadSourceAdapter::class,
            'trucksmarter' => \App\Services\UrbanGoodz\LoadSource\TruckSmarterLoadSourceAdapter::class,
        ];

        $class = $adapters[$sourceKey] ?? null;
        if (!$class) return null;

        $config = config('urban_goodz_load_board.providers.' . $sourceKey, []);

        return new $class($config);
    }
}
