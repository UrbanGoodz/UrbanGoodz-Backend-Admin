<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live operations view for Urban Goodz Stranded (Vendor/Business Portal).
 *
 * Reuses the same data feed structure as the admin live controller, scoped to
 * the store's zone or active service area.
 */
class UrbanGoodzStrandedVendorLiveController extends Controller
{
    private const STALE_SECONDS = 120;

    public function index(): View
    {
        return view('vendor-views.urban-goodz.stranded.live', [
            'mapKey' => Helpers::get_business_settings('map_api_key'),
        ]);
    }

    /**
     * The live picture for the business portal.
     * Scoped to the vendor's zone if set, or active rescues.
     */
    public function feed(Request $request): JsonResponse
    {
        $vendor = auth('vendor')->user();
        $store = $vendor?->stores?->first();
        $zoneId = $store?->zone_id;

        $active = ['draft', 'awaiting_fee', 'broadcasting', 'awaiting_selection',
                   'assigned', 'en_route', 'on_scene', 'escalated_professional'];

        $query = UrbanGoodzStrandedRequest::with('service')
            ->whereIn('status', $active);

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $requests = $query->orderByDesc('is_emergency')
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
                // Signed diff -- see the admin live controller. The older
                // timestamp must be the receiver or every stale responder
                // reads as fresh.
                $age = $p->last_seen_at ? max(0, (int) $p->last_seen_at->diffInSeconds(now())) : null;

                return [
                    'user_id' => $p->user_id,
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
                'stale_fixes' => $responders->where('stale', true)->count(),
            ],
            'requests' => $requests->values(),
            'responders' => $responders->values(),
        ]);
    }
}
