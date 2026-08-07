<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
class ManualIssuingProvider implements CardIssuingGatewayInterface
{
    public function providerName(): string
    {
        return 'manual';
    }

    public function isEnabled(): bool
    {
        return config('urban_goodz_payments.issuing.mode') !== 'disabled';
    }

    public function createCardholder(array $data): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'not_configured',
            'error_code' => 'provider_not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function createVirtualCard(array $data): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'not_configured',
            'error_code' => 'provider_not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function findCardByIdempotencyIdentity(string $identity): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'not_configured',
        ];
    }

    public function createSecureRevealSession(string $cardId, string $nonce): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'unavailable',
            'message' => 'Secure card reveal is unavailable until a real issuing provider is configured.',
        ];
    }

    public function setSpendingLimit(string $cardId, array $limits): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'limits' => $limits,
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'restrictions' => $merchantRestrictions,
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function authorizeTransaction(array $data): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function freezeCard(string $cardId): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function closeCard(string $cardId): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function retrieveCardTransaction(string $transactionId): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'transaction_id' => $transactionId,
            'status' => 'not_configured',
            'message' => 'No issuing provider is configured.',
        ];
    }

    public function retrieveCardStatus(string $cardId): array
    {
        return [
            'success' => false,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'unavailable',
            'message' => 'No issuing provider is configured.',
        ];
    }
}
