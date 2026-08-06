<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedService;
use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UrbanGoodzStrandedController extends Controller
{
    /**
     * Catalogue for the "I'm Stranded" flow. Unauthenticated on purpose: the
     * Services card must show what help exists before we ask somebody on a
     * shoulder to log in.
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
                'price_min_minor' => $s->base_price_min_minor,
                'price_max_minor' => $s->base_price_max_minor,
                'currency' => $s->currency,
                'pricing_note' => $s->pricing_note,
                'is_quote_only' => $s->is_quote_only,
                'samaritan_eligible' => $s->samaritan_eligible,
                'typical_duration_minutes' => $s->typical_duration_minutes,
            ]);

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
        ]);
    }

    /**
     * Raise a request. Created as `draft` and NOT broadcast: the $5 Help
     * Request Fee is what buys the broadcast, so nothing goes out to
     * responders until that has been paid.
     */
    public function store(Request $request): JsonResponse
    {
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

        return response()->json([
            'status' => 'success',
            'data' => $this->present($stranded->fresh()),
            'selected_offer_id' => $offer->getKey(),
        ]);
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

        return response()->json(['status' => 'success', 'data' => $this->present($stranded->fresh())]);
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
