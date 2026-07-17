<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\ExternalLoad;
use App\Models\LoadSource;

class UrbanGoodzInternalLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'urban_goodz_internal';
    protected bool $bidding = true;
    protected bool $booking = true;

    public function isConfigured(): bool
    {
        return true;
    }

    public function search(array $criteria): array
    {
        $query = ExternalLoad::whereHas('source', fn($q) => $q->where('source_key', $this->key))
            ->where('status', 'available')
            ->where('is_duplicate', false);

        if (!empty($criteria['origin_state'])) {
            $query->where('origin_state', $criteria['origin_state']);
        }
        if (!empty($criteria['destination_state'])) {
            $query->where('destination_state', $criteria['destination_state']);
        }
        if (!empty($criteria['equipment_type'])) {
            $query->where('equipment_type', $criteria['equipment_type']);
        }
        if (!empty($criteria['min_rate'])) {
            $query->where('gross_rate', '>=', $criteria['min_rate']);
        }
        if (!empty($criteria['max_deadhead'])) {
            $query->where('distance_deadhead', '<=', $criteria['max_deadhead']);
        }
        if (!empty($criteria['pickup_date_from'])) {
            $query->where('pickup_start', '>=', $criteria['pickup_date_from']);
        }
        if (!empty($criteria['pickup_date_to'])) {
            $query->where('pickup_end', '<=', $criteria['pickup_date_to']);
        }
        if (!empty($criteria['weight_max'])) {
            $query->where('weight', '<=', $criteria['weight_max']);
        }

        $loads = $query->orderByDesc('gross_rate')->limit($criteria['max_results'] ?? 50)->get();

        return [
            'success' => true,
            'source' => $this->key,
            'loads' => $loads->toArray(),
            'count' => $loads->count(),
        ];
    }

    public function getLoad(string $externalId): array
    {
        $load = ExternalLoad::whereHas('source', fn($q) => $q->where('source_key', $this->key))
            ->where('external_id', $externalId)
            ->first();

        if (!$load) {
            return $this->failClosed("Load {$externalId} not found");
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
