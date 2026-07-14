<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\LoadEmailIngestion;
use App\Models\ExternalLoad;
use App\Models\LoadSource;

class EmailLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'email_inbox';

    public function isConfigured(): bool
    {
        return true;
    }

    public function search(array $criteria): array
    {
        $query = LoadEmailIngestion::where('status', 'pending_review');

        if (!empty($criteria['origin_state'])) {
            $query->where('origin_state', $criteria['origin_state']);
        }
        if (!empty($criteria['destination_state'])) {
            $query->where('destination_state', $criteria['destination_state']);
        }

        $emails = $query->orderByDesc('received_at')->limit($criteria['max_results'] ?? 50)->get();

        $loads = [];
        foreach ($emails as $email) {
            $loads[] = [
                'external_id' => 'email-' . $email->id,
                'source' => $this->key,
                'origin_city' => $email->origin_city,
                'origin_state' => $email->origin_state,
                'destination_city' => $email->destination_city,
                'destination_state' => $email->destination_state,
                'equipment_type' => $email->equipment_type,
                'weight' => $email->weight,
                'commodity' => $email->commodity,
                'gross_rate' => $email->rate,
                'broker_name' => $email->broker_name,
                'broker_contact' => $email->broker_contact,
                'broker_reference' => $email->broker_reference,
                'confidence_score' => $email->confidence_score,
                'status' => $email->status,
                'email_ingestion_id' => $email->id,
            ];
        }

        return [
            'success' => true,
            'source' => $this->key,
            'loads' => $loads,
            'count' => count($loads),
        ];
    }

    public function getLoad(string $externalId): array
    {
        $id = str_replace('email-', '', $externalId);
        $email = LoadEmailIngestion::find($id);
        if (!$email) {
            return $this->failClosed("Email ingestion {$externalId} not found");
        }
        return [
            'success' => true,
            'source' => $this->key,
            'load' => $email->toArray(),
        ];
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->getLoad($externalId);
    }
}
