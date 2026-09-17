<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedVerification;
use App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier;
use App\Domain\Stranded\Payments\UrbanGoodzStrandedPaymentService;
use App\Domain\Stranded\Payments\UrbanGoodzStripeConnectService;
use App\Services\UrbanGoodzStrandedDispatcher;
use App\Services\UrbanGoodzStrandedSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The Goodz Samaritan / professional responder side of Stranded.
 *
 * Accepting does NOT win the job. It puts the responder on the customer's
 * shortlist with their terms attached, and the customer chooses. This is the
 * half of the marketplace that was missing.
 */
class UrbanGoodzStrandedResponderController extends Controller
{
    public function __construct(private readonly UrbanGoodzStrandedNotifier $notifier)
    {
    }

    /**
     * Go online, or refresh position while online.
     *
     * Presence is what makes "nearby" mean anything, so the same endpoint
     * doubles as the heartbeat: dispatch treats a responder whose fix has
     * gone stale as unavailable rather than broadcasting into a void.
     */
    public function presence(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_online' => 'required|boolean',
            'latitude' => 'required_if:is_online,1|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:is_online,1|nullable|numeric|between:-180,180',
            'responder_type' => 'nullable|in:samaritan,professional,mobile_mechanic,tow,fleet',
            'max_travel_miles' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;
        $type = $request->input('responder_type', 'samaritan');

        // A Samaritan must clear the same verification bar as a customer
        // before they can be sent to anybody's location.
        if ($type === 'samaritan') {
            $gate = UrbanGoodzStrandedSafety::gate($userId, UrbanGoodzStrandedVerification::ROLE_SAMARITAN);
            if (!$gate['allowed']) {
                return response()->json([
                    'status' => 'error',
                    'code' => $gate['code'],
                    'message' => $gate['message'],
                    'document' => $gate['document'] ?? null,
                    'version' => $gate['version'] ?? null,
                ], 403);
            }
        }

        $responder = UrbanGoodzStrandedResponder::firstOrNew([
            'user_id' => $userId,
            'responder_type' => $type,
        ]);

        $responder->is_online = $request->boolean('is_online');
        if ($responder->is_online) {
            $responder->last_latitude = $request->input('latitude');
            $responder->last_longitude = $request->input('longitude');
            $responder->last_seen_at = now();
        }
        if ($request->filled('max_travel_miles')) {
            $responder->max_travel_miles = (int) $request->input('max_travel_miles');
        }
        $responder->save();

        return response()->json([
            'status' => 'success',
            'is_online' => $responder->is_online,
            'max_travel_miles' => $responder->max_travel_miles,
        ]);
    }

