<?php

namespace App\Services\UrbanGoodz\LoadBoard;

use Illuminate\Support\Facades\Log;

class DatAdapter extends AbstractLoadBoardProvider
{
    /**
     * DAT load board API adapter.
     *
     * API docs: https://developer.dat.com/
     * Auth: API key in X-DAT-API-Key header + session token.
     * Search endpoint: GET /loads/search
     * Load detail: GET /loads/{loadId}
     */

    public function getProviderSlug(): string
    {
        return 'dat';
    }

    protected function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-DAT-API-Key' => $this->config['api_key'] ?? '',
        ];

        if (!empty($this->config['session_token'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['session_token'];
        }

        return $headers;
    }

    public function fetchLoads(array $filters = [], int $maxResults = 100): array
    {
        $query = [
            'maxResults' => min($maxResults, 250),
            'sortField' => 'postedDate',
            'sortOrder' => 'desc',
        ];

        if (!empty($filters['origin_state'])) {
            $query['originState'] = $filters['origin_state'];
        }
        if (!empty($filters['destination_state'])) {
            $query['destinationState'] = $filters['destination_state'];
        }
        if (!empty($filters['equipment_type'])) {
            $query['equipmentType'] = $this->mapEquipmentType($filters['equipment_type']);
        }
        if (!empty($filters['min_weight'])) {
            $query['minWeight'] = $filters['min_weight'];
        }
        if (!empty($filters['max_weight'])) {
            $query['maxWeight'] = $filters['max_weight'];
        }
        if (!empty($filters['min_miles'])) {
            $query['minMiles'] = $filters['min_miles'];
        }
        if (!empty($filters['max_miles'])) {
            $query['maxMiles'] = $filters['max_miles'];
        }
        if (!empty($filters['hazmat'])) {
            $query['hazmat'] = true;
        }
        if (!empty($filters['load_date_from'])) {
            $query['loadDateFrom'] = $filters['load_date_from'];
        }
        if (!empty($filters['load_date_to'])) {
            $query['loadDateTo'] = $filters['load_date_to'];
        }

        $response = $this->get('/loads/search', $query);

        if (!$response || !isset($response['loads'])) {
            Log::info('DAT adapter: no loads returned', ['query' => $query]);
            return [];
        }

        return array_map(fn($raw) => $this->normalize($raw), $response['loads']);
    }

    public function getLoad(string $externalId): ?array
    {
        $response = $this->get("/loads/{$externalId}");

        if (!$response || !isset($response['load'])) {
            return null;
        }

        return $this->normalize($response['load']);
    }

    public function normalize(array $raw): array
    {
        $origin = $raw['origin'] ?? [];
        $destination = $raw['destination'] ?? [];

        return [
            'external_id' => (string) ($raw['loadId'] ?? $raw['externalId'] ?? ''),
            'load_number' => $raw['loadNumber'] ?? $raw['referenceNumber'] ?? null,
            'origin_name' => $origin['companyName'] ?? $origin['facilityName'] ?? null,
            'origin_city' => $origin['city'] ?? null,
            'origin_state' => $this->normalizeState($origin['state'] ?? null),
            'origin_zip' => $origin['zip'] ?? $origin['postalCode'] ?? null,
            'origin_lat' => $this->castFloat($origin['latitude'] ?? null),
            'origin_lng' => $this->castFloat($origin['longitude'] ?? null),
            'origin_ready_at' => $this->parseDateTime($origin['availableDate'] ?? $raw['loadDate'] ?? null),
            'destination_name' => $destination['companyName'] ?? $destination['facilityName'] ?? null,
            'destination_city' => $destination['city'] ?? null,
            'destination_state' => $this->normalizeState($destination['state'] ?? null),
            'destination_zip' => $destination['zip'] ?? $destination['postalCode'] ?? null,
            'destination_lat' => $this->castFloat($destination['latitude'] ?? null),
            'destination_lng' => $this->castFloat($destination['longitude'] ?? null),
            'destination_due_at' => $this->parseDateTime($destination['dueDate'] ?? null),
            'distance_miles' => $this->castFloat($raw['totalMiles'] ?? $raw['distance'] ?? null),
            'estimated_duration_minutes' => $this->castInt($raw['estimatedDriveMinutes'] ?? null),
            'payout_amount' => $this->castFloat($raw['totalPay'] ?? $raw['offeredRate'] ?? null),
            'payout_type' => 'flat',
            'rate_per_mile' => $this->castFloat($raw['ratePerMile'] ?? null),
            'load_type' => $this->mapLoadType($raw['loadType'] ?? $raw['freightType'] ?? 'ltl'),
            'equipment_type' => $this->mapEquipmentType($raw['equipmentType'] ?? $raw['tractorType'] ?? 'van'),
            'weight_lbs' => $this->castFloat($raw['weight'] ?? null),
            'length_ft' => $this->castFloat($raw['length'] ?? $raw['trailerLength'] ?? null),
            'pieces' => $this->castInt($raw['pieces'] ?? $raw['palletCount'] ?? null),
            'commodity_description' => $raw['commodity'] ?? $raw['commodityDescription'] ?? null,
            'special_requirements' => $raw['specialInstructions'] ?? null,
            'notes' => $raw['comments'] ?? $raw['driverInstructions'] ?? null,
            'is_hazmat' => $this->castBool($raw['hazmat'] ?? $raw['isHazmat'] ?? false),
            'is_temperature_controlled' => $this->castBool($raw['temperatureControlled'] ?? $raw['isReefer'] ?? false),
            'temperature_min_f' => $this->castFloat($raw['minTemp'] ?? null),
            'temperature_max_f' => $this->castFloat($raw['maxTemp'] ?? null),
            'requires_liftgate' => $this->castBool($raw['liftgateRequired'] ?? $raw['requiresLiftgate'] ?? false),
            'requires_pallet_jack' => $this->castBool($raw['palletJackRequired'] ?? false),
            'is_team_load' => $this->castBool($raw['teamRequired'] ?? $raw['isTeam'] ?? false),
            'is_expedited' => $this->castBool($raw['expedited'] ?? $raw['isExpedited'] ?? false),
            'shipper_name' => $origin['contactName'] ?? null,
            'shipper_phone' => $origin['phone'] ?? null,
            'consignee_name' => $destination['contactName'] ?? null,
            'consignee_phone' => $destination['phone'] ?? null,
            'metadata' => [
                'dat_posted_date' => $raw['postedDate'] ?? null,
                'dat_load_board_type' => $raw['loadBoardType'] ?? null,
                'dat_trailer_type' => $raw['trailerType'] ?? null,
                'original_data' => $raw,
            ],
        ];
    }

    private function mapLoadType(?string $type): string
    {
        $map = [
            'ftl' => 'ftl', 'full truckload' => 'ftl', 'full' => 'ftl',
            'ltl' => 'ltl', 'less than truckload' => 'ltl', 'partial' => 'ltl',
            'parcel' => 'parcel', 'package' => 'parcel',
            'intermodal' => 'intermodal',
            'flatbed' => 'flatbed',
            'tanker' => 'tanker',
        ];
        return $map[strtolower($type ?? '')] ?? 'ftl';
    }

    private function mapEquipmentType(?string $type): string
    {
        $map = [
            'van' => 'van', 'dry van' => 'van', 'box' => 'van',
            'reefer' => 'reefer', 'refrigerated' => 'reefer', 'temperature controlled' => 'reefer',
            'flatbed' => 'flatbed', 'flat' => 'flatbed',
            'step deck' => 'step_deck', 'stepdeck' => 'step_deck', 'lowboy' => 'step_deck',
            'tanker' => 'tanker', 'tank' => 'tanker',
            'car hauler' => 'car_hauler', 'auto transport' => 'car_hauler',
            'container' => 'container', 'chassis' => 'container',
            'spread axle' => 'spread_axle',
            'double drop' => 'double_drop', 'rgn' => 'double_drop',
        ];
        return $map[strtolower($type ?? '')] ?? 'van';
    }
}
