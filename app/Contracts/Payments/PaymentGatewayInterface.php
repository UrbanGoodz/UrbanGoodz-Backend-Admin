<?php

namespace App\Contracts\Payments;

use App\Contracts\Payments\PayableRequest;
use App\Models\OrderAnywhereRequest;

interface PaymentGatewayInterface
{
    public function providerName(): string;

    public function isEnabled(): bool;

    public function createPaymentLink(PayableRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array;

    public function authorize(PayableRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array;

    public function capture(PayableRequest $request, float $amount, string $currency, string $reference): array;

    public function refund(PayableRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array;

    public function cancel(PayableRequest $request, ?string $reference = null): array;

    public function validateWebhook(array|string $payload, array $headers = []): bool;

    public function parseWebhook(array|string $payload, array $headers = []): array;

    public function retrieveTransaction(string $providerReference): array;
}
