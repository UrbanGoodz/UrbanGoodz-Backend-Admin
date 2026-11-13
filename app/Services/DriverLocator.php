<?php

namespace App\Services;

use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where is this driver, for the purpose of choosing who should get a job?
 *
 * Dispatch used to rank drivers by their saved "private endpoint" address, which is a base location
 * (home or depot), not where they are. A driver across the city from home would be offered work
 * "nearby" while a driver standing at the pickup was skipped. This ranks by the position the driver
 * app last reported, and only falls back to the saved address when there is no recent report.
 */
class DriverLocator
{
    /** A live report older than this is treated as unknown rather than trusted. */
    public static function maxLiveAgeMinutes(): int
    {
        return max(1, (int) config('urban_goodz_dispatch.live_location_max_minutes', 15));
    }

    /**
     * @param  list<int>  $driverIds
     * @return array<int, array{lat: float, lng: float, source: string, age_minutes: int|null}>
     */
    public function positions(array $driverIds): array
    {
        $driverIds = array_values(array_unique(array_map('intval', $driverIds)));
        if ($driverIds === []) {
            return [];
        }

        $out = [];
        if (Schema::hasTable('delivery_histories')) {
            $cutoff = now()->subMinutes(self::maxLiveAgeMinutes());
            $rows = DB::table('delivery_histories')
                ->whereIn('delivery_man_id', $driverIds)
                ->where('created_at', '>=', $cutoff)
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->orderByDesc('id')
                ->get(['delivery_man_id', 'latitude', 'longitude', 'created_at']);
            foreach ($rows as $r) {
                $id = (int) $r->delivery_man_id;
                if (isset($out[$id]) || ! is_numeric($r->latitude) || ! is_numeric($r->longitude)) {
                    continue; // newest row per driver wins
                }
                $out[$id] = [
                    'lat' => (float) $r->latitude,
                    'lng' => (float) $r->longitude,
                    'source' => 'live',
                    'age_minutes' => (int) max(0, now()->diffInMinutes($r->created_at)),
                ];
            }
        }

        $missing = array_values(array_diff($driverIds, array_keys($out)));
        if ($missing !== []) {
            foreach (DeliveryMan::query()->withoutGlobalScopes()->whereIn('id', $missing)
                ->whereNotNull('private_endpoint_lat')->whereNotNull('private_endpoint_lng')
                ->get(['id', 'private_endpoint_lat', 'private_endpoint_lng']) as $d) {
                $out[(int) $d->id] = [
                    'lat' => (float) $d->private_endpoint_lat,
                    'lng' => (float) $d->private_endpoint_lng,
                    'source' => 'home',
                    'age_minutes' => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Drivers with a known position, nearest first, each carrying distance_miles and location_source.
     * A driver with no position at all is left out: guessing would be worse than skipping.
     *
     * @param  iterable<DeliveryMan>  $drivers
     * @return list<DeliveryMan>
     */
    public function rankByDistance(iterable $drivers, float $lat, float $lng, ?float $maxMiles = null): array
    {
        $list = is_array($drivers) ? $drivers : iterator_to_array($drivers, false);
        $positions = $this->positions(array_map(fn ($d) => (int) $d->id, $list));

        $ranked = [];
        foreach ($list as $driver) {
            $pos = $positions[(int) $driver->id] ?? null;
            if (! $pos) {
                continue;
            }
            $miles = self::haversineMiles($pos['lat'], $pos['lng'], $lat, $lng);
            if ($maxMiles !== null && $miles > $maxMiles) {
                continue;
            }
            $driver->distance_miles = round($miles, 2);
            $driver->location_source = $pos['source'];
            $ranked[] = $driver;
        }

        usort($ranked, fn ($a, $b) => [$a->distance_miles, $a->id] <=> [$b->distance_miles, $b->id]);

        return $ranked;
    }

    public static function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
