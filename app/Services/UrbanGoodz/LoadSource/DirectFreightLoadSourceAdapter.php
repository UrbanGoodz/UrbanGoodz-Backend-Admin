<?php

namespace App\Services\UrbanGoodz\LoadSource;

class DirectFreightLoadSourceAdapter extends AbstractLoadSourceAdapter
{
    protected string $key = 'direct_freight';

    public function isConfigured(): bool
    {
        return false;
    }

    public function search(array $criteria): array
    {
        return $this->failClosed('Direct Freight partner API access not yet authorized. Awaiting partnership agreement and API credentials.');
    }

    public function getLoad(string $externalId): array
    {
        return $this->failClosed('Direct Freight partner API access not yet authorized.');
    }

    public function refreshStatus(string $externalId): array
    {
        return $this->failClosed('Direct Freight partner API access not yet authorized.');
    }
}
