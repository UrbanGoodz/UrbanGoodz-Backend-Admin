<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use App\Models\UrbanGoodzStrandedVerification;
use App\Services\UrbanGoodzStrandedNotifier;
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
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;
        $mode = $request->input('response_mode');

        try {
            $accepted = DB::transaction(function () use ($request, $offer, $userId, $mode) {
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
                ]);

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
        $this->notifier->responderAccepted($strandedRequest, $acceptedOffer);

        return response()->json([
            'status' => 'success',
            'message' => 'You are on the shortlist. The customer will choose shortly.',
            'offer_id' => $acceptedOffer->id,
        ]);
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
