<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live operations view for Urban Goodz Stranded.
 *
 * Two endpoints: a page, and a JSON feed the page polls. The feed is kept
 * separate and deliberately cheap so it can be polled every few seconds
 * without dragging the database, and so the business portal can reuse exactly
 * the same shape against a narrower scope rather than growing a second
 * implementation that drifts.
 *
 * Responder coordinates ARE exposed here, unlike the customer-facing tracking
 * endpoint which releases only the one responder assigned to that customer.
 * An operator watching dispatch needs to see everyone; that is the job. The
 * distinction is deliberate rather than accidental.
 */
class UrbanGoodzStrandedLiveController extends Controller
{
    /** Anything older than this is not a position, it is a memory. */
    private const STALE_SECONDS = 120;

    public function index(): View
    {
        return view('admin-views.urban-goodz.stranded.live', [
            'mapKey' => Helpers::get_business_settings('map_api_key'),
        ]);
    }

    /**
     * The live picture. Shape is shared with the business portal; only the
     * scope differs.
     */
    public function feed(Request $request): JsonResponse
    {
        $active = ['draft', 'awaiting_fee', 'broadcasting', 'awaiting_selection',
                   'assigned', 'en_route', 'on_scene', 'escalated_professional'];

        $requests = UrbanGoodzStrandedRequest::with('service')
            ->whereIn('status', $active)
            ->orderByDesc('is_emergency')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (UrbanGoodzStrandedRequest $r) {
                $offer = $r->selected_offer_id ? UrbanGoodzStrandedOffer::find($r->selected_offer_id) : null;

                return [
                    'id' => $r->id,
                    'number' => $r->request_number,
                    'service' => $r->service?->name ?? $r->service_slug,
                    'status' => $r->status,
                    'emergency' => (bool) $r->is_emergency,
                    'safety' => $r->safety_status,
                    'lat' => (float) $r->latitude,
                    'lng' => (float) $r->longitude,
                    'address' => $r->address,
                    'fee_status' => $r->help_request_fee_status,
                    'escrow' => $r->escrow_status,
                    'reward' => round(((int) $r->reward_offer_minor) / 100, 2),
                    'radius' => (int) $r->broadcast_radius_miles,
                    'offers' => UrbanGoodzStrandedOffer::where('request_id', $r->id)
                        ->whereIn('status', ['offered', 'accepted'])->count(),
                    'responder' => $offer ? [
                        'type' => $offer->responder_type,
                        'eta' => $offer->eta_minutes,
                        'distance' => $offer->distance_miles,
                        'rating' => $offer->responder_rating,
                    ] : null,
                    'waiting_minutes' => $r->created_at ? (int) $r->created_at->diffInMinutes(now()) : null,
                ];
            });

        $responders = UrbanGoodzStrandedResponder::query()
            ->where('is_online', true)
            ->whereNotNull('last_latitude')
            ->get()
            ->map(function (UrbanGoodzStrandedResponder $p) {
                $age = $p->last_seen_at ? (int) now()->diffInSeconds($p->last_seen_at) : null;

                return [
                    'user_id' => $p->user_id,
                    // Samaritan and professional are shown apart on purpose --
                    // an operator needs to know at a glance which network is
                    // carrying the load.
                    'type' => $p->responder_type,
                    'lat' => (float) $p->last_latitude,
                    'lng' => (float) $p->last_longitude,
                    'busy' => $p->active_request_id !== null,
                    'rating' => $p->rating,
                    'trust' => $p->trust_score,
                    'completed' => $p->completed_jobs,
                    'fix_age_seconds' => $age,
                    'stale' => $age === null || $age > self::STALE_SECONDS,
                ];
            });

        $samaritans = $responders->where('type', 'samaritan');
        $professionals = $responders->where('type', '!=', 'samaritan');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'active_requests' => $requests->count(),
                'emergencies' => $requests->where('emergency', true)->count(),
                'awaiting_responder' => $requests->whereIn('status', ['broadcasting', 'awaiting_selection'])->count(),
                'in_progress' => $requests->whereIn('status', ['assigned', 'en_route', 'on_scene'])->count(),
                'samaritans_online' => $samaritans->where('stale', false)->count(),
                'professionals_online' => $professionals->where('stale', false)->count(),
                'responders_busy' => $responders->where('busy', true)->count(),
                // Surfaced rather than hidden: a responder whose signal has
                // dropped still shows as online in the raw data, and an
                // operator counting available help would otherwise be misled.
                'stale_fixes' => $responders->where('stale', true)->count(),
            ],
            'requests' => $requests->values(),
            'responders' => $responders->values(),
        ]);
    }
}
