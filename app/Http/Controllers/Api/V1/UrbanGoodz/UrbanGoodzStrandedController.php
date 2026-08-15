<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedService;
use App\Models\UrbanGoodzStrandedVerification;
use App\Models\UrbanGoodzStrandedResponder;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use App\Services\UrbanGoodzStrandedNotifier;
use App\Services\UrbanGoodz\UrbanGoodzStrandedPaymentService;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UrbanGoodzStrandedController extends Controller
{
    public function __construct(private readonly UrbanGoodzStrandedNotifier $notifier)
    {
    }

    /**
     * Help types for the "Find a Goodz Samaritan" flow. Unauthenticated on
     * purpose: the list must show what kinds of help exist before we ask
     * somebody on a shoulder to log in.
     *
     * Deliberately does NOT include a price. Stranded is a community help
     * network, not a priced roadside-services marketplace -- Urban Goodz does
     * not sell "Jump Start - $25". Any amount involved is either the
     * customer's own community reward offer (set per-request, not here) or a
     * responder's own asking price on an offer they choose to make. Neither
     * belongs on the catalogue of problem types.
     */
    public function services(Request $request): JsonResponse
    {
        $services = UrbanGoodzStrandedService::enabled()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (UrbanGoodzStrandedService $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'description' => $s->description,
                'icon' => $s->icon,
                'samaritan_eligible' => $s->samaritan_eligible,
                'typical_duration_minutes' => $s->typical_duration_minutes,
            ]);

        // The publishable key is safe for a client by Stripe's own design --
        // unlike the secret key it authorises nothing on its own, it only lets
        // the SDK tokenise a card into a payment_method id this app never
        // sees the number of. Which key (sandbox or live) mirrors the same
        // mode switch StripePaymentGateway itself already uses.
        $isLive = config('urban_goodz_payments.mode') === 'live_controlled';
        $stripeConfig = config('urban_goodz_payments.stripe', []);
        $publishableKey = $isLive
            ? ($stripeConfig['live_publishable_key'] ?? '')
            : ($stripeConfig['publishable_key'] ?? '');

        return response()->json([
            'status' => 'success',
            // Resolved from admin settings, never hard-coded. A fee of 0 means
            // the administrator has switched it off, and the client should
            // show no fee line at all rather than "$0.00".
            'help_request_fee_minor' => UrbanGoodzStrandedSettings::helpRequestFeeMinor(),
            'help_request_fee_enabled' => UrbanGoodzStrandedSettings::feeEnabled(),
            'priority_upgrade_minor' => UrbanGoodzStrandedSettings::priorityUpgradeMinor(),
            'currency' => 'USD',
            'total_size' => $services->count(),
            'services' => $services,
            'payment_provider' => ($stripeConfig['enabled'] ?? false) ? 'stripe' : null,
            'stripe_publishable_key' => $publishableKey !== '' ? $publishableKey : null,
            'stripe_mode' => $isLive ? 'live' : 'sandbox',
        ]);
    }

    /**
     * Raise a request. Created as `draft` and NOT broadcast: the $5 Help
     * Request Fee is what buys the broadcast, so nothing goes out to
     * responders until that has been paid.
     */
    public function store(Request $request): JsonResponse
    {
        // Both sides of a rescue are verified. A stranded customer is inviting
        // a stranger to their location, so the bar applies to them too, not
        // only to the responder who turns up.
        $gate = UrbanGoodzStrandedSafety::gate(
            $request->user()?->id,
            UrbanGoodzStrandedVerification::ROLE_CUSTOMER
        );

        if (!$gate['allowed']) {
            return response()->json([
                'status' => 'error',
                'code' => $gate['code'],
                'message' => $gate['message'],
                'document' => $gate['document'] ?? null,
                'version' => $gate['version'] ?? null,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'service_slug' => 'required|string|max:60',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'location_notes' => 'nullable|string|max:2000',
            'destination_latitude' => 'nullable|numeric|between:-90,90',
            'destination_longitude' => 'nullable|numeric|between:-180,180',
            'destination_address' => 'nullable|string|max:500',
            'vehicle_type' => 'nullable|string|max:40',
            'vehicle_make' => 'nullable|string|max:60',
            'vehicle_model' => 'nullable|string|max:60',
            'vehicle_year' => 'nullable|string|max:8',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_plate' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:6',
            'photos.*' => 'string|max:500',
            'passenger_count' => 'nullable|integer|min:0|max:50',
            'safety_status' => 'nullable|in:safe,unsafe_location,injury_reported',
            'is_emergency' => 'nullable|boolean',
            'allow_samaritans' => 'nullable|boolean',
            'reward_offer_minor' => 'nullable|integer|min:0|max:100000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $service = UrbanGoodzStrandedService::enabled()
            ->where('slug', $request->input('service_slug'))
            ->first();

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unknown or disabled Stranded service.',
            ], 404);
        }

        // The customer may opt in to Samaritans, but the service decides
        // whether one is ever eligible. A tow or an accident scene stays
        // professional no matter what the client sends.
        $allowSamaritans = (bool) $request->input('allow_samaritans', true)
            && $service->samaritan_eligible;

        // Priced at request time and stored on the row, so a later admin
        // change cannot retroactively alter what this customer was quoted.
        $feeMinor = UrbanGoodzStrandedSettings::helpRequestFeeMinor();

        $stranded = UrbanGoodzStrandedRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_number' => 'ST-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            // Never sent to the responder. The customer reads it aloud (or has
            // it read back to them) to confirm the person who showed up is
            // actually who accepted the request -- see updateStatus().
            'help_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'user_id' => $request->user()?->id,
            'zone_id' => $this->resolveZoneId($request),
            'service_id' => $service->id,
            'service_slug' => $service->slug,
            'status' => 'draft',
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
            'location_notes' => $request->input('location_notes'),
            'destination_latitude' => $request->input('destination_latitude'),
            'destination_longitude' => $request->input('destination_longitude'),
            'destination_address' => $request->input('destination_address'),
            'vehicle_type' => $request->input('vehicle_type'),
            'vehicle_make' => $request->input('vehicle_make'),
            'vehicle_model' => $request->input('vehicle_model'),
            'vehicle_year' => $request->input('vehicle_year'),
            'vehicle_color' => $request->input('vehicle_color'),
            'vehicle_plate' => $request->input('vehicle_plate'),
            'notes' => $request->input('notes'),
            'photos' => $request->input('photos', []),
            'passenger_count' => $request->input('passenger_count'),
            'safety_status' => $request->input('safety_status'),
            'is_emergency' => (bool) $request->input('is_emergency', false),
            'allow_samaritans' => $allowSamaritans,
            'help_request_fee_minor' => $feeMinor,
            // A fee of zero is not "unpaid" -- it is waived, and the request
            // must still be eligible to broadcast. Leaving it `unpaid` would
            // strand every request the moment an admin switched the fee off.
            'help_request_fee_status' => $feeMinor > 0 ? 'unpaid' : 'waived',
            'reward_offer_minor' => (int) $request->input('reward_offer_minor', 0),
            'escrow_status' => 'none',
            'currency' => $service->currency,
            'broadcast_radius_miles' => UrbanGoodzStrandedSettings::radiusLadder()[0],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->present($stranded),
        ], 201);
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $this->present($stranded)]);
    }

    /**
     * Send the request out to nearby responders.
     *
     * The fee is what buys the broadcast, so this refuses until it is settled
     * or waived. Once a payment provider is connected this should be called by
     * the payment webhook rather than the client.
     */
    public function broadcast(Request $request, string $record, UrbanGoodzStrandedDispatcher $dispatcher): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $stranded->status . '.',
            ], 409);
        }

        if (!in_array($stranded->help_request_fee_status, ['paid', 'waived'], true)) {
            return response()->json([
                'status' => 'error',
                'code' => 'fee_outstanding',
                'message' => 'The help request fee must be settled before we can send this out.',
                'help_request_fee_minor' => $stranded->help_request_fee_minor,
            ], 402);
        }

        $count = $dispatcher->broadcast($stranded);

        if ($count === 0 && !$dispatcher->widen($stranded->fresh())) {
            // Ladder exhausted with nobody reachable at any distance.
            $this->notifier->noRespondersFound($stranded->fresh());
        }

        return response()->json([
            'status' => 'success',
            'responders_notified' => $count,
            'data' => $this->present($stranded->fresh()),
        ]);
    }

    /**
     * Pay the help request fee, which is what buys the broadcast.
     *
     * The card is tokenised client-side by the Stripe SDK; only the resulting
     * payment method id reaches this server. Card details never touch Urban
     * Goodz infrastructure.
     */
    public function payFee(Request $request, string $record, UrbanGoodzStrandedPaymentService $payments): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $stranded->status . '.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|max:255',
        ], [
            'payment_method.required' => 'A payment method is required to send your request out.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $payments->payFeeAndBroadcast($stranded, $request->input('payment_method'));
        } catch (\Throwable $e) {
            // The provider's own message is written for a person -- a declined
            // card says why -- so it is passed through rather than replaced.
            return response()->json([
                'status' => 'error',
                'code' => 'payment_failed',
                'message' => $e->getMessage(),
            ], 402);
        }

        return response()->json([
            'status' => 'success',
            'paid' => $result['paid'],
            'broadcast' => $result['broadcast'],
            'responders_notified' => $result['responders_notified'],
            'message' => $result['message'],
            'data' => $this->present($stranded->fresh()),
        ]);
    }

    /**
     * The responders willing to help, for the customer's choice screen.
     * Accepting puts a responder on this list; it does not assign the job.
     */
    public function offers(Request $request, string $record): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        $offers = $stranded->offers()
            ->whereIn('status', ['accepted', 'selected'])
            ->orderByRaw('CASE WHEN status = "selected" THEN 0 ELSE 1 END')
            ->orderBy('eta_minutes')
            ->get()
            ->map(fn (UrbanGoodzStrandedOffer $o) => [
                'id' => $o->id,
                'responder_id' => $o->responder_id,
                'responder_type' => $o->responder_type,
                'response_mode' => $o->response_mode,
                'requested_amount_minor' => $o->payableAmountMinor(),
                'distance_miles' => $o->distance_miles,
                'eta_minutes' => $o->eta_minutes,
                'rating' => $o->responder_rating,
                'trust_score' => $o->responder_trust_score,
                'completed_jobs' => $o->responder_completed_jobs,
                'status' => $o->status,
            ]);

        return response()->json([
            'status' => 'success',
            'total_size' => $offers->count(),
            'offers' => $offers,
        ]);
    }

    /**
     * The customer picks a responder. This is the assignment.
     *
     * Wrapped in a transaction with a locking read so a double-tap, or two
     * devices on the same account, cannot select two different responders for
     * one request.
     */
    public function selectOffer(Request $request, string $record, int $offerId): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $stranded->status . '.',
            ], 409);
        }

        try {
            $offer = DB::transaction(function () use ($stranded, $offerId) {
                $fresh = UrbanGoodzStrandedRequest::whereKey($stranded->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($fresh->selected_offer_id !== null) {
                    throw new \RuntimeException('already_selected');
                }

                $offer = UrbanGoodzStrandedOffer::where('request_id', $fresh->getKey())
                    ->whereKey($offerId)
                    ->lockForUpdate()
                    ->first();

                if (!$offer || !$offer->isSelectable()) {
                    throw new \RuntimeException('offer_unavailable');
                }

                $offer->update(['status' => 'selected', 'selected_at' => now()]);

                // Everyone else on the shortlist is passed over, not declined:
                // they did accept, the customer simply chose somebody else.
                UrbanGoodzStrandedOffer::where('request_id', $fresh->getKey())
                    ->whereKeyNot($offer->getKey())
                    ->whereIn('status', ['offered', 'accepted'])
                    ->update(['status' => 'passed_over']);

                $fresh->update([
                    'status' => 'assigned',
                    'selected_offer_id' => $offer->getKey(),
                    'assigned_responder_id' => $offer->responder_id,
                    'assigned_responder_type' => $offer->responder_type,
                    'assigned_at' => now(),
                    // Money owed to a responder is held, not paid, until the
                    // customer confirms the work is done.
                    'escrow_status' => $offer->payableAmountMinor() > 0 ? 'held' : 'none',
                ]);

                // Mark the responder busy so dispatch stops offering them
                // other rescues while they are driving to this one.
                UrbanGoodzStrandedResponder::where('user_id', $offer->responder_id)
                    ->where('responder_type', $offer->responder_type)
                    ->update(['active_request_id' => $fresh->getKey()]);

                return $offer;
            });
        } catch (\RuntimeException $e) {
            $conflict = $e->getMessage() === 'already_selected';
            return response()->json([
                'status' => 'error',
                'message' => $conflict
                    ? 'A responder has already been selected for this request.'
                    : 'That responder is no longer available.',
            ], $conflict ? 409 : 410);
        }

        $fresh = $stranded->fresh();

        // Notifications are dispatched only after the transaction has
        // committed, and never inside it: a Firebase hiccup must not roll back
        // an assignment the customer has already been told about.
        $this->notifySelectionOutcome($fresh, $offer);

        return response()->json([
            'status' => 'success',
            'data' => $this->present($fresh),
            'selected_offer_id' => $offer->getKey(),
        ]);
    }

    private function notifySelectionOutcome(UrbanGoodzStrandedRequest $stranded, UrbanGoodzStrandedOffer $selected): void
    {
        $this->notifier->responderSelected($stranded, $selected);

        UrbanGoodzStrandedOffer::where('request_id', $stranded->getKey())
            ->where('status', 'passed_over')
            ->get()
            ->each(fn (UrbanGoodzStrandedOffer $o) => $this->notifier->responderPassedOver($stranded, $o));
    }

    /**
     * Move a request along its lifecycle and notify whoever is waiting on that
     * step. One endpoint rather than six keeps the legal transitions in a
     * single table instead of scattered across the controller.
     */
    public function updateStatus(Request $request, string $record): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $stranded->status . '.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'event' => 'required|in:en_route,nearby,delayed,arrived,started,completed,confirmed',
            'minutes_away' => 'nullable|integer|min:0|max:600',
            'reason' => 'nullable|string|max:500',
            'help_code' => 'required_if:event,arrived|nullable|string|max:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $event = $request->input('event');
        $offer = $stranded->selected_offer_id
            ? UrbanGoodzStrandedOffer::find($stranded->selected_offer_id)
            : null;

        // Nothing past broadcast makes sense without an assigned responder.
        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => 'No responder has been selected for this request yet.',
            ], 409);
        }

        // The code is never sent to the responder -- only the customer sees
        // it. Typing it back here is the customer's own confirmation that the
        // person who showed up is who they spoke to, not a check on anything
        // the responder claims to know.
        if ($event === 'arrived' && $request->input('help_code') !== $stranded->help_code) {
            return response()->json([
                'status' => 'error',
                'code' => 'help_code_mismatch',
                'message' => "That code doesn't match. Make sure you're confirming with the right person before continuing.",
            ], 422);
        }

        match ($event) {
            'en_route' => $this->applyStatus($stranded, 'en_route', ['en_route_at' => now()]),
            'nearby' => null,
            'delayed' => null,
            'arrived' => $this->applyStatus($stranded, 'on_scene', ['arrived_at' => now()]),
            'started' => $this->applyStatus($stranded, 'on_scene', []),
            'completed' => $this->applyStatus($stranded, 'completed', ['completed_at' => now()]),
            // Escrow release is money movement, so it runs through the payment
            // service rather than being a status column written inline. That
            // keeps it idempotent and ledgered.
            'confirmed' => $this->applyStatus($stranded, 'completed', ['customer_confirmed_at' => now()]),
        };

        if ($event === 'confirmed') {
            app(UrbanGoodzStrandedPaymentService::class)->releaseEscrow($stranded->fresh());
        }

        // `completed` and `confirmed` are alternative terminal events, not
        // sequential steps -- isTerminal() blocks a second status call once
        // either has landed, so exactly one of them fires per request. The
        // trust count and freeing the responder must not depend on which one
        // the client happened to send.
        if (in_array($event, ['completed', 'confirmed'], true)) {
            UrbanGoodzStrandedResponder::where('user_id', $offer->responder_id)
                ->where('responder_type', $offer->responder_type)
                ->update(['active_request_id' => null]);

            UrbanGoodzStrandedResponder::where('user_id', $offer->responder_id)
                ->where('responder_type', $offer->responder_type)
                ->increment('completed_jobs');
        }

        $fresh = $stranded->fresh();

        match ($event) {
            'en_route' => $this->notifier->responderEnRoute($fresh),
            'nearby' => $this->notifier->responderNearby($fresh, $request->input('minutes_away')),
            'delayed' => $this->notifier->responderDelayed($fresh, $request->input('reason')),
            'arrived' => $this->notifier->responderArrived($fresh),
            'started' => $this->notifier->serviceStarted($fresh),
            'completed' => $this->notifier->serviceCompleted($fresh),
            'confirmed' => $this->notifier->completionConfirmed($fresh, $offer),
        };

        return response()->json(['status' => 'success', 'data' => $this->present($fresh)]);
    }

    private function applyStatus(UrbanGoodzStrandedRequest $stranded, string $status, array $extra): void
    {
        $stranded->update(array_merge(['status' => $status], $extra));
    }

    public function cancel(Request $request, string $record): JsonResponse
    {
        $stranded = $this->findForUser($request, $record);

        if (!$stranded) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $stranded->status . '.',
            ], 409);
        }

        // Captured before the update, because a responder who was standing by
        // still needs telling even though the request is about to be cancelled.
        $assignedOffer = $stranded->selected_offer_id
            ? UrbanGoodzStrandedOffer::find($stranded->selected_offer_id)
            : null;

        $stranded->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
            // The fee bought the broadcast. If that already happened it is
            // not refundable; if it did not, it is.
            'help_request_fee_status' => $stranded->feeIsConsumed()
                ? $stranded->help_request_fee_status
                : 'refundable',
            'escrow_status' => $stranded->escrow_status === 'held'
                ? 'refunded'
                : $stranded->escrow_status,
        ]);

        if ($assignedOffer) {
            UrbanGoodzStrandedResponder::where('user_id', $assignedOffer->responder_id)
                ->where('responder_type', $assignedOffer->responder_type)
                ->update(['active_request_id' => null]);
        }

        $fresh = $stranded->fresh();
        $this->notifier->cancelledByCustomer($fresh, $assignedOffer);

        return response()->json(['status' => 'success', 'data' => $this->present($fresh)]);
    }

    /**
     * A safety concern from either side of the assist. Filing one is always
     * recorded; whether to also cancel the request is a separate, explicit
     * choice the caller makes on top of this.
     */
    public function report(Request $request, string $record): JsonResponse
    {
        [$stranded, $role, $error] = $this->resolveEitherSide($request, $record);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'reason_code' => 'required|in:not_safe,not_who_claimed,location_incorrect,suspicious_behavior,harassment,threatening_behavior,fraud,other',
            'details' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $report = \App\Models\UrbanGoodzStrandedSafetyReport::create([
            'request_id' => $stranded->getKey(),
            'reporter_user_id' => $request->user()->id,
            'reporter_role' => $role,
            'reason_code' => $request->input('reason_code'),
            'details' => $request->input('details'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you. This has been recorded and flagged for review.',
            'report_id' => $report->id,
        ], 201);
    }

    /**
     * Either side rates the other once, after the assist. Comes off the same
     * request the messaging thread uses, so it is only available to the
     * customer or the assigned responder -- nobody else was there.
     */
    public function rate(Request $request, string $record): JsonResponse
    {
        [$stranded, $role, $error] = $this->resolveEitherSide($request, $record);
        if ($error) {
            return $error;
        }

        $validator = Validator::make($request->all(), [
            'stars' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $rateeUserId = $role === \App\Models\UrbanGoodzStrandedMessage::ROLE_CUSTOMER
            ? $stranded->assigned_responder_id
            : $stranded->user_id;

        if (!$rateeUserId) {
            return response()->json(['status' => 'error', 'message' => 'There is nobody to rate on this request.'], 409);
        }

        if (\App\Models\UrbanGoodzStrandedRating::where('request_id', $stranded->getKey())->where('rater_role', $role)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'You have already rated this assist.'], 409);
        }

        $rating = \App\Models\UrbanGoodzStrandedRating::create([
            'request_id' => $stranded->getKey(),
            'rater_user_id' => $request->user()->id,
            'rater_role' => $role,
            'ratee_user_id' => $rateeUserId,
            'stars' => $request->input('stars'),
            'comment' => $request->input('comment'),
        ]);

        // Only a Samaritan/responder's public rating is an aggregate today --
        // there is no equivalent customer-facing score to update yet.
        if ($role === \App\Models\UrbanGoodzStrandedMessage::ROLE_CUSTOMER && $stranded->assigned_responder_type) {
            $responder = UrbanGoodzStrandedResponder::where('user_id', $rateeUserId)
                ->where('responder_type', $stranded->assigned_responder_type)
                ->first();

            if ($responder) {
                $agg = \App\Models\UrbanGoodzStrandedRating::where('ratee_user_id', $rateeUserId)
                    ->avg('stars');
                $responder->update(['rating' => round((float) $agg, 2)]);
            }
        }

        return response()->json(['status' => 'success', 'rating_id' => $rating->id], 201);
    }

    /**
     * Resolves the request and which side of it the caller is on. Mirrors
     * UrbanGoodzStrandedMessageController::resolve() -- reports and ratings
     * are only meaningful from someone who was actually part of the assist.
     */
    private function resolveEitherSide(Request $request, string $record): array
    {
        $userId = (int) ($request->user()?->id ?? 0);

        $stranded = UrbanGoodzStrandedRequest::query()
            ->where(fn ($q) => $q->where('uuid', $record)->orWhere('request_number', $record))
            ->first();

        $notFound = response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);

        if (!$stranded || $userId <= 0) {
            return [null, null, $notFound];
        }

        if ((int) $stranded->user_id === $userId) {
            return [$stranded, \App\Models\UrbanGoodzStrandedMessage::ROLE_CUSTOMER, null];
        }

        if ((int) $stranded->assigned_responder_id === $userId) {
            return [$stranded, \App\Models\UrbanGoodzStrandedMessage::ROLE_RESPONDER, null];
        }

        return [null, null, $notFound];
    }

    private function findForUser(Request $request, string $record): ?UrbanGoodzStrandedRequest
    {
        return UrbanGoodzStrandedRequest::query()
            ->where(fn ($q) => $q->where('uuid', $record)->orWhere('request_number', $record))
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();
    }

    private function resolveZoneId(Request $request): ?int
    {
        $zone = json_decode((string) $request->header('zoneId'), true);
        return is_array($zone) && isset($zone[0]) ? (int) $zone[0] : null;
    }

    private function present(UrbanGoodzStrandedRequest $r): array
    {
        return [
            'uuid' => $r->uuid,
            'request_number' => $r->request_number,
            // Owner-only: present() is never called for anyone but the
            // request's own customer, so it is safe to include here.
            'help_code' => $r->help_code,
            'status' => $r->status,
            'service_slug' => $r->service_slug,
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'address' => $r->address,
            'destination_address' => $r->destination_address,
            'is_emergency' => $r->is_emergency,
            'safety_status' => $r->safety_status,
            'allow_samaritans' => $r->allow_samaritans,
            'help_request_fee_minor' => $r->help_request_fee_minor,
            'help_request_fee_status' => $r->help_request_fee_status,
            'reward_offer_minor' => $r->reward_offer_minor,
            'escrow_status' => $r->escrow_status,
            'tip_minor' => $r->tip_minor,
            'currency' => $r->currency,
            'broadcast_radius_miles' => $r->broadcast_radius_miles,
            'broadcast_at' => $r->broadcast_at?->toIso8601String(),
            'escalation_due_at' => $r->escalation_due_at?->toIso8601String(),
            'assigned_responder_type' => $r->assigned_responder_type,
            'assigned_responder_id' => $r->assigned_responder_id,
            'selected_offer_id' => $r->selected_offer_id,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
