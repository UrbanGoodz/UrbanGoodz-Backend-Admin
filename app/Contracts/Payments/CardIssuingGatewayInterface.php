<?php

namespace App\Contracts\Payments;

interface CardIssuingGatewayInterface
{
    public function providerName(): string;

    public function isEnabled(): bool;

    public function createCardholder(array $data): array;

    public function createVirtualCard(array $data): array;

    /**
     * Resolve an uncertain create attempt without exposing provider payloads.
     */
    public function findCardByIdempotencyIdentity(string $identity): array;

    /**
     * Exchange an Issuing Elements nonce for a short-lived provider token.
     * The implementation must never return PAN or CVC through this method.
     */
    public function createSecureRevealSession(string $cardId, string $nonce): array;

    public function setSpendingLimit(string $cardId, array $limits): array;

    public function restrictMerchant(string $cardId, array $merchantRestrictions): array;

    public function authorizeTransaction(array $data): array;

    public function freezeCard(string $cardId): array;

    public function closeCard(string $cardId): array;

    public function retrieveCardTransaction(string $transactionId): array;

    /**
     * Return only non-sensitive lifecycle state for a provider card.
     */
    public function retrieveCardStatus(string $cardId): array;
}
