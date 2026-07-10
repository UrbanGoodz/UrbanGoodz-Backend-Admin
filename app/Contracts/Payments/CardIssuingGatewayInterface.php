<?php

namespace App\Contracts\Payments;

interface CardIssuingGatewayInterface
{
    public function providerName(): string;

    public function isEnabled(): bool;

    public function createCardholder(array $data): array;

    public function createVirtualCard(array $data): array;

    public function setSpendingLimit(string $cardId, array $limits): array;

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array;

    public function authorizeTransaction(array $data): array;

    public function freezeCard(string $cardId): array;

    public function closeCard(string $cardId): array;

    public function retrieveCardTransaction(string $transactionId): array;
}
