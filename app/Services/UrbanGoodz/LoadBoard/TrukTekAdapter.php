<?php

namespace App\Services\UrbanGoodz\LoadBoard;

/**
 * TrukTek public load board (GET /api/loads).
 *
 * This is the only load source Urban Goodz can query without a partner
 * agreement: the endpoint takes no authentication at all.
 *
 * Verified against the live endpoint on 2026-09-09, and the behaviour does
 * NOT match the published parameter table. Measured, not assumed:
 *
 *   - `freight` (equipment) IS applied server-side. Requesting Reefer
 *     returned total=1414 and 100/100 rows were Reefer.
 *   - `octy` / `ost` / `dcty` / `dst` are ACCEPTED BUT IGNORED. Requesting
 *     ost=TX returned the uncapped total (10000) and only 10/100 rows were
 *     actually TX - i.e. chance, not filtering.
 *   - `gross_rpm` is likewise ignored: a gross_rpm=1.5 request came back
 *     full of rows whose grossRpm was 0.
 *
 * So lane filtering MUST happen locally. If we trusted the query string we
 * would hand dispatchers loads on completely unrelated lanes.
 *
 * Two more field-level traps:
 *   - `grossRpm` from the API is computed against the caller's truck
 *     position. Anonymous callers have none, so it comes back 0-0.03 and is
 *     meaningless. rate_per_mile is recomputed locally from ratePay/loadDist.
 *   - `ratePay` is a placeholder (1, sometimes 50) on unpriced postings,
 *     not a real rate. Those are surfaced as null payout, never as $1.
 *
 * Rate limit: 30 requests/minute/IP.
 */
class TrukTekAdapter extends AbstractLoadBoardProvider
{
    /** Equipment values the public endpoint accepts for `freight`. */
    private const SUPPORTED_EQUIPMENT = ['Van', 'Reefer', 'Flatbed', 'Tanker', 'Intermodal', 'Auto'];

    /** ratePay values at or below this are placeholders, not real offers. */
    private const PLACEHOLDER_RATE_CEILING = 1.0;

    public function getProviderSlug(): string
    {
        return 'truktek';
    }

    /**
     * The public board needs no credentials, so "configured" only means the
     * operator left it enabled and a base URL is present.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['enabled']) && !empty($this->baseUrl);
    }

    protected function buildHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'UrbanGoodz-LoadSourcing/1.0',
        ];
    }

    /**
     * @param array $filters origin_city, origin_state, destination_city,
     *                       destination_state, equipment_type, min_rate_per_mile,
     *                       max_deadhead_miles, pickup_date
     */
    public function fetchLoads(array $filters = [], int $maxResults = 100): array
    {
        $response = $this->get('/api/loads', $this->buildQuery($filters));

        if (!is_array($response) || !isset($response['loads']) || !is_array($response['loads'])) {
            return [];
        }

        // Everything except equipment is filtered here, because the endpoint
        // silently ignores those parameters (see the class docblock).
        $loads = $this->applyLocalFilters($response['loads'], $filters);

        return array_slice($loads, 0, max(1, $maxResults));
    }

    public function getLoad(string $externalId): ?array
    {
        // The public board exposes no single-load endpoint, so the load is
        // located within the current board page.
        $response = $this->get('/api/loads', []);

        foreach ($response['loads'] ?? [] as $load) {
            if ((string) ($load['loadId'] ?? '') === $externalId) {
                return $load;
            }
        }

        return null;
    }

    /**
     * Only `freight` is sent, since it is the one parameter the endpoint
     * honours. Sending the rest would imply a filter we did not get.
     */
    private function buildQuery(array $filters): array
    {
        $equipment = $this->mapEquipmentToFreight($filters['equipment_type'] ?? null);

        return $equipment ? ['freight' => $equipment] : [];
    }

