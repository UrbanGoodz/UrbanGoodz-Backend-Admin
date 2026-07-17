<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\ExternalLoad;
use App\Models\LoadDuplicate;
use Illuminate\Support\Facades\Log;

class LoadNormalizer
{
    public function normalize(array $raw, int $sourceId): array
    {
        return [
            'source_id' => $sourceId,
            'external_id' => $raw['external_id'] ?? $raw['id'] ?? uniqid('ext-'),
            'source_url' => $raw['source_url'] ?? null,
            'broker_name' => $raw['broker_name'] ?? $raw['broker'] ?? null,
            'broker_contact' => $raw['broker_contact'] ?? null,
            'broker_reference' => $raw['broker_reference'] ?? $raw['reference_number'] ?? null,
            'broker_rating' => $this->castDecimal($raw['broker_rating'] ?? null, 2),
            'broker_credit_status' => $raw['broker_credit_status'] ?? 'unknown',
            'origin_address' => $raw['origin_address'] ?? null,
            'origin_city' => $raw['origin_city'] ?? $raw['origin'] ?? null,
            'origin_state' => $this->normalizeState($raw['origin_state'] ?? null),
            'origin_zip' => $raw['origin_zip'] ?? null,
            'origin_latitude' => $this->castDecimal($raw['origin_latitude'] ?? $raw['origin_lat'] ?? null, 7),
            'origin_longitude' => $this->castDecimal($raw['origin_longitude'] ?? $raw['origin_lng'] ?? null, 7),
            'destination_address' => $raw['destination_address'] ?? null,
            'destination_city' => $raw['destination_city'] ?? $raw['destination'] ?? null,
            'destination_state' => $this->normalizeState($raw['destination_state'] ?? null),
            'destination_zip' => $raw['destination_zip'] ?? null,
            'destination_latitude' => $this->castDecimal($raw['destination_latitude'] ?? $raw['dest_lat'] ?? null, 7),
            'destination_longitude' => $this->castDecimal($raw['destination_longitude'] ?? $raw['dest_lng'] ?? null, 7),
            'pickup_start' => $this->parseDateTime($raw['pickup_start'] ?? $raw['pickup_date'] ?? null),
            'pickup_end' => $this->parseDateTime($raw['pickup_end'] ?? null),
            'delivery_start' => $this->parseDateTime($raw['delivery_start'] ?? null),
            'delivery_end' => $this->parseDateTime($raw['delivery_end'] ?? $raw['delivery_date'] ?? null),
            'equipment_type' => $this->normalizeEquipment($raw['equipment_type'] ?? $raw['equipment'] ?? null),
            'trailer_type' => $raw['trailer_type'] ?? null,
            'vehicle_requirements' => $raw['vehicle_requirements'] ?? null,
            'certifications_required' => $this->parseArray($raw['certifications_required'] ?? $raw['certifications'] ?? null),
            'commodity' => $raw['commodity'] ?? $raw['description'] ?? null,
            'weight' => $this->castDecimal($raw['weight'] ?? null, 2),
            'distance_loaded' => $this->castDecimal($raw['distance_loaded'] ?? $raw['miles'] ?? null, 2),
            'distance_deadhead' => $this->castDecimal($raw['distance_deadhead'] ?? $raw['deadhead'] ?? null, 2),
            'gross_rate' => $this->castDecimal($raw['gross_rate'] ?? $raw['rate'] ?? $raw['price'] ?? null, 2),
            'rate_per_loaded_mile' => $this->castDecimal($raw['rate_per_loaded_mile'] ?? null, 4),
            'estimated_fuel_cost' => $this->castDecimal($raw['estimated_fuel_cost'] ?? null, 2),
            'estimated_tolls' => $this->castDecimal($raw['estimated_tolls'] ?? null, 2),
            'estimated_platform_fee' => $this->castDecimal($raw['estimated_platform_fee'] ?? null, 2),
            'estimated_driver_net' => $this->castDecimal($raw['estimated_driver_net'] ?? null, 2),
            'estimated_net_per_total_mile' => $this->castDecimal($raw['estimated_net_per_total_mile'] ?? null, 4),
            'status' => $raw['status'] ?? 'sourced',
            'compliance_status' => $raw['compliance_status'] ?? 'authorized_partner',
            'expires_at' => $this->parseDateTime($raw['expires_at'] ?? null),
            'raw_source_payload' => $raw,
        ];
    }

    public function persistNormalized(array $normalizedData): ExternalLoad
    {
        $fingerprint = ExternalLoad::generateFingerprint($normalizedData);
        $normalizedData['fingerprint'] = $fingerprint;
        $normalizedData['is_duplicate'] = false;
        $normalizedData['deduplicated_to_id'] = null;

        $existing = ExternalLoad::where('source_id', $normalizedData['source_id'])
            ->where('external_id', $normalizedData['external_id'])
            ->first();

        if ($existing) {
            $existing->update($normalizedData);
            return $existing;
        }

        $load = ExternalLoad::create($normalizedData);

        $this->checkDuplicates($load, $fingerprint);

        return $load;
    }

    private function checkDuplicates(ExternalLoad $load, string $fingerprint): void
    {
        $duplicates = ExternalLoad::where('fingerprint', $fingerprint)
            ->where('id', '!=', $load->id)
            ->where('is_duplicate', false)
            ->get();

        foreach ($duplicates as $dup) {
            LoadDuplicate::updateOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'canonical_load_id' => $dup->id,
                    'duplicate_load_id' => $load->id,
                    'similarity_score' => 1.0,
                ]
            );
            $load->update([
                'is_duplicate' => true,
                'deduplicated_to_id' => $dup->id,
            ]);
            break;
        }
    }

    private function normalizeState(?string $state): ?string
    {
        if (!$state) return null;
        $state = strtoupper(trim($state));
        $map = [
            'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
            'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
            'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID',
            'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS',
            'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME', 'MARYLAND' => 'MD',
            'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS',
            'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
            'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
            'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK',
            'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC',
            'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT',
            'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
            'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
        ];
        return $map[$state] ?? (strlen($state) === 2 ? $state : null);
    }

    private function normalizeEquipment(?string $equipment): ?string
    {
        if (!$equipment) return null;
        $e = strtolower(trim($equipment));
        return match(true) {
            str_contains($e, 'van') || str_contains($e, 'enclosed') => 'van',
            str_contains($e, 'reefer') || str_contains($e, 'refriger') => 'reefer',
            str_contains($e, 'flat') => 'flatbed',
            str_contains($e, 'tank') || str_contains($e, 'tanker') => 'tanker',
            str_contains($e, 'step') => 'step_deck',
            str_contains($e, 'lowboy') => 'lowboy',
            str_contains($e, 'car') || str_contains($e, 'auto') => 'car_hauler',
            str_contains($e, 'dry') || str_contains($e, 'box') => 'dry_van',
            default => $equipment,
        };
    }

    private function castDecimal($value, int $scale): ?string
    {
        if ($value === null || $value === '') return null;
        $num = (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
        return number_format($num, $scale, '.', '');
    }

    private function parseDateTime(?string $value): ?string
    {
        if (!$value) return null;
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseArray($value): ?array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && !empty($value)) {
            return array_map('trim', explode(',', $value));
        }
        return null;
    }
}
