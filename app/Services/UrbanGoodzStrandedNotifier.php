<?php

namespace App\Services;

use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;

/**
 * Every Urban Goodz Stranded notification, in one place.
 *
 * Both sides of a rescue are anxious: the customer is stuck somewhere, and the
 * responder has committed to driving to them. Silence is the worst outcome for
 * either, so each state change notifies whoever is waiting on it.
 *
 * Two rules hold throughout:
 *
 *  - Delivery never breaks the caller. Every send is wrapped; a Firebase
 *    outage must not turn a completed rescue into a failed API call.
 *  - Sends happen after the caller's transaction has committed, never inside
 *    it, so nobody is told about an assignment that later rolls back.
 *
 * Goodz Samaritans are community members and are notified as customers;
 * professional outfits are vendors. `audienceFor()` is the only place that
 * distinction is made.
 */
class UrbanGoodzStrandedNotifier
{
    public function __construct(private readonly UrbanGoodzNotificationService $notifications)
    {
    }

    // ---------------------------------------------------------------- broadcast

    /** A request has gone out to a responder. */
    public function requestBroadcast(UrbanGoodzStrandedRequest $r, UrbanGoodzStrandedOffer $offer): void
    {
        $distance = $offer->distance_miles !== null
            ? number_format((float) $offer->distance_miles, 1) . ' miles away'
            : 'Nearby';

        $this->toResponder($offer, 'Someone nearby needs help',
            $this->serviceLabel($r) . ' · ' . $distance . ($r->is_emergency ? ' · marked unsafe' : ''),
            $r, ['event' => 'request_broadcast', 'expires_at' => $offer->expires_at?->toIso8601String()]);
    }

