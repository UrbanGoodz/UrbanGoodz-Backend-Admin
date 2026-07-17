<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\LoadImport;
use App\Models\ExternalLoad;

class ManualLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'manual_import';

    public function isConfigured(): bool
    {
        return true;
    }

    public function search(array $criteria): array
    {
        $query = LoadImport::with('source')
            ->whereIn('status', ['completed', 'partially_completed']);

        if (!empty($criteria['imported_by'])) {
            $query->where('imported_by', $criteria['imported_by']);
        }

        $imports = $query->orderByDesc('created_at')->limit($criteria['max_results'] ?? 50)->get();

        return [
            'success' => true,
            'source' => $this->key,
            'loads' => $imports->toArray(),
            'count' => $imports->count(),
        ];
    }

    public function getLoad(string $externalId): array
    {
        $id = str_replace('manual-', '', $externalId);
        $load = ExternalLoad::whereHas('source', fn($q) => $q->where('source_key', $this->key))
            ->where('external_id', $id)
            ->first();

        if (!$load) {
            return $this->failClosed("Manual import load {$externalId} not found");
        }

        return [
            'success' => true,
            'source' => $this->key,
            'load' => $load->toArray(),
        ];
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->getLoad($externalId);
    }
}
