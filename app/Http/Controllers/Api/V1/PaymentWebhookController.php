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
        $allowedProviders = ['adyen', 'stripe'];

        if (! in_array($provider, $allowedProviders, true)) {
            Log::warning('Payment webhook received for unknown provider', ['provider' => $provider]);

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

        match ($provider) {
            'adyen' => $this->processAdyenEvent($eventCode, $success, $providerReference, $amount, $currency, $requestModel, $payments),
            'stripe' => $this->processStripeEvent($eventCode, $success, $providerReference, $amount, $currency, $requestModel, $payments),
            default => null,
        };

        return 'handled';
    }

    private function processAdyenEvent(
        string $eventCode,
        bool $success,
        ?string $pspReference,
        float $amount,
        string $currency,
        OrderAnywhereRequest $request,
        UrbanGoodzPaymentService $payments
    ): void {
        match ($eventCode) {
            'AUTHORISATION' => $success
                ? $payments->authorizeOrderAnywhere($request, [
                    'authorized_amount' => $amount,
                    'authorization_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, 'authorization_failed', $pspReference, $amount),
            'CAPTURE' => $success
                ? $payments->captureOrderAnywhere($request, [
                    'captured_amount' => $amount,
                    'capture_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, 'capture_failed', $pspReference, $amount),
            'CAPTURE_FAILED' => $this->markFailed($request, 'capture_failed', $pspReference, $amount),
            'REFUND' => $success
                ? $payments->refundOrderAnywhere($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : $this->markFailed($request, 'refund_failed', $pspReference, $amount),
            'REFUND_FAILED' => $this->markFailed($request, 'refund_failed', $pspReference, $amount),
            'CANCELLATION' => $success && in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? $request->transitionTo('cancelled')
                : null,
            'CANCEL_OR_REFUND' => $success && $request->payment_status === 'captured'
                ? $payments->refundOrderAnywhere($request, [
                    'refund_amount' => $amount,
                    'refund_reference' => $pspReference,
                    'psp_reference' => $pspReference,
                    'source' => 'webhook',
                ])
                : ($success ? $request->transitionTo('cancelled') : null),
            default => null,
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
    ): void {
        match ($eventCode) {
            'checkout.session.completed',
            'payment_intent.succeeded',
            'charge.succeeded' => $payments->captureOrderAnywhere($request, [
                'captured_amount' => $amount,
                'capture_reference' => $providerReference,
                'psp_reference' => $providerReference,
                'source' => 'webhook',
            ]),
            'payment_intent.payment_failed',
            'charge.failed' => $this->markFailed($request, 'payment_failed', $providerReference, $amount),
            'payment_intent.canceled' => in_array($request->status, ['pending_review', 'reviewing', 'quote_needed'])
                ? $request->transitionTo('cancelled')
                : null,
            'charge.refunded',
            'refund.succeeded' => $payments->refundOrderAnywhere($request, [
                'refund_amount' => $amount,
                'refund_reference' => $providerReference,
                'psp_reference' => $providerReference,
                'source' => 'webhook',
            ]),
            'refund.failed' => $this->markFailed($request, 'refund_failed', $providerReference, $amount),
            'charge.dispute.created' => $request->update([
                'payment_status' => 'disputed',
                'metadata' => array_merge($request->metadata ?? [], [
                    'disputed_at' => now()->toISOString(),
                    'provider_reference' => $providerReference,
                ]),
            ]),
            default => null,
        };
    }

    private function markFailed(OrderAnywhereRequest $request, string $status, ?string $providerReference, float $amount): void
    {
        $request->update([
            'payment_status' => $status,
            'metadata' => array_merge($request->metadata ?? [], [
                "{$status}_at" => now()->toISOString(),
                'provider_reference' => $providerReference,
            ]),
        ]);
        $request->logPaymentEvent($status, $amount, $providerReference, ['source' => 'webhook']);
    }

    private function findRequestByReference(?string $merchantReference, ?string $providerReference): ?OrderAnywhereRequest
    {
        if (! $merchantReference && ! $providerReference) {
            return null;
        }

        $query = OrderAnywhereRequest::query();

        if ($merchantReference) {
            $query->where('request_number', $merchantReference)
                ->orWhere('merchant_reference', $merchantReference);
        }

        if ($providerReference) {
            $query->orWhere('psp_reference', $providerReference)
                ->orWhere('payment_link_id', $providerReference)
                ->orWhere('authorization_reference', $providerReference)
                ->orWhere('capture_reference', $providerReference)
                ->orWhere('refund_reference', $providerReference);
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
