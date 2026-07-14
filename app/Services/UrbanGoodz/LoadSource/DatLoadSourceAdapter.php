<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Services\UrbanGoodz\LoadBoard\DatAdapter;
use Illuminate\Support\Facades\Log;

class DatLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'dat';
    protected bool $bidding = true;

    private ?DatAdapter $innerAdapter = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (!empty($config['dat'])) {
            $this->innerAdapter = new DatAdapter($config['dat']);
        } elseif (!empty($config['api_key'])) {
            $this->innerAdapter = new DatAdapter($config);
        }
    }

    public function isConfigured(): bool
    {
        return $this->innerAdapter !== null && $this->innerAdapter->isConfigured();
    }

    public function search(array $criteria): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('DAT partner API access not yet authorized. Awaiting credentials.');
        }

        try {
            $rawLoads = $this->innerAdapter->fetchLoads($criteria, $criteria['max_results'] ?? 50);
            $loads = array_map(fn($raw) => $this->innerAdapter->normalize($raw), $rawLoads);

            return [
                'success' => true,
                'source' => $this->key,
                'loads' => $loads,
                'count' => count($loads),
            ];
        } catch (\Exception $e) {
            $this->logError('search', $e->getMessage());
            return $this->failClosed('DAT API request failed: ' . $e->getMessage());
        }
    }

    public function getLoad(string $externalId): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('DAT partner API access not yet authorized.');
        }

        try {
            $raw = $this->innerAdapter->getLoad($externalId);
            if (!$raw) {
                return $this->failClosed("DAT load {$externalId} not found");
            }
            return [
                'success' => true,
                'source' => $this->key,
                'load' => $this->innerAdapter->normalize($raw),
            ];
        } catch (\Exception $e) {
            return $this->failClosed('DAT API request failed: ' . $e->getMessage());
        }
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->getLoad($externalId);
    }
}
