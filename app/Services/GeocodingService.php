<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns an address into coordinates with the platform's existing server-side Google key
 * (business setting `map_api_key_server`). Returns null when there is no key, no confident result or the call
 * fails: callers must treat null as "location unknown", never as a location. Results are cached for 30 days.
 */
class GeocodingService
{
    /** @return array{lat: float, lng: float, formatted: string}|null */
    public function geocode(?string $address): ?array
    {
        $address = trim((string) $address);
        // A website or a one-word name is not an address; do not spend a lookup on it.
        if (mb_strlen($address) < 6 || preg_match('#^(https?://|www\.)#i', $address)) {
            return null;
        }

        $key = (string) BusinessSetting::where('key', 'map_api_key_server')->value('value');
        if ($key === '') {
            return null;
        }

        return Cache::remember('geocode:' . sha1(mb_strtolower($address)), now()->addDays(30), function () use ($address, $key) {
            try {
                $res = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', ['address' => $address, 'key' => $key]);
                $first = $res->json('results.0');
                if (! $res->successful() || $res->json('status') !== 'OK' || ! $first) {
                    return null;
                }
                // A match that is only a city or region is too coarse to pick the nearest driver from.
                if (in_array($first['geometry']['location_type'] ?? '', ['APPROXIMATE'], true)
                    && ! array_intersect($first['types'] ?? [], ['street_address', 'premise', 'subpremise', 'establishment', 'point_of_interest'])) {
                    return null;
                }

                return [
                    'lat' => (float) $first['geometry']['location']['lat'],
                    'lng' => (float) $first['geometry']['location']['lng'],
                    'formatted' => (string) ($first['formatted_address'] ?? $address),
                ];
            } catch (\Throwable $e) {
                Log::warning('Geocoding failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