    /** Live offers waiting on this responder. */
    public function offers(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $offers = UrbanGoodzStrandedOffer::with('request.service')
            ->where('responder_id', $userId)
            ->where('status', 'offered')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get()
            ->map(function (UrbanGoodzStrandedOffer $o) {
                $r = $o->request;
                return [
                    'offer_id' => $o->id,
                    'request_uuid' => $r?->uuid,
                    'request_number' => $r?->request_number,
                    'service' => $r?->service?->name,
                    'distance_miles' => $o->distance_miles,
                    'eta_minutes' => $o->eta_minutes,
                    'reward_offer_minor' => $r?->reward_offer_minor,
                    'currency' => $r?->currency,
                    'is_emergency' => (bool) $r?->is_emergency,
                    'safety_status' => $r?->safety_status,
                    'vehicle' => trim(implode(' ', array_filter([
                        $r?->vehicle_year, $r?->vehicle_color, $r?->vehicle_make, $r?->vehicle_model,
                    ]))) ?: null,
                    'notes' => $r?->notes,
                    // The customer's exact position is withheld until the
                    // responder is actually chosen. Distance is enough to
                    // decide on.
                    'expires_at' => $o->expires_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'total_size' => $offers->count(),
            'offers' => $offers,
        ]);
    }

    /**
     * Accept an offer on stated terms.
     *
     * This shortlists the responder; the customer still chooses. Several
     * responders may accept the same request, which is the point.
     */
    public function accept(Request $request, int $offer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'response_mode' => 'required|in:volunteer,tips_only,paid',
            'requested_amount_minor' => 'required_if:response_mode,paid|nullable|integer|min:0|max:100000',
            'eta_minutes' => 'nullable|integer|min:1|max:600',
            // The client's own "I'm using a different vehicle" toggle. A
            // samaritan who leaves this false is accepting with the vehicle
            // on their verified profile -- see UrbanGoodzStrandedOffer::
            // effectiveVehicle().
            'uses_alternate_vehicle' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;
        $mode = $request->input('response_mode');
        $usesAlternateVehicle = $request->boolean('uses_alternate_vehicle');

        try {
            $accepted = DB::transaction(function () use ($request, $offer, $userId, $mode, $usesAlternateVehicle) {
                $row = UrbanGoodzStrandedOffer::whereKey($offer)
                    ->where('responder_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$row) {
                    throw new \RuntimeException('not_found');
                }

                if ($row->status !== 'offered') {
                    throw new \RuntimeException('already_answered');
                }

                if ($row->expires_at !== null && $row->expires_at->isPast()) {
                    $row->update(['status' => 'expired', 'responded_at' => now()]);
                    throw new \RuntimeException('expired');
                }

                $strandedRequest = UrbanGoodzStrandedRequest::find($row->request_id);

                if (!$strandedRequest || $strandedRequest->isTerminal() || $strandedRequest->selected_offer_id !== null) {
                    throw new \RuntimeException('closed');
                }

                $row->update([
                    'status' => 'accepted',
                    'response_mode' => $mode,
                    // Only a `paid` response carries an amount. Storing one
                    // for a volunteer would quietly create a debt nobody
                    // agreed to.
                    'requested_amount_minor' => $mode === 'paid'
                        ? (int) $request->input('requested_amount_minor', 0)
                        : 0,
                    'eta_minutes' => $request->input('eta_minutes', $row->eta_minutes),
                    'responded_at' => now(),
                    'uses_alternate_vehicle' => $usesAlternateVehicle,
                ]);

                // Normal path: verified profile, registered vehicle, nothing
                // more to collect -- ready in the same instant as accepting.
                // Declaring an alternate vehicle leaves this null until the
                // vehicle override endpoint is called.
                if (!$usesAlternateVehicle && $row->computeIdentityReady()) {
                    $row->update(['identity_ready_at' => now()]);
                }

                // Let the customer know somebody can help, and move the
                // request on so the UI can show a choice.
                if ($strandedRequest->status === 'broadcasting') {
                    $strandedRequest->update(['status' => 'awaiting_selection']);
                }

                return [$row->fresh(), $strandedRequest->fresh()];
            });
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'not_found' => response()->json(['status' => 'error', 'message' => 'Offer not found.'], 404),
                'expired' => response()->json(['status' => 'error', 'code' => 'expired', 'message' => 'This request has expired.'], 410),
                'closed' => response()->json(['status' => 'error', 'code' => 'closed', 'message' => 'This request is no longer open.'], 409),
                default => response()->json(['status' => 'error', 'code' => 'already_answered', 'message' => 'You have already answered this request.'], 409),
            };
        }

        [$acceptedOffer, $strandedRequest] = $accepted;

        // The customer is only told a responder is ready to choose once the
        // identity/vehicle they'll actually see is in hand -- notifying
        // earlier would surface a shortlist entry with nothing to show.
        if ($acceptedOffer->identity_ready_at !== null) {
            $this->notifier->responderAccepted($strandedRequest, $acceptedOffer);
        } else {
            $this->notifier->responderNeedsVehicleInfo($strandedRequest, $acceptedOffer);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'You are on the shortlist. The customer will choose shortly.',
            'offer_id' => $acceptedOffer->id,
        ]);
    }

    /**
     * The one rescue this responder is currently assigned to, with the full
     * detail they need to actually find and help the person: exact location,
     * name, photo, vehicle, and the problem. Withheld entirely until this
     * responder is the one selected -- see UrbanGoodzStrandedController::
     * offers(), where the pre-selection view stays limited to distance.
     */
    public function activeAssignment(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $responder = UrbanGoodzStrandedResponder::where('user_id', $userId)
            ->whereNotNull('active_request_id')
            ->first();

        if (!$responder) {
            return response()->json(['status' => 'success', 'has_assignment' => false]);
        }

        $stranded = UrbanGoodzStrandedRequest::find($responder->active_request_id);

        if (!$stranded || (int) $stranded->assigned_responder_id !== $userId || $stranded->isTerminal()) {
            return response()->json(['status' => 'success', 'has_assignment' => false]);
        }

        $customer = \App\Models\User::find($stranded->user_id);
        $customerVerification = \App\Models\UrbanGoodzStrandedVerification::where('user_id', $stranded->user_id)
            ->where('role', \App\Models\UrbanGoodzStrandedVerification::ROLE_CUSTOMER)
            ->first();

        return response()->json([
            'status' => 'success',
            'has_assignment' => true,
            'request_uuid' => $stranded->uuid,
            'request_number' => $stranded->request_number,
            'stage' => $stranded->status,
            'customer' => [
                'name' => $customer?->f_name ?: 'Community Member',
                'photo_url' => $customer?->image_full_url,
                'verified' => $customerVerification?->isUsable() ?? false,
            ],
            'location' => [
                // Exact, on purpose -- this only reaches the responder once
                // they are the one selected for this request.
                'latitude' => (float) $stranded->latitude,
                'longitude' => (float) $stranded->longitude,
                'address' => $stranded->address,
                'notes' => $stranded->location_notes,
            ],
            'vehicle' => trim(implode(' ', array_filter([
                $stranded->vehicle_year, $stranded->vehicle_color, $stranded->vehicle_make, $stranded->vehicle_model,
            ]))) ?: null,
            'vehicle_plate' => $stranded->vehicle_plate,
            'problem' => $stranded->notes,
            'is_emergency' => (bool) $stranded->is_emergency,
            'safety_status' => $stranded->safety_status,
        ]);
    }

    /**
     * Let a Samaritan describe their vehicle and what they're comfortable
     * helping with. Both surface to the customer only after this responder
     * has actually been selected -- see UrbanGoodzStrandedTrackingController.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_make' => 'nullable|string|max:60',
            'vehicle_model' => 'nullable|string|max:60',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_plate' => 'nullable|string|max:20',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'in:battery,tire,fuel,lockout,vehicle,towing,general',
            // Set once, not per request -- this is what lets a normal
            // acceptance skip any extra step. See the migration docblock.
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
            'vehicle_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;

        $responder = UrbanGoodzStrandedResponder::firstOrNew([
            'user_id' => $userId,
            'responder_type' => 'samaritan',
        ]);

        foreach (['vehicle_make', 'vehicle_model', 'vehicle_color', 'vehicle_plate'] as $field) {
            if ($request->has($field)) {
                $responder->{$field} = $request->input($field);
            }
        }
        if ($request->has('capabilities')) {
            $responder->capabilities = $request->input('capabilities');
        }

        $dir = "stranded/responders/{$userId}";
        if ($request->hasFile('profile_photo')) {
            $responder->profile_photo_path = $dir . '/' . \App\CentralLogics\Helpers::upload($dir, 'webp', $request->file('profile_photo'), 8);
        }
        if ($request->hasFile('vehicle_photo')) {
            $responder->vehicle_photo_path = $dir . '/' . \App\CentralLogics\Helpers::upload($dir, 'webp', $request->file('vehicle_photo'), 8);
        }

        $responder->save();

        return response()->json(['status' => 'success']);
    }

    /** Records that this user has read and accepted the Goodz Samaritan pledge. */
    public function acknowledgeSafety(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $responder = UrbanGoodzStrandedResponder::firstOrNew([
            'user_id' => $userId,
            'responder_type' => 'samaritan',
        ]);
        $responder->safety_ack_at = now();
        $responder->save();

        \App\Services\UrbanGoodzStrandedSafety::recordConsent(
            $userId,
            \App\Models\UrbanGoodzStrandedVerification::ROLE_SAMARITAN,
            \App\Services\UrbanGoodzStrandedSafety::DOC_SAMARITAN_PLEDGE,
            $request
        );

        return response()->json(['status' => 'success']);
    }

    /**
     * A fresh Stripe-hosted link to start or resume payout onboarding.
     *
     * Generated on demand and never stored -- Account Links expire quickly by
     * design, so there is nothing useful to cache.
     */
    public function payoutOnboardingLink(Request $request, UrbanGoodzStripeConnectService $connect): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'responder_type' => 'nullable|in:samaritan,professional,mobile_mechanic,tow,fleet',
            'return_url' => 'required|url',
            'refresh_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if (!$connect->isEnabled()) {
            return response()->json(['status' => 'error', 'message' => 'Responder payouts are not configured right now.'], 503);
        }

        $responder = UrbanGoodzStrandedResponder::firstOrNew([
            'user_id' => (int) $request->user()->id,
            'responder_type' => $request->input('responder_type', 'samaritan'),
        ]);
        if (!$responder->exists) {
            $responder->save();
        }

        try {
            $url = $connect->createOnboardingLink(
                $responder,
                (string) $request->input('refresh_url'),
                (string) $request->input('return_url')
            );
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 502);
        }

        return response()->json(['status' => 'success', 'onboarding_url' => $url]);
    }

    /** Whether this responder can actually be paid yet, and what is still needed. */
    public function payoutStatus(Request $request): JsonResponse
    {
        $responder = UrbanGoodzStrandedResponder::where('user_id', (int) $request->user()->id)
            ->where('responder_type', $request->input('responder_type', 'samaritan'))
            ->first();

        if (!$responder) {
            return response()->json([
                'status' => 'success',
                'onboarding_status' => 'not_started',
                'can_receive_payouts' => false,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'onboarding_status' => $responder->stripe_onboarding_status,
            'charges_enabled' => (bool) $responder->stripe_charges_enabled,
            'can_receive_payouts' => $responder->canReceivePayouts(),
        ]);
    }

    /**
     * Complete the "I'm using a different vehicle" declaration made at
     * accept() time. Only vehicle details are collected here -- never a new
     * personal photo. A verified Samaritan's identity does not change
     * because the car did.
     *
     * The vehicle is stored on this offer only (temporary, this request).
     * Making it a standing second vehicle on the profile is a follow-on --
     * this endpoint intentionally does not touch the profile row.
     */
    public function submitVehicleOverride(Request $request, int $offer, UrbanGoodzStrandedNotifier $notifier): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_make' => 'required|string|max:60',
            'vehicle_model' => 'required|string|max:60',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_photo' => 'required|image|mimes:jpg,jpeg,png|max:8192',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;

        $row = UrbanGoodzStrandedOffer::whereKey($offer)
            ->where('responder_id', $userId)
            ->where('status', 'accepted')
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Offer not found or not yet accepted.'], 404);
        }

        if (!$row->uses_alternate_vehicle) {
            return response()->json([
                'status' => 'error',
                'message' => 'This offer was accepted with your registered vehicle. There is nothing to override.',
            ], 409);
        }

        $dir = "stranded/offers/{$row->getKey()}";
        $wasReady = $row->identity_ready_at !== null;

        $row->update([
            'vehicle_make' => $request->input('vehicle_make'),
            'vehicle_model' => $request->input('vehicle_model'),
            'vehicle_color' => $request->input('vehicle_color'),
            'vehicle_plate' => $request->input('vehicle_plate'),
            'vehicle_photo_path' => $dir . '/' . \App\CentralLogics\Helpers::upload($dir, 'webp', $request->file('vehicle_photo'), 8),
        ]);

        if (!$wasReady && $row->computeIdentityReady()) {
            $row->update(['identity_ready_at' => now()]);

            $strandedRequest = UrbanGoodzStrandedRequest::find($row->request_id);
            if ($strandedRequest) {
                // This is the customer's first notice of this offer -- accept()
                // held it back because there was nothing to show yet. The
                // "verified" copy covers both "someone can help" and "here's
                // who," so nothing else needs to fire alongside it.
                $notifier->responderIdentityReady($strandedRequest, $row->fresh());
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * A selected responder backs out after being chosen -- something the
     * product never had a path for. Anyone can decline before selection;
     * nobody could cancel after it. Reopens the request for a fresh round of
     * offers rather than leaving the customer stranded on a responder who
     * is not coming, and releases any reward hold rather than leaving it
     * pending against a job that is not happening with this responder.
     */
    public function cancelAssignment(Request $request, UrbanGoodzStrandedNotifier $notifier, UrbanGoodzStrandedDispatcher $dispatcher): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $responder = UrbanGoodzStrandedResponder::where('user_id', $userId)
            ->whereNotNull('active_request_id')
            ->first();

        if (!$responder) {
            return response()->json(['status' => 'error', 'message' => 'You do not have an active assignment.'], 404);
        }

        try {
            [$stranded, $offer] = DB::transaction(function () use ($responder, $userId) {
                $fresh = UrbanGoodzStrandedRequest::whereKey($responder->active_request_id)
                    ->lockForUpdate()
                    ->first();

                if (!$fresh || (int) $fresh->assigned_responder_id !== $userId || $fresh->isTerminal()) {
                    throw new \RuntimeException('not_assigned');
                }

                $offer = $fresh->selected_offer_id ? UrbanGoodzStrandedOffer::find($fresh->selected_offer_id) : null;
                $offer?->update(['status' => 'cancelled_by_responder']);

                $fresh->update([
                    'status' => 'broadcasting',
                    'selected_offer_id' => null,
                    'assigned_responder_id' => null,
                    'assigned_responder_type' => null,
                    'assigned_at' => null,
                    'escrow_status' => $fresh->escrow_status === 'held' ? 'none' : $fresh->escrow_status,
                    // A new round so the responder who just backed out (and
                    // anyone else already offered this round) gets a clean
                    // slate rather than colliding with their old offer row.
                    'broadcast_round' => (int) $fresh->broadcast_round + 1,
                ]);

                $responder->update(['active_request_id' => null]);

                return [$fresh->fresh(), $offer];
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => 'You do not have an active assignment for this request.'], 409);
        }

        if ($offer?->payableAmountMinor() > 0 && $stranded->reward_hold_transaction_id) {
            $holdLedger = UrbanGoodzPaymentTransaction::find($stranded->reward_hold_transaction_id);
            if ($holdLedger?->provider_payment_id) {
                app(UrbanGoodzStrandedPaymentService::class)->voidRewardHold($holdLedger->provider_payment_id);
            }
        }

        $notifier->responderCancelledAssignment($stranded);

        // Look for a new responder immediately rather than waiting on the
        // next dispatch-tick -- the customer already lost the one they had.
        try {
            $dispatcher->broadcast($stranded);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['status' => 'success']);
    }

    public function decline(Request $request, int $offer): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $row = UrbanGoodzStrandedOffer::whereKey($offer)
            ->where('responder_id', $userId)
            ->first();

        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'Offer not found.'], 404);
        }

        if ($row->status !== 'offered') {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already answered this request.',
            ], 409);
        }

        $row->update(['status' => 'declined', 'responded_at' => now()]);

        UrbanGoodzStrandedResponder::where('user_id', $userId)
            ->where('responder_type', $row->responder_type)
            ->increment('declined_jobs');

        return response()->json(['status' => 'success']);
    }
}