    /** The community window lapsed and professionals are now being offered. */
    public function escalatedToProfessionals(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'Bringing in professional help',
            'No Goodz Samaritan was available, so we are contacting professional providers near you.',
            ['event' => 'escalated_professional']);
    }

    /** Nobody was reachable, even after the radius ladder was exhausted. */
    public function noRespondersFound(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'We could not reach anyone yet',
            'Nobody is available right now. You can widen your search or cancel for a refund of your help request fee.',
            ['event' => 'no_responders']);
    }

    // ---------------------------------------------------------------- selection

    /** A responder accepted; the customer now has someone to choose. */
    public function responderAccepted(UrbanGoodzStrandedRequest $r, UrbanGoodzStrandedOffer $offer): void
    {
        $eta = $offer->eta_minutes !== null ? $offer->eta_minutes . ' min away' : 'On the way';
        $terms = match ($offer->response_mode) {
            UrbanGoodzStrandedOffer::MODE_VOLUNTEER => 'Volunteering, no charge',
            UrbanGoodzStrandedOffer::MODE_TIPS_ONLY => 'Tips appreciated',
            default => 'Requested payment listed',
        };

        $this->toCustomer($r, 'Someone can help',
            'A responder is available · ' . $eta . ' · ' . $terms,
            ['event' => 'responder_accepted', 'offer_id' => $offer->getKey()]);
    }

    public function responderSelected(UrbanGoodzStrandedRequest $r, UrbanGoodzStrandedOffer $offer): void
    {
        $this->toResponder($offer, 'You have been selected',
            'You were chosen to help with ' . $r->request_number . '. Navigate to the customer to begin.',
            $r, ['event' => 'selected']);

        $this->toCustomer($r, 'Help is on the way',
            'Your responder has been confirmed and is heading to you now.',
            ['event' => 'provider_assigned']);
    }

    public function responderPassedOver(UrbanGoodzStrandedRequest $r, UrbanGoodzStrandedOffer $offer): void
    {
        $this->toResponder($offer, 'Another responder was selected',
            'Thanks for offering to help with ' . $r->request_number . '. The customer chose someone else this time.',
            $r, ['event' => 'passed_over']);
    }

    // ---------------------------------------------------------------- en route

    public function responderEnRoute(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'Your responder is on the way',
            'They have started travelling to your location. You can track them live.',
            ['event' => 'provider_en_route']);
    }

    public function responderNearby(UrbanGoodzStrandedRequest $r, ?int $minutesAway = null): void
    {
        $this->toCustomer($r, 'Your responder is nearby',
            $minutesAway !== null
                ? 'Arriving in about ' . $minutesAway . ' minutes.'
                : 'They are approaching your location.',
            ['event' => 'provider_nearby']);
    }

    public function responderDelayed(UrbanGoodzStrandedRequest $r, ?string $reason = null): void
    {
        $this->toCustomer($r, 'Your responder is running late',
            $reason ?: 'They have been delayed but are still on the way.',
            ['event' => 'provider_delayed']);
    }

    public function responderArrived(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'Your responder has arrived',
            'They are at your location now.',
            ['event' => 'provider_arrived']);
    }

    // ---------------------------------------------------------------- service

    public function serviceStarted(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'Work has started',
            $this->serviceLabel($r) . ' is underway.',
            ['event' => 'service_started']);
    }

    public function serviceCompleted(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'Service complete',
            'Please confirm the work is done so your responder can be paid.',
            ['event' => 'service_completed']);
    }

    /** The customer confirmed, which is what releases escrow. */
    public function completionConfirmed(UrbanGoodzStrandedRequest $r, ?UrbanGoodzStrandedOffer $offer): void
    {
        if ($offer) {
            $paid = $offer->payableAmountMinor() > 0;
            $this->toResponder($offer, 'Job confirmed',
                $paid
                    ? 'The customer confirmed the work. Your payment has been released.'
                    : 'The customer confirmed the work. Thank you for helping out.',
                $r, ['event' => 'completion_confirmed']);
        }

        $this->toCustomer($r, 'Your receipt is ready',
            'Thanks for using Urban Goodz Stranded. Your receipt is available, and you can rate your responder.',
            ['event' => 'receipt_available']);
    }

    public function rateResponderReminder(UrbanGoodzStrandedRequest $r): void
    {
        $this->toCustomer($r, 'How did it go?',
            'Rate your responder to help the community find trustworthy help.',
            ['event' => 'rate_provider']);
    }

    // ---------------------------------------------------------------- money

    public function paymentConfirmed(UrbanGoodzStrandedRequest $r, ?UrbanGoodzStrandedOffer $offer): void
    {
        if ($offer) {
            $this->toResponder($offer, 'Payment confirmed',
                'Payment for ' . $r->request_number . ' has cleared and is held securely until the job is confirmed.',
                $r, ['event' => 'payment_confirmed']);
        }

        $this->toCustomer($r, 'Payment confirmed',
            'Your payment has been received. We are finding help now.',
            ['event' => 'payment_confirmed']);
    }

    // ---------------------------------------------------------------- changes

    public function requestUpdatedByCustomer(UrbanGoodzStrandedRequest $r, ?UrbanGoodzStrandedOffer $offer): void
    {
        if (!$offer) {
            return;
        }

        $this->toResponder($offer, 'The customer updated their request',
            'Details for ' . $r->request_number . ' have changed. Check the latest before you arrive.',
            $r, ['event' => 'customer_updated']);
    }

    public function destinationChanged(UrbanGoodzStrandedRequest $r, ?UrbanGoodzStrandedOffer $offer): void
    {
        if (!$offer) {
            return;
        }

        $this->toResponder($offer, 'Destination changed',
            'The drop-off location for ' . $r->request_number . ' has changed. Re-check your navigation.',
            $r, ['event' => 'navigation_changed']);
    }

    public function cancelledByCustomer(UrbanGoodzStrandedRequest $r, ?UrbanGoodzStrandedOffer $offer): void
    {
        if (!$offer) {
            return;
        }

        $this->toResponder($offer, 'Request cancelled',
            'The customer cancelled ' . $r->request_number . '. You can stand down.',
            $r, ['event' => 'customer_cancelled']);
    }

    // ---------------------------------------------------------------- plumbing

    private function serviceLabel(UrbanGoodzStrandedRequest $r): string
    {
        return $r->service?->name ?: 'Roadside assistance';
    }

    private function toCustomer(UrbanGoodzStrandedRequest $r, string $title, string $body, array $extra = []): void
    {
        if (!$r->user_id) {
            return;
        }

        $this->send(fn () => $this->notifications->notifyCustomer(
            (int) $r->user_id, $title, $body, $this->payload($r, $extra)
        ));
    }

    private function toResponder(UrbanGoodzStrandedOffer $offer, string $title, string $body, UrbanGoodzStrandedRequest $r, array $extra = []): void
    {
        $responderId = (int) $offer->responder_id;
        if ($responderId <= 0) {
            return;
        }

        $payload = $this->payload($r, $extra + ['offer_id' => $offer->getKey()]);

        $this->send(function () use ($offer, $responderId, $title, $body, $payload) {
            if ($offer->responder_type === 'samaritan') {
                $this->notifications->notifyCustomer($responderId, $title, $body, $payload);
                return;
            }
            $this->notifications->notifyVendor($responderId, $title, $body, $payload);
        });
    }

    private function payload(UrbanGoodzStrandedRequest $r, array $extra): array
    {
        return array_merge([
            'type' => 'stranded_request',
            'request_uuid' => $r->uuid,
            'request_number' => $r->request_number,
            'status' => $r->status,
        ], $extra);
    }

    /** A failed notification is reported, never surfaced to the caller. */
    private function send(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
