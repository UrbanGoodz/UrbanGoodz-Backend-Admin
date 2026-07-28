<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\ExternalLoad;
use App\Models\UrbanGoodzLoadBoardLoad;
use Illuminate\Support\Facades\DB;
use LogicException;

class ExternalLoadPublisher
{
    /**
     * Publish a canonical external load to the runtime load board.
     *
     * @return array{load: UrbanGoodzLoadBoardLoad, already_published: bool}
     */
    public function publish(ExternalLoad $externalLoad): array
    {
        return DB::transaction(function () use ($externalLoad): array {
            $sourceLoad = ExternalLoad::with('source')
                ->lockForUpdate()
                ->findOrFail($externalLoad->getKey());

            $existing = UrbanGoodzLoadBoardLoad::where('fingerprint', $sourceLoad->fingerprint)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertSameSourceLoad($existing, $sourceLoad);

                if ($sourceLoad->status !== 'booked') {
                    $sourceLoad->update(['status' => 'booked']);
                }

                return ['load' => $existing, 'already_published' => true];
            }

            if ($sourceLoad->status !== 'available') {
                throw new LogicException('External load must be available before publishing.');
            }

            $boardLoad = UrbanGoodzLoadBoardLoad::create($this->boardAttributes($sourceLoad));
            $sourceLoad->update(['status' => 'booked']);

            return ['load' => $boardLoad, 'already_published' => false];
        });
    }

    private function assertSameSourceLoad(UrbanGoodzLoadBoardLoad $boardLoad, ExternalLoad $sourceLoad): void
    {
        if (
            (int) $boardLoad->source_id !== (int) $sourceLoad->source_id
            || (string) $boardLoad->external_id !== (string) $sourceLoad->external_id
        ) {
            throw new LogicException('Load fingerprint collision conflicts with an existing board load.');
        }
    }

    private function boardAttributes(ExternalLoad $load): array
    {
        return [
            'external_id' => $load->external_id,
            'provider' => $load->source?->source_key ?? 'sourced',
            'source_id' => $load->source_id,
            'fingerprint' => $load->fingerprint,
            'load_number' => 'UGS-' . $load->id,
            'status' => 'available',
            'origin_name' => $load->origin_address,
            'origin_city' => $load->origin_city,
            'origin_state' => $load->origin_state,
            'origin_zip' => $load->origin_zip,
            'origin_lat' => $load->origin_latitude,
            'origin_lng' => $load->origin_longitude,
            'origin_ready_at' => $load->pickup_start,
            'destination_name' => $load->destination_address,
            'destination_city' => $load->destination_city,
            'destination_state' => $load->destination_state,
            'destination_zip' => $load->destination_zip,
            'destination_lat' => $load->destination_latitude,
            'destination_lng' => $load->destination_longitude,
            'destination_due_at' => $load->delivery_end ?? $load->delivery_start,
            'distance_miles' => $load->distance_loaded,
            'payout_amount' => $load->gross_rate ?? 0,
            'rate_per_mile' => $load->rate_per_loaded_mile,
            'driver_payout_amount' => $load->estimated_driver_net,
            'processing_fee' => $load->estimated_platform_fee,
            'equipment_type' => $load->equipment_type,
            'weight_lbs' => $load->weight,
            'commodity_description' => $load->commodity,
            'special_requirements' => $load->vehicle_requirements,
            'raw_source_payload' => $load->raw_source_payload,
            'source_url' => $load->source_url,
            'expires_at' => $load->expires_at,
            'metadata' => [
                'external_load_id' => $load->id,
                'pickup_end' => $load->pickup_end?->toIso8601String(),
                'delivery_start' => $load->delivery_start?->toIso8601String(),
            ],
        ];
    }
}
