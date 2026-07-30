<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzRoutePackage;
use Illuminate\Database\QueryException;

/**
 * Records package scan events.
 *
 * Scans arrive from handheld cameras and from an offline queue that flushes
 * whenever the device regains signal, so the same physical scan can reach the
 * server more than once. When the client supplies an `idempotency_key` the
 * first event wins and every replay returns it unchanged — that is what makes
 * "duplicate scan returns a clear idempotent result" and "a queued scan
 * synchronises once" true at the same time.
 *
 * Clients that send no key keep the previous behaviour, so existing builds are
 * unaffected.
 */
class UrbanGoodzPackageScanRecorder
{
    public const IDENTIFIER_BARCODE = 'barcode';
    public const IDENTIFIER_QR = 'qr';
    public const IDENTIFIER_TRACKING = 'tracking_id';
    public const IDENTIFIER_PACKAGE_ID = 'package_id';
    public const IDENTIFIER_MANUAL = 'manual';

    /**
     * @param array<string, mixed> $attributes
     */
    public function record(
        UrbanGoodzRoutePackage $package,
        string $scanType,
        array $attributes = []
    ): UrbanGoodzPackageScan {
        $key = $attributes['idempotency_key'] ?? null;

        if ($key !== null) {
            $existing = UrbanGoodzPackageScan::where('idempotency_key', $key)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $payload = [
            'package_id' => $package->id,
            'route_id' => $attributes['route_id'] ?? $package->dedicated_route_id,
            'stop_id' => $attributes['stop_id'] ?? $package->optimizationStop?->id,
            'business_client_id' => $attributes['business_client_id'] ?? $package->business_client_id,
            'scan_type' => $scanType,
            'scanned_by' => $attributes['scanned_by'] ?? null,
            'scanner_type' => $attributes['scanner_type'] ?? 'driver',
            'identifier_type' => $attributes['identifier_type'] ?? null,
            'identifier_value' => $attributes['identifier_value'] ?? null,
            'status_before' => $attributes['status_before'] ?? null,
            'status_after' => $attributes['status_after'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'photo' => $attributes['photo'] ?? null,
            'signature' => $attributes['signature'] ?? null,
            'proof_reference' => $attributes['proof_reference'] ?? null,
            'device_source' => $attributes['device_source'] ?? null,
            'exception_reason' => $attributes['exception_reason'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'idempotency_key' => $key,
        ];

        try {
            return UrbanGoodzPackageScan::create($payload);
        } catch (QueryException $exception) {
            // Two flushes of the same queued scan raced each other; the winner's
            // event is authoritative.
            if ($key !== null) {
                $winner = UrbanGoodzPackageScan::where('idempotency_key', $key)->first();

                if ($winner !== null) {
                    return $winner;
                }
            }

            throw $exception;
        }
    }

    /**
     * Whether a scan carrying this key has already been recorded.
     */
    public function alreadyRecorded(?string $idempotencyKey): bool
    {
        if ($idempotencyKey === null) {
            return false;
        }

        return UrbanGoodzPackageScan::where('idempotency_key', $idempotencyKey)->exists();
    }

    /**
     * Classify the identifier a scan was performed with, for the audit trail.
     *
     * @param array<string, mixed> $input
     * @return array{0: string|null, 1: string|null}
     */
    public function classifyIdentifier(array $input): array
    {
        foreach ([
            self::IDENTIFIER_BARCODE => 'barcode',
            self::IDENTIFIER_QR => 'qr_code',
            self::IDENTIFIER_TRACKING => 'tracking_id',
            self::IDENTIFIER_PACKAGE_ID => 'package_id',
        ] as $type => $field) {
            if (! empty($input[$field])) {
                return [$type, (string) $input[$field]];
            }
        }

        if (! empty($input['manual_code'])) {
            return [self::IDENTIFIER_MANUAL, (string) $input['manual_code']];
        }

        return [null, null];
    }
}
