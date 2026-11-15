<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzNotificationPreference;
use App\Models\UrbanGoodzStrandedNearbyPresence;
use App\Services\UrbanGoodzStrandedSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Nearby help alerts": any user can choose to be reachable when a neighbour is stranded close by.
 *
 * Opt-in only. The app reports a rough position while it is in use; we round it, keep it for a couple of
 * hours at most, and use it for nothing but delivering a nearby Stranded request.
 */
class UrbanGoodzStrandedCommunityController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $pref = UrbanGoodzNotificationPreference::forRecipient('customer', $userId);
        $presence = UrbanGoodzStrandedNearbyPresence::where('user_id', $userId)->where('expires_at', '>', now())->first();
        $gate = UrbanGoodzStrandedSafety::communityGate($userId);

        return response()->json([
            'status' => 'success',
            'opted_in' => (bool) ($pref->nearby_help_opt_in ?? false),
            'location_active' => $presence !== null,
            'can_help' => $gate['allowed'],
            'blocked_reason' => $gate['allowed'] ? null : ['code' => $gate['code'], 'message' => $gate['message'], 'document' => $gate['document'] ?? null],
        ]);
    }

    /** The app reports where the user roughly is. Ignored (and refused) unless they have opted in. */
    public function presence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $userId = (int) $request->user()->id;
        $pref = UrbanGoodzNotificationPreference::forRecipient('customer', $userId);
        if (! ($pref->nearby_help_opt_in ?? false)) {
            return response()->json(['status' => 'error', 'code' => 'nearby_help_off', 'message' => 'Turn on Nearby help alerts first.'], 403);
        }

        $ttl = max(10, (int) config('urban_goodz_stranded.community.presence_ttl_minutes', 120));
        UrbanGoodzStrandedNearbyPresence::updateOrCreate(['user_id' => $userId], [
            // About 100 m: enough to rank by distance, not enough to follow someone.
            'latitude' => round((float) $data['latitude'], 3),
            'longitude' => round((float) $data['longitude'], 3),
            'reported_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        return response()->json(['status' => 'success', 'expires_in_minutes' => $ttl]);
    }

    public function clear(Request $request): JsonResponse
    {
        UrbanGoodzStrandedNearbyPresence::where('user_id', (int) $request->user()->id)->delete();

        return response()->json(['status' => 'success']);
    }

    /** Accept the safety terms, privacy notice and helper pledge that go with helping a stranger. */
    public function acknowledge(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        foreach (UrbanGoodzStrandedSafety::requiredDocumentsFor(UrbanGoodzStrandedSafety::ROLE_COMMUNITY) as $document) {
            UrbanGoodzStrandedSafety::recordConsent($userId, UrbanGoodzStrandedSafety::ROLE_COMMUNITY, $document, $request);
        }

        return response()->json(['status' => 'success']);
    }
}
