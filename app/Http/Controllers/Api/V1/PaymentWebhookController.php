<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Services\Payments\PaymentProviderManager;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    private PaymentProviderManager $providerManager;

    public function __construct(PaymentProviderManager $providerManager)
    {
        $this->providerManager = $providerManager;
    }

    public function handle(Request $request, string $provider, UrbanGoodzPaymentService $payments): Response
    {
        $allowedProviders = ['adyen', 'stripe', 'staged_test'];

        if (! in_array($provider, $allowedProviders, true)) {
            Log::warning('Payment webhook received for unknown provider', ['provider' => $provider]);

            return response('OK', 200);
        }

        if ($this->providerManager->isDisabled()) {
            Log::warning('Payment webhook received but payments are globally disabled', ['provider' => $provider]);

            return response('OK', 200);
        }

        $gateway = $this->providerManager->resolveProvider($provider);

        if (! $gateway->isEnabled()) {
            Log::warning('Payment webhook received for disabled provider', ['provider' => $provider]);

            return response('OK', 200);
        }

        $payload = $this->extractPayload($request, $provider);
        $headers = $this->extractHeaders($request, $provider);

        if (! $gateway->validateWebhook($payload, $headers)) {
            Log::warning("{$provider} webhook validation failed", [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response('OK', 200);
        }

        $events = $gateway->parseWebhook($payload, $headers);

        $handled = [];
        $failed = [];

        foreach ($events as $event) {
            $eventCode = $event['event_code'] ?? '';
            $merchantReference = $event['merchant_reference'] ?? null;
            $providerReference = $event['provider_reference'] ?? null;

            $requestModel = $this->findRequestByReference($merchantReference, $providerReference);

            if (! $requestModel) {
                Log::warning("{$provider} webhook: no matching OrderAnywhereRequest found", [
                    'merchant_reference' => $merchantReference,
                    'provider_reference' => $providerReference,
                    'event_code' => $eventCode,
                ]);
                $failed[] = ['event' => $eventCode, 'reason' => 'unmatched'];
                continue;
            }

            // Webhook idempotency protection: check if event has already been recorded in payment ledgers
            $eventType = $this->mapWebhookEventToLedgerType($eventCode, $provider, (bool) ($event['success'] ?? false));
            if ($eventType) {
                $existingLedger = \App\Models\UrbanGoodzPaymentLedger::where('payable_type', OrderAnywhereRequest::class)
                    ->where('payable_id', $requestModel->id)
                    ->where('event_type', $eventType)
                    ->where('reference', $providerReference)
                    ->first();
                if ($existingLedger) {
                    Log::info("Webhook event {$eventCode} with reference {$providerReference} already processed. Skipping.");
                    $handled[] = $eventCode;
                    continue;
                }
            }

            $result = $this->processEvent($event, $requestModel, $gateway, $payments);

            if ($result === 'unhandled') {
                $failed[] = ['event' => $eventCode, 'reason' => 'unhandled_code'];
                Log::info("{$provider} webhook: unhandled event code", ['event_code' => $eventCode]);
            } else {
                $handled[] = $eventCode;
            }
        }

        Log::info("{$provider} webhook batch processed", [
            'handled' => $handled,
            'failed' => $failed,
            'total' => count($events),
        ]);

        return response('OK', 200);
    }

    private function mapWebhookEventToLedgerType(string $eventCode, string $provider, bool $success): ?string
    {
        if ($provider === 'adyen' || $provider === 'staged_test') {
            return match ($eventCode) {
                'AUTHORISATION' => $success ? 'authorization' : 'authorization_failed',
                'CAPTURE' => $success ? 'capture' : 'capture_failed',
                'REFUND' => $success ? 'refund' : 'refund_failed',
                'CANCEL_OR_REFUND' => $success ? 'refund' : null,
                'CAPTURE_FAILED' => 'capture_failed',
                'REFUND_FAILED' => 'refund_failed',
                default => null,
            };
        }

        if ($provider === 'stripe') {
            return match ($eventCode) {
                'checkout.session.completed',
                'payment_intent.succeeded',
                'charge.succeeded' => $success ? 'capture' : 'payment_failed',
                'charge.refunded',
                'refund.succeeded' => 'refund',
                'payment_intent.payment_failed',
                'charge.failed' => 'payment_failed',
                'refund.failed' => 'refund_failed',
                default => null,
            };
        }

        return null;
    }

    private function extractPayload(Request $request, string $provider): array|string
    {
        return match ($provider) {
            'stripe' => $request->getContent(),
            default => $request->all(),
        };
    }

    private function extractHeaders(Request $request, string $provider): array
    {
        return match ($provider) {
            'stripe' => [
                'stripe-signature' => $request->header('stripe-signature', ''),
            ],
            default => [],
        };
    }

    private function processEvent(array $event, OrderAnywhereRequest $requestModel, PaymentGatewayInterface $gateway, UrbanGoodzPaymentService $payments): string
    {
        $provider = $gateway->providerName();
        $eventCode = $event['event_code'] ?? '';
        $success = $event['success'] ?? false;
        $providerReference = $event['provider_reference'] ?? null;
        $amountMinor = $event['amount_minor'] ?? 0;
        $currency = $event['currency'] ?? 'USD';
        $amount = $this->fromMinorUnits($amountMinor, $currency);

        $requestModel->logPaymentEvent("webhook.{$eventCode}", $amount, $providerReference, [
            'source' => 'webhook',
            'provider' => $provider,
            'success' => $success,
        ]);

        $handled = match ($provider) {
            'adyen', 'staged_test' => $this->processAdyenEvent($eventCode, $success, $providerReference, $amount, $currency, $provider, $requestModel, $payments),
            'stripe' => $this->processStripeEvent($eventCode, $success, $providerReference, $amount, $currency, $requestModel, $payments),
            default => false,
        };

        return $handled ? 'handled' : 'unhandled';
    }

    private function processAdyenEvent(
        string $eventCode,
        bool $success,
        ?string $pspReference,
        float $amount,
        string $currency,
        string $provider,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments
    ): bool {
        return match ($eventCode) {
            'AUTHORISATION' => $success
                ? (bool) $payments->authorizeCustomerPayment($request, [
                    'authorized_amount' => $amount,
                    'authorization_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, $payments, 'authorization_failed', $pspReference, $amount, $provider),
            'CAPTURE' => $success
                ? $this->captureFromWebhook($request, $payments, $amount, $pspReference)
                : $this->markFailed($request, $payments, 'capture_failed', $pspReference, $amount, $provider),
            'CAPTURE_FAILED' => $this->markFailed($request, $payments, 'capture_failed', $pspReference, $amount, $provider),
            'REFUND' => $success
                ? (bool) $payments->refundCustomerPayment($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, $payments, 'refund_failed', $pspReference, $amount, $provider),
            'REFUND_FAILED' => $this->markFailed($request, $payments, 'refund_failed', $pspReference, $amount, $provider),
            'CANCELLATION' => $success && in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? (bool) $request->transitionTo('cancelled')
                : false,
            'CANCEL_OR_REFUND' => $success && $request->payment_status === 'captured'
                ? (bool) $payments->refundCustomerPayment($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : ($success ? (bool) $request->transitionTo('cancelled') : false),
            default => false,
        };
    }

    private function processStripeEvent(
        string $eventCode,
        bool $success,
        ?string $providerReference,
        float $amount,
        string $currency,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments
    ): bool {
        return match ($eventCode) {
            'checkout.session.completed',
            'payment_intent.succeeded',
            'charge.succeeded' => $this->captureFromWebhook($request, $payments, $amount, $providerReference),
            'payment_intent.payment_failed',
            'charge.failed' => $this->markFailed($request, $payments, 'payment_failed', $providerReference, $amount, 'stripe'),
            'payment_intent.canceled' => in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? (bool) $request->transitionTo('cancelled')
                : false,
            'charge.refunded',
            'refund.succeeded' => (bool) $payments->refundCustomerPayment($request, [
                'refund_amount' => $amount,
                'refund_reference' => $providerReference,
                'psp_reference' => $providerReference,
                'source' => 'webhook',
            ]),
            'refund.failed' => $this->markFailed($request, $payments, 'refund_failed', $providerReference, $amount, 'stripe'),
            'charge.dispute.created' => (bool) $request->update([
                'payment_status' => 'disputed',
            ]),
            default => false,
        };
    }

    private function captureFromWebhook(
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        float $amount,
        ?string $providerReference
    ): bool {
        if ($request->payment_status !== 'authorized') {
            $request->update([
                'authorized_amount' => $amount,
                'payment_status' => 'authorized',
                'payment_authorized_at' => now(),
                'authorization_reference' => $providerReference,
                'psp_reference' => $providerReference,
            ]);
        }

        return (bool) $payments->captureCustomerPayment($request->fresh(), [
            'captured_amount' => $amount,
            'capture_reference' => $providerReference,
            'psp_reference' => $providerReference,
            'source' => 'webhook',
        ]);
    }

    private function markFailed(
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments,
        string $status,
        ?string $reference,
        float $amount,
        string $provider
    ): bool {
        $payments->recordWebhookFailure($request, $status, [
            'provider' => $provider,
            'reference' => $reference,
            'amount' => $amount,
        ]);

        return true;
    }

    private function findRequestByReference(?string $merchantReference, ?string $providerReference): ?OrderAnywhereRequest
    {
        if (! $merchantReference && ! $providerReference) {
            return null;
        }

        $query = OrderAnywhereRequest::query();

        if ($merchantReference) {
            $query->where(function ($q) use ($merchantReference) {
                $q->where('request_number', $merchantReference)
                  ->orWhere('merchant_reference', $merchantReference);
            });
        }

        if ($providerReference) {
            $query->orWhere(function ($q) use ($providerReference) {
                $q->where('psp_reference', $providerReference)
                  ->orWhere('payment_link_id', $providerReference)
                  ->orWhere('authorization_reference', $providerReference)
                  ->orWhere('capture_reference', $providerReference)
                  ->orWhere('refund_reference', $providerReference);
            });
        }

        return $query->first();
    }

    private function fromMinorUnits(int $amountMinor, string $currency): float
    {
        $minorUnits = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0, 'KRW' => 0];
        $exponent = $minorUnits[strtoupper($currency)] ?? 2;

        return $amountMinor / pow(10, $exponent);
    }
}
