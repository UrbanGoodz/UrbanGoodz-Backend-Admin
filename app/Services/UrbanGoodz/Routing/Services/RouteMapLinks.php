<?php

namespace App\Services\UrbanGoodz\Routing\Services;

/**
 * Turns an ordered list of route stops into what a driver's phone needs to navigate: a single-stop link for each
 * stop and multi-stop Google Maps trips for the NEXT pending stops (Google Maps accepts one destination plus up to
 * 9 waypoints in a link). No API key is needed: the driver's own Maps app does the turn-by-turn.
 */
class RouteMapLinks
{
    public const DEFAULT_CHUNK = 9;

    /**
     * @param array<int, array{stop_order:int, lat:?float, lng:?float, pending:bool}> $stops in route order
     * @return array{stops: array<int, array<string,mixed>>, next_trip: ?array<string,mixed>, trips: array<int, array<string,mixed>>}
     */
    public function build(array $stops, int $chunk = self::DEFAULT_CHUNK): array
    {
        $chunk = max(1, min(10, $chunk));
        $withLinks = [];
        foreach ($stops as $s) {
            $s['navigate_url'] = $this->hasCoords($s) ? $this->singleUrl($s) : null;
            $withLinks[] = $s;
        }

        // Only pending, locatable stops go into trips, in route order.
        $pending = array_values(array_filter($withLinks, fn ($s) => $s['pending'] && $this->hasCoords($s)));
        $trips = [];
        foreach (array_chunk($pending, $chunk) as $i => $group) {
            $trips[] = [
                'trip_number' => $i + 1,
                'stop_orders' => array_column($group, 'stop_order'),
                'stops_in_trip' => count($group),
                'url' => $this->tripUrl($group),
            ];
        }

        return ['stops' => $withLinks, 'next_trip' => $trips[0] ?? null, 'trips' => $trips];
    }

    private function hasCoords(array $s): bool
    {
        return isset($s['lat'], $s['lng']) && is_numeric($s['lat']) && is_numeric($s['lng'])
            && ((float) $s['lat'] != 0.0 || (float) $s['lng'] != 0.0);
    }

    private function point(array $s): string
    {
        return rtrim(rtrim(number_format((float) $s['lat'], 6, '.', ''), '0'), '.') . ',' . rtrim(rtrim(number_format((float) $s['lng'], 6, '.', ''), '0'), '.');
    }

    private function singleUrl(array $s): string
    {
        return 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' . $this->point($s);
    }

    /** Origin is left out on purpose: Maps then starts from the driver's current location. */
    private function tripUrl(array $group): string
    {
        $dest = $this->point($group[count($group) - 1]);
        $url = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=' . $dest;
        $waypoints = array_map(fn ($s) => $this->point($s), array_slice($group, 0, -1));
        if ($waypoints) {
            $url .= '&waypoints=' . implode('%7C', $waypoints);
        }

        return $url;
    }
}
