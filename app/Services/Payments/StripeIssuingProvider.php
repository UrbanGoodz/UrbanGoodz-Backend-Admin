<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Issuing\Card as IssuingCard;
use Stripe\Issuing\Cardholder;
use Stripe\Stripe;

class StripeIssuingProvider implements CardIssuingGatewayInterface
{
    private bool $enabled;

    public function __construct()
    {
        $config = config('urban_goodz_payments.stripe');
        $this->enabled = ($config['enabled'] ?? false) && $this->hasIssuingAccess();

        if ($this->enabled) {
            $isLive = config('urban_goodz_payments.mode') === 'live_controlled';
            $key = $isLive ? ($config['live_secret_key'] ?? '') : ($config['secret_key'] ?? '');
            if (! empty($key)) {
                Stripe::setApiKey($key);
            }
        }
    }

    public function providerName(): string
    {
        return 'stripe_issuing';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function createCardholder(array $data): array
    {
        if (! $this->enabled) {
            return $this->pendingResponse('cardholder', 'Stripe Issuing access not confirmed. Cardholder created in provider_pending status.');
        }

        try {
            $cardholder = Cardholder::create([
                'name' => $data['name'] ?? 'Urban Goodz Driver',
                'email' => $data['email'] ?? null,
                'phone_number' => $data['phone'] ?? null,
                'type' => 'individual',
                'billing' => [
                    'address' => [
                        'line1' => $data['address_line1'] ?? '123 Main St',
                        'city' => $data['city'] ?? 'New York',
                        'state' => $data['state'] ?? 'NY',
                        'postal_code' => $data['postal_code'] ?? '10001',
                        'country' => $data['country'] ?? 'US',
                    ],
                ],
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'cardholder_id' => $cardholder->id,
                'status' => 'active',
                'message' => 'Stripe cardholder created.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: cardholder creation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'failed',
                'message' => 'Cardholder creation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function createVirtualCard(array $data): array
    {
        if (! $this->enabled) {
            return $this->pendingResponse('card', 'Stripe Issuing access not confirmed. Card created in provider_pending status.');
        }

        try {
            $params = [
                'cardholder' => $data['cardholder_id'] ?? '',
                'type' => 'virtual',
                'currency' => strtolower($data['currency'] ?? 'usd'),
                'spending_controls' => [
                    'allowed_categories' => $data['allowed_mccs'] ?? [],
                    'spending_limits' => [[
                        'amount' => $this->toMinorUnits((float) ($data['spending_limit'] ?? 0), $data['currency'] ?? 'USD'),
                        'interval' => 'all_time',
                    ]],
                ],
                'metadata' => $data['metadata'] ?? [],
            ];

            if (isset($data['single_use']) && $data['single_use']) {
                $params['type'] = 'virtual';
            }

            $card = IssuingCard::create($params);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'cardholder_id' => $card->cardholder,
                'last4' => $card->last4,
                'brand' => $card->brand,
                'status' => $card->status === 'active' ? 'active' : 'provider_pending',
                'message' => 'Stripe Issuing virtual card created.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: card creation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'failed',
                'message' => 'Card creation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function setSpendingLimit(string $cardId, array $limits): array
    {
        if (! $this->enabled || empty($cardId)) {
            return ['success' => true, 'provider' => $this->providerName(), 'card_id' => $cardId, 'status' => 'provider_pending'];
        }

        try {
            $card = IssuingCard::update($cardId, [
                'spending_controls' => [
                    'spending_limits' => [[
                        'amount' => $this->toMinorUnits((float) ($limits['amount'] ?? 0), $limits['currency'] ?? 'USD'),
                        'interval' => $limits['interval'] ?? 'all_time',
                    ]],
                ],
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'status' => $card->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: spending limit update failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => $e->getMessage()];
        }
    }

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array
    {
        if (! $this->enabled || empty($cardId)) {
            return ['success' => true, 'provider' => $this->providerName(), 'card_id' => $cardId, 'status' => 'provider_pending'];
        }

        try {
            $card = IssuingCard::update($cardId, [
                'spending_controls' => [
                    'allowed_categories' => $merchantRestrictions['allowed_mccs'] ?? [],
                    'blocked_categories' => $merchantRestrictions['blocked_mccs'] ?? [],
                ],
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'status' => $card->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: merchant restriction failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => $e->getMessage()];
        }
    }

    public function authorizeTransaction(array $data): array
    {
        if (! $this->enabled) {
            return [
                'success' => true,
                'provider' => $this->providerName(),
                'transaction_id' => 'STRIPE_ISSUING_PENDING_' . bin2hex(random_bytes(8)),
                'status' => 'provider_pending',
                'message' => 'Stripe Issuing access not confirmed. Transaction pending provider activation.',
            ];
        }

        // Stripe Issuing authorizations are handled via webhooks (issuing_authorization.created)
        // This method is called from the card service when driver initiates purchase
        return [
            'success' => true,
            'provider' => $this->providerName(),
            'transaction_id' => null,
            'status' => 'awaiting_webhook',
            'message' => 'Purchase initiated. Awaiting Stripe Issuing authorization webhook.',
        ];
    }

    public function freezeCard(string $cardId): array
    {
        if (! $this->enabled || empty($cardId)) {
            return ['success' => true, 'provider' => $this->providerName(), 'card_id' => $cardId, 'status' => 'frozen'];
        }

        try {
            $card = IssuingCard::update($cardId, ['status' => 'inactive']);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'status' => 'inactive',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: freeze failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => $e->getMessage()];
        }
    }

    public function closeCard(string $cardId): array
    {
        if (! $this->enabled || empty($cardId)) {
            return ['success' => true, 'provider' => $this->providerName(), 'card_id' => $cardId, 'status' => 'closed'];
        }

        try {
            $card = IssuingCard::update($cardId, ['status' => 'canceled']);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'status' => 'canceled',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: close failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => $e->getMessage()];
        }
    }

    public function retrieveCardTransaction(string $transactionId): array
    {
        if (! $this->enabled || empty($transactionId)) {
            return [
                'success' => true,
                'provider' => $this->providerName(),
                'transaction_id' => $transactionId,
                'status' => 'provider_pending',
            ];
        }

        try {
            $transaction = \Stripe\Issuing\Transaction::retrieve($transactionId);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => ($transaction->amount ?? 0) / 100,
                'currency' => strtoupper($transaction->currency ?? 'usd'),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: transaction retrieve failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => $e->getMessage()];
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function hasIssuingAccess(): bool
    {
        $issuingProvider = config('urban_goodz_payments.issuing.provider', 'manual');

        return $issuingProvider === 'stripe';
    }

    private function pendingResponse(string $type, string $message): array
    {
        return [
            'success' => true,
            'provider' => $this->providerName(),
            "{$type}_id" => 'PENDING_' . strtoupper($type) . '_' . bin2hex(random_bytes(8)),
            'last4' => null,
            'brand' => null,
            'status' => 'provider_pending',
            'message' => $message,
        ];
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[strtoupper($currency)] ?? 2;

        return (int) round($amount * pow(10, $exponent));
    }
}
