<?php

namespace App\Services;

use App\Domain\Stranded\Notifications\UrbanGoodzStrandedNotifier;
use App\Models\UrbanGoodzStrandedOffer;
use App\Models\UrbanGoodzStrandedRequest;
use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Support\Collection;
use App\Services\UrbanGoodzStrandedSafety;
use Illuminate\Support\Facades\DB;

/**
 * Finds responders and fans a request out to them.
 *
 * Without this nothing happens: a request is raised, paid for, and then sits
 * there. This is the piece that turns it into a rescue.
 *
 * Order of preference follows the specification -- Goodz Samaritans first,
 * professionals second -- but only where the service itself permits it. The
 * safety rule is applied here as well as at the API boundary, because this is
 * the code that actually decides who gets told about a job, and a tow must
 * never reach a community member no matter how the request was created.
 */
class UrbanGoodzStrandedDispatcher
{
    /** Miles per radian, for the haversine below. */
    private const EARTH_RADIUS_MILES = 3959;

    private const PROFESSIONAL_TYPES = ['professional', 'mobile_mechanic', 'tow', 'fleet'];

    public function __construct(private readonly UrbanGoodzStrandedNotifier $notifier)
    {
    }

    /**
     * Broadcast a request at its current radius.
     *
     * Returns the number of responders newly offered the job. Zero is a
     * meaningful answer: it is what tells the caller to widen or escalate.
     */
    public function broadcast(UrbanGoodzStrandedRequest $request): int
    {
        if ($request->isTerminal() || $request->selected_offer_id !== null) {
            return 0;
        }

        if ($request->latitude === null || $request->longitude === null) {
            return 0;
        }

        $radius = (int) $request->broadcast_radius_miles;
        $round = (int) $request->broadcast_round;

        $candidates = $this->findCandidates($request, $radius);

        // Any nearby user who opted in, AFTER Goodz Samaritans: only when no Samaritan is in range, or the
        // Samaritans have had their window (a later round, or the community delay has passed).
        $community = $this->shouldOfferCommunity($request, $candidates, $round)
            ? $this->findCommunityCandidates($request, $radius, $candidates)
            : collect();
        $candidates = $candidates->concat($community)->values();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $ttl = UrbanGoodzStrandedSettings::offerTtlSeconds();
        $expiresAt = now()->addSeconds($ttl);
        $created = 0;

        foreach ($candidates as $candidate) {
            // The unique key on (request, responder, round) is the real guard
            // against double-offering; this just avoids the exception in the
            // ordinary case.
            $exists = UrbanGoodzStrandedOffer::where('request_id', $request->getKey())
                ->where('responder_id', $candidate->user_id)
                ->where('broadcast_round', $round)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $offer = UrbanGoodzStrandedOffer::create([
                    'request_id' => $request->getKey(),
                    'responder_id' => $candidate->user_id,
                    'responder_type' => $candidate->responder_type,
                    'distance_miles' => round((float) $candidate->distance_miles, 2),
                    'eta_minutes' => $this->estimateEta((float) $candidate->distance_miles),
                    'broadcast_round' => $round,
                    'status' => 'offered',
                    'responder_rating' => $candidate->rating ?? null,
                    'responder_trust_score' => $candidate->trust_score ?? null,
                    'responder_completed_jobs' => $candidate->completed_jobs ?? null,
                    'offered_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            } catch (\Throwable $e) {
                // Lost a race against another dispatch pass. Not fatal.
                continue;
            }

            $created++;
            $this->notifier->requestBroadcast($request, $offer);
        }

        if ($created > 0) {
            $request->update([
                // An escalated request is still broadcasting, but saying so
                // would erase the more specific fact that samaritans have been
                // dropped -- which is what an operator watching the live board
                // needs to see, and which the feeds already list as a state.
                'status' => $request->status === 'escalated_professional'
                    ? 'escalated_professional'
                    : 'broadcasting',
                'broadcast_at' => $request->broadcast_at ?? now(),
                'broadcast_expires_at' => $expiresAt,
                'escalation_due_at' => $request->escalation_due_at
                    ?? now()->addMinutes(UrbanGoodzStrandedSettings::escalationMinutes()),
            ]);
        }

        return $created;
    }

    /**
     * Widen to the next radius and broadcast again.
     *
     * Returns false when the ladder is exhausted, which is the caller's signal
     * to escalate or give up rather than loop.
     */
    public function widen(UrbanGoodzStrandedRequest $request): bool
    {
        $next = $request->nextRadius();

        if ($next === null) {
            return false;
        }

        $request->update([
            'broadcast_radius_miles' => $next,
            'broadcast_round' => (int) $request->broadcast_round + 1,
        ]);

        $this->broadcast($request->fresh());

        return true;
    }

    /**
     * Hand the request to professional providers.
     *
     * The customer is not asked to raise anything new and is not charged a
     * second broadcast fee -- the request simply changes who it is looking at.
     */
    public function escalateToProfessionals(UrbanGoodzStrandedRequest $request): int
    {
        $request->update([
            'allow_samaritans' => false,
            'escalated_at' => now(),
            'status' => 'escalated_professional',
            'broadcast_round' => (int) $request->broadcast_round + 1,
            // Professionals travel further than neighbours, so reopen the
            // ladder at its widest rather than crawling back up it.
            'broadcast_radius_miles' => max(
                (int) $request->broadcast_radius_miles,
                (int) (UrbanGoodzStrandedSettings::radiusLadder()[count(UrbanGoodzStrandedSettings::radiusLadder()) - 1] ?? 25)
            ),
        ]);

        $this->notifier->escalatedToProfessionals($request->fresh());

        return $this->broadcast($request->fresh());
    }

    /**
     * Which responder types may be told about this request.
     *
     * Enforced here as well as at the API, because this is where the decision
     * actually takes effect.
     */
    private function eligibleTypes(UrbanGoodzStrandedRequest $request): array
    {
        $serviceAllowsSamaritans = (bool) ($request->service?->samaritan_eligible);

        if ($request->allow_samaritans && $serviceAllowsSamaritans) {
            return array_merge(['samaritan'], self::PROFESSIONAL_TYPES);
        }

        return self::PROFESSIONAL_TYPES;
    }

    /** Community users are offered a request only after (or without) the Goodz Samaritans. */
    private function shouldOfferCommunity(UrbanGoodzStrandedRequest $request, Collection $responders, int $round): bool
    {
        if (! (bool) config('urban_goodz_stranded.community.enabled', true)
            || $request->escalated_at !== null
            || ! $request->allow_samaritans
            || ! $request->service?->samaritan_eligible) {
            return false;
        }

        $samaritansInRange = $responders->contains(fn ($r) => ($r->responder_type ?? null) === 'samaritan');
        // Offers already went out for this request, so the Samaritans have had their turn (this is a later
        // pass: the radius widened or the offer window lapsed with no answer).
        $samaritansHadTheirTurn = UrbanGoodzStrandedOffer::where('request_id', $request->getKey())->exists();
        if (! $samaritansInRange || $samaritansHadTheirTurn) {
            return true;
        }

        return false;
    }

    /**
     * Opted-in users whose app reported a position recently, nearest first. Never the requester, never
     * anyone who has not cleared the community safety gate, never someone already offered this request.
     */
    private function findCommunityCandidates(UrbanGoodzStrandedRequest $request, int $radius, Collection $already): Collection
    {
        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;
        $latDelta = $radius / 69.0;
        $lngDelta = $radius / max(0.1, (69.0 * cos(deg2rad($lat))));
        $haversine = sprintf(
            '(%d * acos(least(1.0, cos(radians(?)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(?)) + sin(radians(?)) * sin(radians(p.latitude)))))',
            self::EARTH_RADIUS_MILES
        );

        $rows = DB::table('urban_goodz_stranded_nearby_presence as p')
            ->join('urban_goodz_notification_preferences as n', function ($j) {
                $j->on('n.recipient_id', '=', 'p.user_id')->where('n.recipient_type', '=', 'customer');
            })
            ->where('n.nearby_help_opt_in', 1)
            ->where('p.expires_at', '>', now())
            ->where('p.user_id', '!=', (int) $request->user_id)
            ->whereNotIn('p.user_id', $already->pluck('user_id')->all())
            ->whereBetween('p.latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('p.longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->select('p.user_id')
            ->selectRaw("$haversine as distance_miles", [$lat, $lng, $lat])
            ->havingRaw('distance_miles <= ?', [$radius])
            ->orderBy('distance_miles')
            ->limit(60)
            ->get();

        $max = max(1, (int) config('urban_goodz_stranded.community.max_per_round', 15));
        $out = collect();
        foreach ($rows as $row) {
            if (! UrbanGoodzStrandedSafety::communityGate((int) $row->user_id)['allowed']) {
                continue;
            }
            $out->push((object) [
                'user_id' => (int) $row->user_id,
                'responder_type' => 'community',
                'distance_miles' => (float) $row->distance_miles,
            ]);
            if ($out->count() >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Available responders inside the radius, nearest first.
     *
     * Distance is computed with the haversine formula in SQL so the database
     * does the filtering. A bounding box narrows the candidate set first,
     * because the trigonometry cannot use an index and a full scan of every
     * responder in the country would not survive contact with real traffic.
     */
    private function findCandidates(UrbanGoodzStrandedRequest $request, int $radius): Collection
    {
        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        // ~69 miles per degree of latitude; longitude narrows with latitude.
        $latDelta = $radius / 69.0;
        $lngDelta = $radius / max(0.1, (69.0 * cos(deg2rad($lat))));

        $haversine = sprintf(
            '(%d * acos(least(1.0, cos(radians(?)) * cos(radians(last_latitude)) * cos(radians(last_longitude) - radians(?)) + sin(radians(?)) * sin(radians(last_latitude)))))',
            self::EARTH_RADIUS_MILES
        );

        return UrbanGoodzStrandedResponder::query()
            ->available()
            ->whereIn('responder_type', $this->eligibleTypes($request))
            ->whereBetween('last_latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('last_longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->select('*')
            ->selectRaw("$haversine as distance_miles", [$lat, $lng, $lat])
            ->havingRaw('distance_miles <= ?', [$radius])
            // Respect what each responder said they are willing to travel.
            ->havingRaw('distance_miles <= max_travel_miles')
            ->orderBy('distance_miles')
            ->limit(25)
            ->get();
    }

    /**
     * A deliberately conservative estimate: 2 minutes per mile plus 3 to get
     * moving. Better to arrive early than to promise a time and miss it while
     * somebody waits on a hard shoulder.
     */
    private function estimateEta(float $miles): int
    {
        return (int) max(3, ceil($miles * 2) + 3);
    }
}
