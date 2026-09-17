<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Domain\Stranded\Payments\UrbanGoodzStripeConnectService;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzWebhookEvent;
use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Stripe tells this app when a responder's Connect account changes --
 * onboarding submitted, a capability approved or restricted, a requirement
 * newly due. charges_enabled/payouts_enabled/details_submitted only exist
 * here, on the account object itself; nothing this platform does locally can
 * infer them, so this webhook is the only place
 * UrbanGoodzStrandedResponder::canReceivePayouts() ever becomes true.
 *
 * Same event-dedup shape as StripeIssuingWebhookController: a unique
 * (provider, event_id) row in urban_goodz_webhook_events, written before any
 * side effect, so a Stripe retry of the same event is a no-op rather than a
 * second write.
 */
class UrbanGoodzStrandedConnectWebhookController extends Controller
{
    public function handle(Request $request, UrbanGoodzStripeConnectService $connect): JsonResponse
    {
        $secret = (string) config('urban_goodz_payments.stripe.connect_webhook_secret', '');
        if ($secret === '') {
            return response()->json(['received' => false, 'message' => 'Connect webhook is not configured.'], 503);
        }

        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret);
        } catch (\UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['received' => false], 400);
        }

        $eventId = (string) $event->id;
        $eventType = (string) $event->type;
        $object = $event->data->object;
        $accountId = (string) ($object->id ?? '');

        $responder = $accountId
            ? UrbanGoodzStrandedResponder::where('stripe_connect_account_id', $accountId)->first()
            : null;

        try {
            $receipt = UrbanGoodzWebhookEvent::create([
                'provider' => 'stripe',
                'event_id' => $eventId,
                'event_type' => $eventType,
                'internal_reference' => $accountId,
                'payable_type' => UrbanGoodzStrandedResponder::class,
                'payable_id' => $responder?->getKey(),
                'idempotency_key' => "webhook_event:stripe_connect:{$eventId}",
                'received_at' => now(),
                'status' => 'processing',
            ]);
        } catch (QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }

            // Already recorded -- Stripe retries deliveries until it gets a
            // 2xx, so seeing the same event id again is routine, not an error.
            return response()->json(['received' => true, 'already_processed' => true]);
        }

        try {
            if ($eventType === 'account.updated') {
                $connect->syncFromAccountObject((array) json_decode(json_encode($object), true));
            }

            $receipt->update(['status' => 'succeeded', 'processed_at' => now()]);
            return response()->json(['received' => true]);
        } catch (\Throwable $e) {
            $receipt->update([
                'status' => 'failed',
                'failure_type' => class_basename($e),
                'processed_at' => now(),
            ]);
            report($e);
            return response()->json(['received' => false], 500);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->getMessage());

        return $driverCode === 1062
            || $sqlState === '23505'
            || ($driverCode === 19 && str_contains($message, 'unique'))
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }
}
