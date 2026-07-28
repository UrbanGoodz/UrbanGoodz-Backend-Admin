<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Facades\Log;
use LogicException;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private bool $enabled;
    private bool $isLive;
    private string $secretKey;
    private string $webhookSecret;
    private string $captureMethod;
    private string $successUrl;
    private string $cancelUrl;

    public function __construct()
    {
        $config = config('urban_goodz_payments.stripe');
        $this->enabled = $config['enabled'] ?? false;
        $this->isLive = app(PaymentSettings::class)->mode() === 'live_controlled';

        if ($this->isLive && ! empty($config['live_secret_key'])) {
            $this->secretKey = $config['live_secret_key'];
            $this->webhookSecret = $config['live_webhook_secret'] ?? '';
        } else {
            $this->secretKey = $config['secret_key'] ?? '';
            $this->webhookSecret = $config['webhook_secret'] ?? '';
        }

        $this->captureMethod = $config['capture_method'] ?? 'automatic';
        $this->successUrl = $config['success_url'] ?? 'https://localhost/stripe/success';
        $this->cancelUrl = $config['cancel_url'] ?? 'https://localhost/stripe/cancel';

        if ($this->enabled && ! empty($this->secretKey)) {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function providerName(): string
    {
        return 'stripe';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->secretKey);
    }

    public function createPaymentLink(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $returnUrl = null, ?string $description = null): array
    {
        $this->assertConfigured();

        try {
            $amountMinor = $this->toMinorUnits($amount, $currency);

            $params = [
                'mode' => 'payment',
                'success_url' => ($returnUrl ?? $this->successUrl) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->cancelUrl,
                'metadata' => [
                    'urban_goodz_order_anywhere_request_id' => $request->id,
                    'merchant_reference' => $reference,
                    'customer_id' => $request->customer_id,
                    'quoted_amount_minor' => $amountMinor,
                    'environment' => app(PaymentSettings::class)->mode() === 'live_controlled' ? 'live' : 'test',
                    'payment_mode' => app(PaymentSettings::class)->mode(),
                ],
            ];

            if ($this->captureMethod !== 'automatic') {
                $params['payment_intent_data'] = [
                    'capture_method' => 'manual',
                    'metadata' => $params['metadata'],
                ];
            }

            if ($description) {
                $params['line_items'] = [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $amountMinor,
                        'product_data' => [
                            'name' => $description,
                        ],
                    ],
                    'quantity' => 1,
                ]];
            } else {
                $params['line_items'] = [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $amountMinor,
                        'product_data' => [
                            'name' => "Order Anywhere - {$reference}",
                        ],
                    ],
                    'quantity' => 1,
                ]];
            }

            $session = CheckoutSession::create($params);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $session->id,
                'merchant_reference' => $reference,
                'payment_link_id' => $session->id,
                'payment_url' => $session->url,
                'status' => $session->status,
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment link creation failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function authorize(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $context = null): array
    {
        $this->assertConfigured();

        try {
            $sessionId = $context ?? $request->psp_reference ?? $reference;
            $session = CheckoutSession::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent'],
            ]);

            if ($session->payment_status === 'paid') {
                return [
                    'success' => true,
                    'provider' => $this->providerName(),
                    'provider_reference' => $session->payment_intent->id ?? $session->id,
                    'merchant_reference' => $reference,
                    'status' => 'authorized',
                    'amount' => $amount,
                    'currency' => $currency,
                    'staged_test' => false,
                ];
            }

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'provider_reference' => $session->id,
                'merchant_reference' => $reference,
                'status' => 'pending',
                'failure_code' => 'payment_not_completed',
                'failure_message' => 'Checkout session not yet paid',
                'staged_test' => false,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe authorize failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function capture(OrderAnywhereRequest $request, float $amount, string $currency, string $reference): array
    {
        $this->assertConfigured();

        try {
            $paymentIntentId = $request->psp_reference ?? $reference;

            if ($this->captureMethod === 'automatic') {
                $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

                return [
                    'success' => true,
                    'provider' => $this->providerName(),
                    'provider_reference' => $paymentIntent->id,
                    'merchant_reference' => $reference,
                    'status' => 'captured',
                    'amount' => $amount,
                    'currency' => $currency,
                    'staged_test' => false,
                ];
            }

            $amountMinor = $this->toMinorUnits($amount, $currency);

            $paymentIntent = \Stripe\PaymentIntent::capture($paymentIntentId, [
                'amount_to_capture' => $amountMinor,
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $paymentIntent->id,
                'merchant_reference' => $reference,
                'status' => 'captured',
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe capture failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function refund(OrderAnywhereRequest $request, float $amount, string $currency, string $reference, ?string $reason = null): array
    {
        $this->assertConfigured();

        try {
            $paymentIntentId = $request->psp_reference ?? $request->capture_reference ?? $reference;
            $amountMinor = $this->toMinorUnits($amount, $currency);

            $params = [
                'payment_intent' => $paymentIntentId,
                'amount' => $amountMinor,
            ];

            if ($reason) {
                $params['reason'] = match ($reason) {
                    'duplicate' => 'duplicate',
                    'fraudulent' => 'fraudulent',
                    default => 'requested_by_customer',
                };
            }

            $refund = Refund::create($params);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $refund->id,
                'merchant_reference' => $reference,
                'status' => 'refunded',
                'amount' => $amount,
                'currency' => $currency,
                'staged_test' => false,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function cancel(OrderAnywhereRequest $request, ?string $reference = null): array
    {
        $this->assertConfigured();

        try {
            $paymentIntentId = $request->psp_reference ?? $reference;

            if ($paymentIntentId) {
                $paymentIntent = \Stripe\PaymentIntent::cancel($paymentIntentId);

                return [
                    'success' => true,
                    'provider' => $this->providerName(),
                    'provider_reference' => $paymentIntent->id,
                    'merchant_reference' => $reference ?? $request->request_number,
                    'status' => 'canceled',
                    'staged_test' => false,
                ];
            }

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => null,
                'merchant_reference' => $reference ?? $request->request_number,
                'status' => 'canceled',
                'staged_test' => false,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function validateWebhook(array|string $payload, array $headers = []): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Stripe webhook secret not configured; webhook rejected');

            return false;
        }

        $sigHeader = $headers['stripe-signature'] ?? $headers['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($sigHeader)) {
            Log::warning('Stripe webhook missing signature header');

            return false;
        }

        $rawBody = is_string($payload) ? $payload : json_encode($payload);

        try {
            Webhook::constructEvent($rawBody, $sigHeader, $this->webhookSecret);

            return true;
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function parseWebhook(array|string $payload, array $headers = []): array
    {
        $rawBody = is_string($payload) ? $payload : json_encode($payload);
        $event = json_decode($rawBody, true);

        if (! isset($event['type'], $event['data']['object'])) {
            return [];
        }

        $object = $event['data']['object'];

        return [
            [
                'event_id' => $event['id'] ?? null,
                'event_code' => $event['type'],
                'success' => in_array($event['type'], [
                    'checkout.session.completed',
                    'payment_intent.succeeded',
                    'charge.succeeded',
                    'refund.succeeded',
                    'charge.refunded',
                ]),
                'resource_reference' => $object['id'] ?? null,
                'provider_reference' => $object['payment_intent'] ?? $object['id'] ?? null,
                'merchant_reference' => $object['metadata']['merchant_reference'] ?? $object['client_reference_id'] ?? null,
                'amount_minor' => (int) ($event['type'] === 'charge.refunded'
                    ? ($object['amount_refunded'] ?? 0)
                    : ($object['amount_total'] ?? $object['amount'] ?? 0)),
                'currency' => strtoupper($object['currency'] ?? 'usd'),
                'raw' => $object,
            ],
        ];
    }

    public function retrieveTransaction(string $providerReference): array
    {
        if (! $this->enabled || empty($this->secretKey)) {
            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $providerReference,
                'status' => 'unknown',
            ];
        }

        try {
            $session = CheckoutSession::retrieve([
                'id' => $providerReference,
                'expand' => ['payment_intent'],
            ]);

            return [
                'success' => true,
                'provider' => $this->providerName(),
                'provider_reference' => $session->id,
                'status' => $session->payment_status,
                'payment_intent_id' => $session->payment_intent->id ?? null,
                'amount' => ($session->amount_total ?? 0) / 100,
                'currency' => strtoupper($session->currency ?? 'usd'),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe retrieve transaction failed', [
                'provider_reference' => $providerReference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'provider' => $this->providerName(),
                'provider_reference' => $providerReference,
                'status' => 'error',
                'failure_message' => $e->getMessage(),
            ];
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function toMinorUnits(float $amount, string $currency): int
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[strtoupper($currency)] ?? 2;

        return (int) round($amount * pow(10, $exponent));
    }

    // ─── Staged Test Mode Fallbacks ───────────────────────────────────────

    private function assertConfigured(): void
    {
        if (! $this->isEnabled()) {
            throw new LogicException('Stripe is disabled or its secret key is not configured.');
        }
    }
}
