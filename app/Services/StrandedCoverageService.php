<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where does Stranded work, and where does it let people down?
 *
 * Splits the map into square cells (about 7 miles by default) and, for each cell where requests came from, lays the
 * demand (requests, how many were never answered, how long the first answer took) beside the supply that exists
 * there now (online Samaritans and professionals, and neighbours who opted in to nearby alerts). The cells with
 * unanswered requests and little supply are where to recruit.
 */
class StrandedCoverageService
{
    /**
     * @return array{summary: array<string, mixed>, cells: list<array<string, mixed>>}
     */
    public function analyze(int $days = 30, float $cellDegrees = 0.1): array
    {
        $cellDegrees = max(0.01, $cellDegrees);
        $since = now()->subDays(max(1, $days));
        $cells = [];
        $key = fn (float $lat, float $lng) => floor($lat / $cellDegrees).':'.floor($lng / $cellDegrees);
        $center = fn (string $k) => [
            round((((int) explode(':', $k)[0]) + 0.5) * $cellDegrees, 4),
            round((((int) explode(':', $k)[1]) + 0.5) * $cellDegrees, 4),
        ];
        $blank = fn () => ['requests' => 0, 'answered' => 0, 'unanswered' => 0, 'first_answer_minutes' => [], 'online_helpers' => 0, 'opted_in_neighbours' => 0];

        $requests = DB::table('urban_goodz_stranded_requests')
            ->whereNotNull('latitude')->whereNotNull('longitude')->where('created_at', '>=', $since)
            ->get(['id', 'latitude', 'longitude', 'status', 'selected_offer_id', 'escalated_at', 'created_at']);

        $firstAccept = Schema::hasTable('urban_goodz_stranded_offers')
            ? DB::table('urban_goodz_stranded_offers')->whereIn('request_id', $requests->pluck('id'))->where('status', 'accepted')->whereNotNull('responded_at')
                ->groupBy('request_id')->selectRaw('request_id, MIN(responded_at) as first_at')->pluck('first_at', 'request_id')
            : collect();

        $totalAnswered = 0;
        $allMinutes = [];
        foreach ($requests as $r) {
            $k = $key((float) $r->latitude, (float) $r->longitude);
            $cells[$k] ??= $blank();
            $cells[$k]['requests']++;
            $answered = $r->selected_offer_id !== null || in_array($r->status, ['assigned', 'en_route', 'on_scene', 'completed'], true);
            if ($answered) {
                $cells[$k]['answered']++;
                $totalAnswered++;
            } elseif (in_array($r->status, ['no_responders', 'expired', 'cancelled', 'escalated_professional'], true) || $r->escalated_at !== null) {
                $cells[$k]['unanswered']++;
            }
            if (isset($firstAccept[$r->id])) {
                $m = max(0, (strtotime($firstAccept[$r->id]) - strtotime($r->created_at)) / 60);
                $cells[$k]['first_answer_minutes'][] = $m;
                $allMinutes[] = $m;
            }
        }

        // Supply that exists right now, in the same cells (also shown for cells with supply but no requests yet).
        if (Schema::hasTable('urban_goodz_stranded_responders')) {
            foreach (DB::table('urban_goodz_stranded_responders')->where('is_online', 1)->where('last_seen_at', '>=', now()->subMinutes(30))
                ->whereNotNull('last_latitude')->get(['last_latitude', 'last_longitude']) as $h) {
                $k = $key((float) $h->last_latitude, (float) $h->last_longitude);
                $cells[$k] ??= $blank();
                $cells[$k]['online_helpers']++;
            }
        }
        if (Schema::hasTable('urban_goodz_stranded_nearby_presence')) {
            foreach (DB::table('urban_goodz_stranded_nearby_presence')->where('expires_at', '>', now())->get(['latitude', 'longitude']) as $p) {
                $k = $key((float) $p->latitude, (float) $p->longitude);
                $cells[$k] ??= $blank();
                $cells[$k]['opted_in_neighbours']++;
            }
        }

        $rows = [];
        foreach ($cells as $k => $c) {
            $mins = $c['first_answer_minutes'];
            sort($mins);
            [$lat, $lng] = $center($k);
            $supply = $c['online_helpers'] + $c['opted_in_neighbours'];
            $rows[] = [
                'lat' => $lat, 'lng' => $lng,
                'map_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
                'requests' => $c['requests'], 'answered' => $c['answered'], 'unanswered' => $c['unanswered'],
                'answer_rate' => $c['requests'] ? round($c['answered'] / $c['requests'] * 100) : null,
                'median_first_answer_minutes' => $mins ? round($mins[intdiv(count($mins), 2)], 1) : null,
                'online_helpers' => $c['online_helpers'], 'opted_in_neighbours' => $c['opted_in_neighbours'],
                // Unanswered demand matters most; demand with no supply at all is next.
                'priority' => $c['unanswered'] * 3 + ($c['requests'] > 0 && $supply === 0 ? $c['requests'] : 0),
            ];
        }
        usort($rows, fn ($a, $b) => [$b['priority'], $b['requests']] <=> [$a['priority'], $a['requests']]);

        sort($allMinutes);

        return [
            'summary' => [
                'days' => $days,
                'requests' => $requests->count(),
                'answered' => $totalAnswered,
                'answer_rate' => $requests->count() ? round($totalAnswered / $requests->count() * 100) : null,
                'median_first_answer_minutes' => $allMinutes ? round($allMinutes[intdiv(count($allMinutes), 2)], 1) : null,
                'gap_cells' => count(array_filter($rows, fn ($r) => $r['priority'] > 0)),
                'online_helpers_now' => array_sum(array_column($rows, 'online_helpers')),
                'opted_in_neighbours_now' => array_sum(array_column($rows, 'opted_in_neighbours')),
            ],
            'cells' => $rows,
        ];
    }
}
