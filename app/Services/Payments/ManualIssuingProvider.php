<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use Illuminate\Support\Str;

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
            'success' => true,
            'provider' => $this->providerName(),
            'cardholder_id' => 'MANUAL_CH_' . Str::uuid()->toString(),
            'status' => 'pending',
            'message' => 'Manual cardholder created. Awaiting real provider configuration.',
        ];
    }

    public function createVirtualCard(array $data): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => 'MANUAL_CARD_' . Str::uuid()->toString(),
            'cardholder_id' => $data['cardholder_id'] ?? null,
            'last4' => null,
            'brand' => null,
            'status' => 'provider_pending',
            'message' => 'Card request created in provider_pending status. Awaiting real issuing provider.',
        ];
    }

    public function setSpendingLimit(string $cardId, array $limits): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'limits' => $limits,
            'message' => 'Spending limit recorded. Awaiting real provider for enforcement.',
        ];
    }

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'restrictions' => $merchantRestrictions,
            'message' => 'Merchant restrictions recorded. Awaiting real provider for enforcement.',
        ];
    }

    public function authorizeTransaction(array $data): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'transaction_id' => 'MANUAL_TXN_' . Str::uuid()->toString(),
            'status' => 'provider_pending',
            'message' => 'Manual purchase authorization. Admin must reconcile with actual receipt.',
        ];
    }

    public function freezeCard(string $cardId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'frozen',
            'message' => 'Card frozen in manual mode.',
        ];
    }

    public function closeCard(string $cardId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'card_id' => $cardId,
            'status' => 'closed',
            'message' => 'Card closed in manual mode.',
        ];
    }

    public function retrieveCardTransaction(string $transactionId): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'transaction_id' => $transactionId,
            'status' => 'manual_review',
            'message' => 'Manual transaction. Admin must verify with actual receipt.',
        ];
    }
}
