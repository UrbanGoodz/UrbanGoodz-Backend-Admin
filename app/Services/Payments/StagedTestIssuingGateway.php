<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;

class StagedTestIssuingGateway implements CardIssuingGatewayInterface
{
    public function providerName(): string
    {
        return 'staged_test_issuing';
    }

    public function isEnabled(): bool
    {
        return config('urban_goodz_payments.staged_test.enabled', true);
    }

    public function createCardholder(array $data): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'cardholder_id' => 'STG_CH_' . hash('sha256', (string) ($data['driver_id'] ?? 'test')),
            'status' => 'active',
        ];
    }

    public function createVirtualCard(array $data): array
    {
        $identity = (string) data_get($data, 'metadata.idempotency_identity', 'test');
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => 'STG_CARD_' . hash('sha256', $identity),
            'cardholder_id' => $data['cardholder_id'] ?? null,
            'last4' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'brand' => 'visa',
            'status' => 'active',
            'spending_limit' => $data['spending_limit'] ?? null,
        ];
    }

    public function findCardByIdempotencyIdentity(string $identity): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'not_found',
        ];
    }

    public function createSecureRevealSession(string $cardId, string $nonce): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'unavailable',
            'message' => 'Staged test cards do not expose card credentials.',
        ];
    }

    public function setSpendingLimit(string $cardId, array $limits): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'limits' => $limits,
        ];
    }

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'restrictions' => $merchantRestrictions,
        ];
    }

    public function authorizeTransaction(array $data): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'transaction_id' => 'STG_TXN_' . bin2hex(random_bytes(12)),
            'status' => 'approved',
        ];
    }

    public function freezeCard(string $cardId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'frozen',
        ];
    }

    public function closeCard(string $cardId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'closed',
        ];
    }

    public function retrieveCardTransaction(string $transactionId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'transaction_id' => $transactionId,
            'status' => 'settled',
        ];
    }

    public function retrieveCardStatus(string $cardId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'canceled',
        ];
    }
}
