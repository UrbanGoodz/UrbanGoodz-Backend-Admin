<?php

namespace App\Contracts\Payments;

use App\Models\OrderAnywhereRequest;

interface PaymentGatewayInterface
{
    public function providerName(): string;

    public function isEnabled(): bool;

    public function createPaymentLink(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array;

    public function authorize(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array;

    public function capture(OrderAnywhereRequest $request, float $amount, string $currency, string $reference): array;

    public function refund(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array;

    public function cancel(OrderAnywhereRequest $request, ?string $reference = null): array;

    public function validateWebhook(array|string $payload, array $headers = []): bool;

    public function parseWebhook(array|string $payload, array $headers = []): array;

    public function retrieveTransaction(string $providerReference): array;
}