    private function applyLocalFilters(array $loads, array $filters): array
    {
        $originCity = $this->normalizeCity($filters['origin_city'] ?? null);
        $originState = $this->normalizeState($filters['origin_state'] ?? null);
        $destCity = $this->normalizeCity($filters['destination_city'] ?? null);
        $destState = $this->normalizeState($filters['destination_state'] ?? null);
        $minRpm = isset($filters['min_rate_per_mile']) ? (float) $filters['min_rate_per_mile'] : null;
        $maxDeadhead = isset($filters['max_deadhead_miles']) ? (float) $filters['max_deadhead_miles'] : null;

        return array_values(array_filter($loads, function (array $load) use (
            $originCity, $originState, $destCity, $destState, $minRpm, $maxDeadhead
        ) {
            if ($originState && $this->normalizeState($load['ost'] ?? null) !== $originState) {
                return false;
            }
            if ($originCity && $this->normalizeCity($load['octy'] ?? null) !== $originCity) {
                return false;
            }
            if ($destState && $this->normalizeState($load['dst'] ?? null) !== $destState) {
                return false;
            }
            if ($destCity && $this->normalizeCity($load['dcty'] ?? null) !== $destCity) {
                return false;
            }

            if ($minRpm !== null) {
                $rpm = $this->computeRatePerMile($load);
                if ($rpm === null || $rpm < $minRpm) {
                    return false;
                }
            }

            if ($maxDeadhead !== null) {
                $deadhead = $this->castFloat($load['o2oDist'] ?? null);
                if ($deadhead !== null && $deadhead > $maxDeadhead) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function normalize(array $raw): array
    {
        $payout = $this->realPayout($raw);
        $distance = $this->castFloat($raw['loadDist'] ?? null);
        $coordinates = is_array($raw['coordinates'] ?? null) ? $raw['coordinates'] : [];

        // coordinates is the full route polyline as [lng, lat] pairs; the
        // first and last points are the origin and destination.
        $first = $coordinates[0] ?? null;
        $last = $coordinates ? end($coordinates) : null;

        $equipment = $this->mapEquipmentType($raw['equip'] ?? null);
        $driveMinutes = $this->castFloat($raw['loadHours'] ?? null);

        return [
            'external_id' => (string) ($raw['loadId'] ?? ''),
            'load_number' => null,
            'origin_name' => null,
            'origin_city' => $raw['octy'] ?? null,
            'origin_state' => $this->normalizeState($raw['ost'] ?? null),
            'origin_zip' => null,
            'origin_lat' => is_array($first) ? $this->castFloat($first[1] ?? null) : null,
            'origin_lng' => is_array($first) ? $this->castFloat($first[0] ?? null) : null,
            'origin_ready_at' => $this->parseDateTime($raw['pickupDate'] ?? null),
            'destination_name' => null,
            'destination_city' => $raw['dcty'] ?? null,
            'destination_state' => $this->normalizeState($raw['dst'] ?? null),
            'destination_zip' => null,
            'destination_lat' => is_array($last) ? $this->castFloat($last[1] ?? null) : null,
            'destination_lng' => is_array($last) ? $this->castFloat($last[0] ?? null) : null,
            'destination_due_at' => $this->parseDateTime($raw['deliveryDate'] ?? null),
            'distance_miles' => $distance,
            'estimated_duration_minutes' => $driveMinutes !== null ? (int) round($driveMinutes * 60) : null,
            'payout_amount' => $payout,
            'payout_type' => 'flat',
            // Recomputed locally: the API's grossRpm is relative to the
            // caller's truck position, which an anonymous caller has none of.
            'rate_per_mile' => $this->computeRatePerMile($raw),
            'load_type' => 'ftl',
            'equipment_type' => $equipment,
            'weight_lbs' => $this->castFloat($raw['weight'] ?? null),
            'length_ft' => $this->castFloat($raw['length'] ?? null),
            'pieces' => null,
            'commodity_description' => null,
            'special_requirements' => null,
            'notes' => null,
            'is_hazmat' => false,
            'is_temperature_controlled' => $equipment === 'reefer',
            'temperature_min_f' => null,
            'temperature_max_f' => null,
            'requires_liftgate' => false,
            'requires_pallet_jack' => false,
            'is_team_load' => false,
            'is_expedited' => false,
            'shipper_name' => $raw['shipperNm'] ?? null,
            'shipper_phone' => null,
            'consignee_name' => null,
            'consignee_phone' => null,
            'metadata' => [
                'truktek_deadhead_to_origin_miles' => $this->castFloat($raw['o2oDist'] ?? null),
                'truktek_deadhead_from_destination_miles' => $this->castFloat($raw['d2dDist'] ?? null),
                'truktek_extra_miles' => $this->castFloat($raw['extraDist'] ?? null),
                'truktek_extra_hours' => $this->castFloat($raw['extraHours'] ?? null),
                // Kept for traceability, but never used as a rate: it is 0
                // for anonymous callers.
                'truktek_reported_gross_rpm' => $this->castFloat($raw['grossRpm'] ?? null),
                'truktek_rate_is_placeholder' => $payout === null,
                'route_polyline' => $raw['coordinates'] ?? null,
                'original_data' => $raw,
            ],
        ];
    }

    /**
     * ratePay is 1 (occasionally 50) on postings with no published rate.
     * Returning those as a real $1 payout would poison every downstream
     * margin calculation, so an unpriced load reports null.
     */
    private function realPayout(array $raw): ?float
    {
        $rate = $this->castFloat($raw['ratePay'] ?? null);

        if ($rate === null || $rate <= self::PLACEHOLDER_RATE_CEILING) {
            return null;
        }

        return $rate;
    }

    private function computeRatePerMile(array $raw): ?float
    {
        $payout = $this->realPayout($raw);
        $distance = $this->castFloat($raw['loadDist'] ?? null);

        if ($payout === null || $distance === null || $distance <= 0.0) {
            return null;
        }

        return round($payout / $distance, 4);
    }

    private function normalizeCity(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        return strtolower(trim($city));
    }

    /** Map an Urban Goodz equipment slug onto TrukTek's `freight` values. */
    private function mapEquipmentToFreight(?string $equipment): ?string
    {
        if (!$equipment) {
            return null;
        }

        $map = [
            'van' => 'Van', 'dry_van' => 'Van', 'box_truck' => 'Van',
            'reefer' => 'Reefer', 'refrigerated' => 'Reefer',
            'flatbed' => 'Flatbed', 'step_deck' => 'Flatbed',
            'tanker' => 'Tanker',
            'intermodal' => 'Intermodal', 'container' => 'Intermodal',
            'car_hauler' => 'Auto', 'auto' => 'Auto',
        ];

        $candidate = $map[strtolower(trim($equipment))] ?? ucfirst(strtolower(trim($equipment)));

        return in_array($candidate, self::SUPPORTED_EQUIPMENT, true) ? $candidate : null;
    }

    private function mapEquipmentType(?string $type): string
    {
        $map = [
            'van' => 'van',
            'reefer' => 'reefer',
            'flatbed' => 'flatbed',
            'tanker' => 'tanker',
            'intermodal' => 'intermodal',
            'auto' => 'car_hauler',
        ];

        return $map[strtolower(trim($type ?? ''))] ?? 'van';
    }
}
