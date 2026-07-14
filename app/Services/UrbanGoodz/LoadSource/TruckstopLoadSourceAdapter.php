<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Services\UrbanGoodz\LoadBoard\TruckstopAdapter;

class TruckstopLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'truckstop';
    protected bool $bidding = true;

    private ?TruckstopAdapter $innerAdapter = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (!empty($config['truckstop'])) {
            $this->innerAdapter = new TruckstopAdapter($config['truckstop']);
        } elseif (!empty($config['client_id'])) {
            $this->innerAdapter = new TruckstopAdapter($config);
        }
    }

    public function isConfigured(): bool
    {
        return $this->innerAdapter !== null && $this->innerAdapter->isConfigured();
    }

    public function search(array $criteria): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('Truckstop partner API access not yet authorized. Awaiting credentials.');
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
            return $this->failClosed('Truckstop API request failed: ' . $e->getMessage());
        }
    }

    public function getLoad(string $externalId): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('Truckstop partner API access not yet authorized.');
        }

        try {
            $raw = $this->innerAdapter->getLoad($externalId);
            if (!$raw) {
                return $this->failClosed("Truckstop load {$externalId} not found");
            }
            return [
                'success' => true,
                'source' => $this->key,
                'load' => $this->innerAdapter->normalize($raw),
            ];
        } catch (\Exception $e) {
            return $this->failClosed('Truckstop API request failed: ' . $e->getMessage());
        }
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->getLoad($externalId);
    }
}
