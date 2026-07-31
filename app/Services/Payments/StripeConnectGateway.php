<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class StripeConnectGateway
{
    private string $secret;
    private string $version;

    public function __construct()
    {
        $this->secret = (string) config('urban_goodz_payments.stripe.secret_key');
        $this->version = (string) config(
            'urban_goodz_payments.stripe.connect_api_version',
            '2026-02-25.clover'
        );
    }

    public function createRecipientAccount(array $owner, string $idempotencyKey): array
    {
        return $this->v2()->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/v2/core/accounts', [
                'contact_email' => $owner['email'],
                'display_name' => $owner['display_name'],
                'dashboard' => 'express',
                'identity' => [
                    'country' => strtolower($owner['country']),
                    'entity_type' => $owner['entity_type'],
                ],
                'configuration' => [
                    'recipient' => [
                        'capabilities' => [
                            'stripe_balance' => [
                                'stripe_transfers' => ['requested' => true],
                            ],
                        ],
                    ],
                ],
                'defaults' => [
                    'currency' => strtolower($owner['currency']),
                    'responsibilities' => [
                        'fees_collector' => 'application',
                        'losses_collector' => 'application',
                    ],
                ],
                'metadata' => [
                    'urban_goodz_owner_role' => $owner['role'],
                    'urban_goodz_owner_id' => (string) $owner['id'],
                ],
                'include' => [
                    'configuration.recipient', 'requirements',
                    'future_requirements', 'defaults',
                ],
            ])->throw()->json();
    }

    public function retrieveAccount(string $accountId): array
    {
        return $this->v2()->get("/v2/core/accounts/{$accountId}", [
            'include' => [
                'configuration.recipient', 'requirements',
                'future_requirements', 'defaults',
            ],
        ])->throw()->json();
    }

    public function createOnboardingLink(
        string $accountId,
        string $returnUrl,
        string $refreshUrl,
        bool $continuation
    ): array {
        $type = $continuation ? 'account_update' : 'account_onboarding';

        return $this->v2()->post('/v2/core/account_links', [
            'account' => $accountId,
            'use_case' => [
                'type' => $type,
                $type => [
                    'configurations' => ['recipient'],
                    'return_url' => $returnUrl,
                    'refresh_url' => $refreshUrl,
                    'collection_options' => [
                        'fields' => 'eventually_due',
                        'future_requirements' => 'include',
                    ],
                ],
            ],
        ])->throw()->json();
    }

    public function createManagementLink(string $accountId): array
    {
        return $this->v1()->asForm()
            ->post("/v1/accounts/{$accountId}/login_links")
            ->throw()->json();
    }

    public function retrieveBalance(string $accountId): array
    {
        return $this->v1($accountId)->get('/v1/balance')->throw()->json();
    }

    public function createTransfer(array $transfer): array
    {
        return $this->v1()->withHeaders(['Idempotency-Key' => $transfer['idempotency_key']])
            ->asForm()->post('/v1/transfers', [
                'amount' => $transfer['amount_cents'],
                'currency' => strtolower($transfer['currency']),
                'destination' => $transfer['destination'],
                'source_transaction' => $transfer['source_transaction'],
                'transfer_group' => $transfer['transfer_group'],
                'metadata' => [
                    'urban_goodz_transfer_id' => (string) $transfer['local_id'],
                    'urban_goodz_settlement_id' => (string) $transfer['settlement_id'],
                ],
            ])->throw()->json();
    }

    public function reverseTransfer(
        string $transferId,
        int $amountCents,
        string $idempotencyKey,
        array $metadata
    ): array {
        return $this->v1()->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()->post("/v1/transfers/{$transferId}/reversals", [
                'amount' => $amountCents,
                'metadata' => $metadata,
            ])->throw()->json();
    }

    private function v1(?string $connectedAccount = null): PendingRequest
    {
        $request = $this->base()->withHeaders(['Stripe-Version' => $this->version]);

        return $connectedAccount
            ? $request->withHeaders(['Stripe-Account' => $connectedAccount])
            : $request;
    }

    private function v2(): PendingRequest
    {
        return $this->base()->withHeaders([
            'Stripe-Version' => $this->version,
            'Content-Type' => 'application/json',
        ]);
    }

    private function base(): PendingRequest
    {
        if (config('urban_goodz_payments.mode') !== 'sandbox') {
            throw new LogicException('Stripe Connect payout operations are sandbox-only until certified.');
        }
        if (! str_starts_with($this->secret, 'sk_test_')) {
            throw new LogicException('A Stripe test secret is required for Connect payout operations.');
        }

        return Http::baseUrl('https://api.stripe.com')
            ->acceptJson()
            ->withToken($this->secret)
            ->retry(2, 200, function ($exception): bool {
                return method_exists($exception, 'response')
                    && ($exception->response?->serverError() ?? false);
            }, throw: false)
            ->throw(function ($response): void {
                $code = data_get($response->json(), 'error.code', 'stripe_connect_error');
                throw new RuntimeException("Stripe Connect request failed ({$response->status()}): {$code}");
            });
    }
}
