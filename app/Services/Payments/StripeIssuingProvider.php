<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CardIssuingGatewayInterface;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Issuing\Card as IssuingCard;
use Stripe\Issuing\Cardholder;
use Stripe\EphemeralKey;
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
            $cardholderId = (string) ($data['existing_cardholder_id'] ?? '');
            if ($cardholderId === '') {
                return [
                    'success' => false,
                    'provider' => $this->providerName(),
                    'status' => 'failed',
                    'error_code' => 'verified_cardholder_mapping_missing',
                    'message' => 'A verified Stripe Issuing cardholder mapping is required.',
                ];
            }
            $cardholder = Cardholder::retrieve($cardholderId);
            $pastDue = (array) data_get($cardholder, 'requirements.past_due', []);
            if (($cardholder->status ?? null) !== 'active'
                || data_get($cardholder, 'requirements.disabled_reason')
                || $pastDue !== []) {
                return [
                    'success' => false,
                    'provider' => $this->providerName(),
                    'status' => 'failed',
                    'error_code' => 'cardholder_not_verified',
                    'message' => 'The mapped Stripe Issuing cardholder is not active and verified.',
                ];
            }

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'cardholder_id' => $cardholder->id,
                'status' => 'active',
                'message' => 'Verified Stripe cardholder confirmed.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Issuing: cardholder creation failed', [
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'failed',
                'error_code' => $e->getStripeCode() ?: 'stripe_cardholder_creation_failed',
                'message' => 'Stripe could not create the cardholder.',
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
                'currency' => 'usd',
                'status' => 'active',
                'lifecycle_controls' => [
                    'cancel_after' => [
                        'payment_count' => 1,
                    ],
                ],
                'spending_controls' => [
                    'allowed_categories' => $data['allowed_mccs'] ?? [],
                    'allowed_card_presences' => $data['allowed_card_presences'] ?? ['not_present'],
                    'allowed_merchant_countries' => $data['allowed_merchant_countries'] ?? ['US'],
                    'spending_limits' => [[
                        'amount' => $this->toMinorUnits((float) ($data['spending_limit'] ?? 0), $data['currency'] ?? 'USD'),
                        'interval' => 'all_time',
                    ], [
                        'amount' => $this->toMinorUnits((float) ($data['spending_limit'] ?? 0), $data['currency'] ?? 'USD'),
                        'interval' => 'per_authorization',
                    ]],
                ],
                'metadata' => $data['metadata'] ?? [],
            ];

            $card = IssuingCard::create($params, [
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'stripe_version' => config(
                    'urban_goodz_payments.issuing.stripe_api_version',
                    '2026-06-24.dahlia'
                ),
            ]);

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
            Log::error('Stripe Issuing: card creation failed', [
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'failed',
                'error_code' => $e->getStripeCode() ?: 'stripe_card_creation_failed',
                'message' => 'Stripe could not create the virtual card.',
            ];
        }
    }

    public function findCardByIdempotencyIdentity(string $identity): array
    {
        if (! $this->enabled || $identity === '') {
            return ['success' => false, 'provider' => $this->providerName(), 'status' => 'not_found'];
        }

        try {
            $cards = IssuingCard::all(['limit' => 100]);
            foreach ($cards->data as $card) {
                if (($card->metadata->idempotency_identity ?? null) === $identity) {
                    return [
                        'success' => true,
                        'provider' => $this->providerName(),
                        'card_id' => $card->id,
                        'cardholder_id' => is_string($card->cardholder)
                            ? $card->cardholder
                            : ($card->cardholder->id ?? null),
                        'last4' => $card->last4,
                        'brand' => $card->brand,
                        'status' => $card->status,
                    ];
                }
            }
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Issuing idempotency lookup failed', [
                'error_code' => $e->getStripeCode(),
            ]);
        }

        return ['success' => false, 'provider' => $this->providerName(), 'status' => 'not_found'];
    }

    public function createSecureRevealSession(string $cardId, string $nonce): array
    {
        if (! $this->enabled || $cardId === '' || $nonce === '') {
            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'unavailable',
                'message' => 'Stripe Issuing secure reveal is not available.',
            ];
        }

        try {
            $key = EphemeralKey::create([
                'nonce' => $nonce,
                'issuing_card' => $cardId,
            ], [
                'stripe_version' => config(
                    'urban_goodz_payments.issuing.stripe_api_version',
                    '2026-02-25.clover'
                ),
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'ephemeral_key_secret' => $key->secret,
                'expires_at' => now()->addMinutes(15)->toISOString(),
            ];
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Issuing secure reveal session failed', [
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'failed',
                'message' => 'Secure reveal session could not be created.',
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
            Log::error('Stripe Issuing: spending limit update failed', ['error_code' => $e->getStripeCode()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => 'Spending limit update failed.'];
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
            Log::error('Stripe Issuing: merchant restriction failed', ['error_code' => $e->getStripeCode()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => 'Merchant restriction update failed.'];
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
            Log::error('Stripe Issuing: freeze failed', ['error_code' => $e->getStripeCode()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => 'Provider card freeze failed.'];
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
            Log::error('Stripe Issuing: close failed', ['error_code' => $e->getStripeCode()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => 'Provider card cancellation failed.'];
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
            Log::error('Stripe Issuing: transaction retrieve failed', ['error_code' => $e->getStripeCode()]);

            return ['success' => false, 'provider' => $this->providerName(), 'message' => 'Provider transaction retrieval failed.'];
        }
    }

    public function retrieveCardStatus(string $cardId): array
    {
        if (! $this->enabled || $cardId === '') {
            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'unavailable',
            ];
        }

        try {
            $card = IssuingCard::retrieve($cardId);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'card_id' => $card->id,
                'status' => $card->status,
            ];
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Issuing card-status retrieval failed', [
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'status' => 'unavailable',
            ];
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function hasIssuingAccess(): bool
    {
        $issuingProvider = config('urban_goodz_payments.issuing.provider', 'manual');

        $secret = (string) config('urban_goodz_payments.stripe.secret_key', '');

        return $issuingProvider === 'stripe'
            && config('urban_goodz_payments.issuing.mode') === 'sandbox'
            && str_starts_with($secret, 'sk_test_');
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
