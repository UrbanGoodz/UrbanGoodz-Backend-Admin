<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live tracking for a Stranded rescue.
 *
 * A responder's precise position is deliberately not public. It is visible to
 * exactly one customer -- the one they have been assigned to -- and only while
 * the rescue is actually running. Before selection there is no responder to
 * track; after completion there is no reason to keep watching someone.
 *
 * This is why UrbanGoodzStrandedResponder hides last_latitude/last_longitude
 * by default: the coordinates are released here, deliberately, rather than
 * leaking through a model that happened to be serialised somewhere else.
 */
class UrbanGoodzStrandedTrackingController extends Controller
{
    /** Miles per radian, matching the dispatcher's own distance maths. */
    private const EARTH_RADIUS_MILES = 3959;

    public function track(Request $request, string $record): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);

        $stranded = UrbanGoodzStrandedRequest::query()
            ->where(fn ($q) => $q->where('uuid', $record)->orWhere('request_number', $record))
            ->first();

        // A stranger's rescue is not confirmed to exist.
        if (!$stranded || (int) $stranded->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($stranded->selected_offer_id === null) {
            return response()->json([
                'status' => 'success',
                'trackable' => false,
                'stage' => $stranded->status,
                'message' => 'No responder has been selected yet.',
            ]);
        }

        if ($stranded->isTerminal()) {
            return response()->json([
                'status' => 'success',
                'trackable' => false,
                'stage' => $stranded->status,
                'message' => 'This request is complete.',
            ]);
        }

        $offer = UrbanGoodzStrandedOffer::find($stranded->selected_offer_id);
        $responder = UrbanGoodzStrandedResponder::where('user_id', $stranded->assigned_responder_id)
            ->where('responder_type', $stranded->assigned_responder_type ?: 'samaritan')
            ->first();

        $lat = $responder?->last_latitude;
        $lng = $responder?->last_longitude;

        // A fix older than this is stale enough to mislead. Showing a marker
        // that has not moved for ten minutes is worse than admitting the
        // position is unknown.
        $fixAgeSeconds = $responder?->last_seen_at
            ? now()->diffInSeconds($responder->last_seen_at)
            : null;
        $stale = $fixAgeSeconds === null || $fixAgeSeconds > 120;

        $distance = ($lat !== null && $lng !== null && !$stale)
            ? $this->miles((float) $stranded->latitude, (float) $stranded->longitude, (float) $lat, (float) $lng)
            : null;

        return response()->json([
            'status' => 'success',
            'trackable' => true,
            'stage' => $stranded->status,
            'responder' => [
                // First name only. The customer needs to recognise who is
                // arriving, not to be handed an identity record.
                'name' => $this->firstName($stranded->assigned_responder_id),
                'type' => $stranded->assigned_responder_type,
                'rating' => $offer?->responder_rating,
                'trust_score' => $offer?->responder_trust_score,
                'completed_jobs' => $offer?->responder_completed_jobs,
                'verified' => true,
            ],
            'position' => $stale ? null : [
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'updated_seconds_ago' => $fixAgeSeconds,
            ],
            'position_unavailable_reason' => $stale
                ? 'We have lost their signal for a moment. This usually recovers on its own.'
                : null,
            'distance_miles' => $distance !== null ? round($distance, 2) : null,
            'eta_minutes' => $distance !== null ? (int) max(1, ceil($distance * 2) + 1) : $offer?->eta_minutes,
            'destination' => [
                'latitude' => (float) $stranded->latitude,
                'longitude' => (float) $stranded->longitude,
                'address' => $stranded->address,
            ],
            'timeline' => [
                'assigned_at' => $stranded->assigned_at?->toIso8601String(),
                'en_route_at' => $stranded->en_route_at?->toIso8601String(),
                'arrived_at' => $stranded->arrived_at?->toIso8601String(),
            ],
        ]);
    }

    private function firstName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = \App\Models\User::find($userId);

        return $user?->f_name ?: ($user?->name ? explode(' ', $user->name)[0] : 'Your responder');
    }

    /** Haversine, in miles. */
    private function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
