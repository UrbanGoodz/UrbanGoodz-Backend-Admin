<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Services\UrbanGoodz\LoadBoard\TrukTekAdapter;

/**
 * Load sourcing over TrukTek's public board.
 *
 * Unlike every other adapter in this namespace, this one is not fail-closed:
 * the upstream endpoint requires no credentials, so it works as soon as it is
 * enabled. See TrukTekAdapter for the verified endpoint behaviour.
 *
 * Booking and bidding stay unsupported - the public board is read-only, and
 * a load is still tendered to the broker off-platform.
 */
class TrukTekLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'truktek';

    private ?TrukTekAdapter $innerAdapter = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Accept either the whole providers map or this provider's own block.
        $providerConfig = $config['truktek'] ?? $config;

        if (!empty($providerConfig['base_url'])) {
            $this->innerAdapter = new TrukTekAdapter($providerConfig);
        }
    }

    public function isConfigured(): bool
    {
        return $this->innerAdapter !== null && $this->innerAdapter->isConfigured();
    }

    public function search(array $criteria): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('TrukTek load sourcing is disabled. Set TRUKTEK_ENABLED=true to use the public board.');
        }

        try {
            $raw = $this->innerAdapter->fetchLoads($criteria, (int) ($criteria['max_results'] ?? 50));
            $loads = array_map(fn (array $item) => $this->innerAdapter->normalize($item), $raw);

            return [
                'success' => true,
                'source' => $this->key,
                'loads' => $loads,
                'count' => count($loads),
            ];
        } catch (\Exception $e) {
            $this->logError('search', $e->getMessage());

            return $this->failClosed('TrukTek API request failed: ' . $e->getMessage());
        }
    }

    public function getLoad(string $externalId): array
    {
        if (!$this->isConfigured()) {
            return $this->failClosed('TrukTek load sourcing is disabled.');
        }

        try {
            $raw = $this->innerAdapter->getLoad($externalId);

            if (!$raw) {
                // The public board has no single-load endpoint, so a load that
                // has rolled off the current page is simply gone.
                return $this->failClosed("TrukTek load {$externalId} is no longer on the public board.");
            }

            return [
                'success' => true,
                'source' => $this->key,
                'load' => $this->innerAdapter->normalize($raw),
            ];
        } catch (\Exception $e) {
            $this->logError('getLoad', $e->getMessage());

            return $this->failClosed('TrukTek API request failed: ' . $e->getMessage());
        }
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->getLoad($externalId);
    }
}
