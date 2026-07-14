<?php

namespace App\Services\UrbanGoodz\LoadSource;

class TruckSmarterLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'trucksmarter';

    public function isConfigured(): bool
    {
        return false;
    }

    public function search(array $criteria): array
    {
        return $this->failClosed('TruckSmarter partner API access not yet authorized. Awaiting partnership agreement and API credentials.');
    }

    public function getLoad(string $externalId): array
    {
        return $this->failClosed('TruckSmarter partner API access not yet authorized.');
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->failClosed('TruckSmarter partner API access not yet authorized.');
    }
}
