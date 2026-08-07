<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzOrderAnywhereCardEvent;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Services\OrderAnywhereCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeIssuingWebhookController extends Controller
{
    public function handle(Request $request, OrderAnywhereCardService $cards): JsonResponse
    {
        $secret = (string) config('urban_goodz_payments.issuing.stripe_webhook_secret', '');
        if ($secret === '') {
            return response()->json(['received' => false, 'message' => 'Issuing webhook is not configured.'], 503);
        }

        $payload = $request->getContent();
        try {
            $event = Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret);
        } catch (\UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['received' => false], 400);
        }

        $eventId = (string) $event->id;
        $eventType = (string) $event->type;
        $hash = hash('sha256', $payload);
        $object = $event->data->object;
        $cardValue = str_starts_with($eventType, 'issuing_card.')
            ? ($object->id ?? null)
            : ($object->card ?? null);
        $cardId = is_string($cardValue) ? $cardValue : ($cardValue->id ?? null);
        $card = $cardId
            ? UrbanGoodzOrderAnywhereCardRequest::where('provider_card_id', $cardId)->first()
            : null;

        $receipt = UrbanGoodzOrderAnywhereCardEvent::firstOrCreate(
            ['provider' => 'stripe', 'event_id' => $eventId],
            [
                'card_request_id' => $card?->id,
                'event_type' => $eventType,
                'payload_hash' => $hash,
                'safe_metadata' => ['card_request_id' => $card?->id],
            ]
        );
        if (! $receipt->wasRecentlyCreated) {
            if (! hash_equals($receipt->payload_hash, $hash)) {
                return response()->json(['received' => false, 'message' => 'Event identity collision.'], 409);
            }
            return response()->json(['received' => true, 'already_processed' => true]);
        }

        try {
            if ($eventType === 'issuing_authorization.request') {
                $approved = $this->authorizationAllowed($card, $object);
                $receipt->update([
                    'processing_status' => $approved ? 'approved' : 'declined',
                    'processed_at' => now(),
                ]);
                return response()->json([
                    'approved' => $approved,
                    'metadata' => $card ? ['urban_goodz_card_request_id' => (string) $card->id] : [],
                ]);
            }

            if (str_starts_with($eventType, 'issuing_authorization.') && $card) {
                $approved = (bool) ($object->approved ?? false);
                $amount = abs((float) ($object->amount ?? 0)) / 100;
                $cards->recordProviderAuthorization(
                    $card,
                    (string) ($object->id ?? ''),
                    $amount,
                    $approved,
                    $object->merchant_data->name ?? null,
                    $object->merchant_data->category ?? null,
                    (string) ($object->status ?? ($approved ? 'approved' : 'declined'))
                );
            }

            if (str_starts_with($eventType, 'issuing_transaction.') && $card) {
                $amount = abs((float) ($object->amount ?? 0)) / 100;
                $authorizationValue = $object->authorization ?? null;
                $authorizationId = is_string($authorizationValue)
                    ? $authorizationValue
                    : ($authorizationValue->id ?? null);
                if ($amount > 0) {
                    $cards->recordProviderTransaction(
                        $card,
                        (string) $object->id,
                        (string) ($object->type ?? 'capture'),
                        $amount,
                        $authorizationId
                    );
                }
            }

            if ($eventType === 'issuing_card.updated' && $card) {
                $providerStatus = (string) ($object->status ?? 'unknown');
                $card->update([
                    'metadata' => array_merge($card->metadata ?? [], [
                        'provider_card_status' => $providerStatus,
                    ]),
                    'cancelled_at' => $providerStatus === 'canceled'
                        ? ($card->cancelled_at ?? now())
                        : $card->cancelled_at,
                ]);
            }

            $receipt->update(['processing_status' => 'processed', 'processed_at' => now()]);
            return response()->json(['received' => true]);
        } catch (\Throwable $exception) {
            $receipt->update([
                'processing_status' => 'failed',
                'failure_reason' => class_basename($exception),
                'processed_at' => now(),
            ]);
            report($exception);
            return response()->json(['received' => false], 500);
        }
    }

    private function authorizationAllowed(?UrbanGoodzOrderAnywhereCardRequest $card, object $authorization): bool
    {
        if (! $card || ! $card->isUsable()) {
            return false;
        }
        $order = $card->orderAnywhereRequest;
        if (! $order
            || (int) $order->assigned_delivery_man_id !== (int) $card->delivery_man_id
            || in_array($order->status, ['completed', 'rejected', 'cancelled'], true)
            || ! in_array($order->payment_status, ['authorized', 'captured'], true)) {
            return false;
        }
        $amount = abs((float) ($authorization->pending_request->amount ?? $authorization->amount ?? 0)) / 100;
        if ($amount <= 0 || $amount > $card->remainingBalance()) {
            return false;
        }
        $category = $authorization->merchant_data->category ?? null;
        if (! empty($card->allowed_mccs) && (! $category || ! in_array($category, $card->allowed_mccs, true))) {
            return false;
        }
        $merchantName = trim((string) ($authorization->merchant_data->name ?? ''));
        if ($card->allowed_merchant
            && ($merchantName === '' || ! str_contains(
                mb_strtolower($merchantName),
                mb_strtolower(trim($card->allowed_merchant))
            ))) {
            return false;
        }
        return true;
    }
}
