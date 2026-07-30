<?php

namespace App\Services\UrbanGoodz;

use App\Models\DeliveryMan;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzMedicalCustodyLog;
use App\Models\UrbanGoodzPackageScan;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Models\UrbanGoodzRoutePackage;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PackageScanWorkflowService
{
    private const TERMINAL_STATUSES = [
        'delivered', 'returned_to_pickup', 'returned_to_hub',
        'returned_to_business', 'completed',
    ];

    public function process(
        UrbanGoodzDedicatedRoute $route,
        DeliveryMan $driver,
        array $event
    ): array {
        if ((int) $route->assigned_driver_id !== (int) $driver->id) {
            throw (new ModelNotFoundException())->setModel(UrbanGoodzDedicatedRoute::class);
        }

        $key = trim((string) ($event['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new DomainException('An idempotency_key is required for every package event.');
        }

        return DB::transaction(function () use ($route, $driver, $event, $key): array {
            $existing = UrbanGoodzPackageScan::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((int) $existing->scanned_by !== (int) $driver->id
                    || (int) $existing->dedicated_route_id !== (int) $route->id) {
                    throw new DomainException('The idempotency key belongs to a different route event.');
                }

                return $this->result($existing->package()->firstOrFail(), $existing, true);
            }

            $package = $this->findPackage($route, $event);
            $action = (string) $event['action'];
            $before = $package->status;
            $scanType = $this->applyTransition($route, $package, $driver, $action, $event);

            $scan = UrbanGoodzPackageScan::create([
                'package_id' => $package->id,
                'scan_type' => $scanType,
                'scanned_by' => $driver->id,
                'scanner_type' => 'driver',
                'idempotency_key' => $key,
                'business_client_id' => $route->business_client_id,
                'dedicated_route_id' => $route->id,
                'input_method' => $event['input_method'] ?? $event['identifier_type'] ?? 'manual',
                'device_id' => $event['device_id'] ?? null,
                'occurred_at' => $event['occurred_at'] ?? now(),
                'received_at' => now(),
                'was_offline' => (bool) ($event['was_offline'] ?? false),
                'latitude' => $event['latitude'] ?? null,
                'longitude' => $event['longitude'] ?? null,
                'photo' => $event['photo'] ?? null,
                'signature' => $event['signature'] ?? null,
                'exception_reason' => $event['exception_reason'] ?? null,
                'notes' => $event['notes'] ?? null,
                'metadata' => array_merge($event['metadata'] ?? [], [
                    'status_before' => $before,
                    'status_after' => $package->status,
                    'route_type' => $route->route_type,
                    'source_module' => $route->source_module ?: $route->route_type,
                ]),
            ]);

            return $this->result($package->fresh(), $scan, false);
        }, 3);
    }

    private function findPackage(
        UrbanGoodzDedicatedRoute $route,
        array $event
    ): UrbanGoodzRoutePackage {
        $identifier = trim((string) ($event['identifier'] ?? ''));
        $type = (string) ($event['identifier_type'] ?? 'manual');
        if ($identifier === '') {
            throw new DomainException('A barcode, QR code, tracking ID, or manual identifier is required.');
        }

        $query = UrbanGoodzRoutePackage::query()
            ->where('dedicated_route_id', $route->id)
            ->where('business_client_id', $route->business_client_id);

        $column = match ($type) {
            'barcode' => 'barcode',
            'qr_code' => 'qr_code',
            'tracking_id' => 'tracking_id',
            'manual' => null,
            default => throw new DomainException('Unsupported identifier_type.'),
        };

        if ($column) {
            $query->where($column, $identifier);
        } else {
            $query->where(function ($builder) use ($identifier) {
                $builder->where('tracking_id', $identifier)
                    ->orWhere('barcode', $identifier)
                    ->orWhere('qr_code', $identifier);
            });
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function applyTransition(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package,
        DeliveryMan $driver,
        string $action,
        array $event
    ): string {
        return match ($action) {
            'load' => $this->load($package),
            'pickup' => $this->pickup($package, $driver, $event),
            'delivery' => $this->delivery($route, $package, $driver, $event),
            'proof' => $this->proof($package, $event),
            'exception' => $this->exception($route, $package, $event),
            'return' => $this->returnPackage($route, $package, $event),
            'redelivery' => $this->redelivery($route, $package),
            default => throw new DomainException('Unsupported package event action.'),
        };
    }

    private function load(UrbanGoodzRoutePackage $package): string
    {
        $this->requireStatus($package, ['pending', 'ready_for_route', 'assigned', 'loaded']);
        $package->update(['status' => 'loaded']);
        return 'driver_loading';
    }

    private function pickup(
        UrbanGoodzRoutePackage $package,
        DeliveryMan $driver,
        array $event
    ): string {
        $this->requireStatus($package, ['pending', 'ready_for_route', 'assigned', 'loaded', 'picked_up']);
        $package->update([
            'status' => 'picked_up',
            'pickup_scanned_at' => $package->pickup_scanned_at ?: now(),
            'pickup_scanned_by' => $driver->id,
            'pickup_lat' => $event['latitude'] ?? $package->pickup_lat,
            'pickup_lng' => $event['longitude'] ?? $package->pickup_lng,
        ]);

        if ($package->requires_custody && !$package->custodyLogs()->where('custody_event', 'pickup')->exists()) {
            UrbanGoodzMedicalCustodyLog::create([
                'package_id' => $package->id,
                'custody_event' => 'pickup',
                'from_user_id' => $package->business_client_id,
                'from_user_type' => 'client',
                'to_user_id' => $driver->id,
                'to_user_type' => 'driver',
                'seal_intact' => true,
                'notes' => 'Idempotent package workflow pickup',
            ]);
        }

        return 'pickup';
    }

    private function delivery(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package,
        DeliveryMan $driver,
        array $event
    ): string {
        $this->requireStatus($package, [
            'picked_up', 'in_transit', 'out_for_delivery',
            'redelivery_pending', 'delivered',
        ]);
        if ($package->isDeliveryLocked()) {
            throw new DomainException('Age verification is required before delivery completion.');
        }
        if ($package->requires_photo && empty($event['photo']) && empty($package->proof_photo)) {
            throw new DomainException('Photo proof is required for this package.');
        }
        if ($package->requires_signature && empty($event['signature']) && empty($package->recipient_signature)) {
            throw new DomainException('Recipient signature is required for this package.');
        }
        $this->assertPrecedingStopsComplete($route, $package);

        $wasDelivered = $package->status === 'delivered';
        $package->update([
            'status' => 'delivered',
            'dropoff_scanned_at' => $package->dropoff_scanned_at ?: now(),
            'dropoff_scanned_by' => $driver->id,
            'proof_photo' => $event['photo'] ?? $package->proof_photo,
            'recipient_signature' => $event['signature'] ?? $package->recipient_signature,
            'delivery_result' => 'delivered',
        ]);
        if (!$wasDelivered) {
            $route->increment('completed_packages');
        }
        if ($package->requires_custody
            && !$package->custodyLogs()->where('custody_event', 'dropoff')->exists()) {
            UrbanGoodzMedicalCustodyLog::create([
                'package_id' => $package->id,
                'custody_event' => 'dropoff',
                'from_user_id' => $driver->id,
                'from_user_type' => 'driver',
                'to_user_id' => null,
                'to_user_type' => 'recipient',
                'seal_intact' => true,
                'signature' => $event['signature'] ?? $package->recipient_signature,
                'notes' => 'Idempotent package workflow dropoff',
            ]);
        }

        return 'dropoff';
    }

    private function assertPrecedingStopsComplete(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package
    ): void {
        $currentStop = UrbanGoodzRouteOptimizationStop::query()
            ->where('dedicated_route_id', $route->id)
            ->where('package_id', $package->id)
            ->first();
        if (!$currentStop) {
            return;
        }

        $unfinished = UrbanGoodzRouteOptimizationStop::query()
            ->where('dedicated_route_id', $route->id)
            ->where('stop_order', '<', $currentStop->stop_order)
            ->whereHas('package', fn ($query) => $query->whereNotIn('status', [
                'delivered', 'failed', 'unable_to_deliver',
                'returned_to_pickup', 'returned_to_hub', 'returned_to_business',
                'completed',
            ]))
            ->orderBy('stop_order')
            ->first();
        if ($unfinished) {
            throw new DomainException(
                "Complete or except stop {$unfinished->stop_order} before stop {$currentStop->stop_order}."
            );
        }
    }

    private function proof(UrbanGoodzRoutePackage $package, array $event): string
    {
        if (empty($event['photo']) && empty($event['signature'])) {
            throw new DomainException('Proof requires a photo or signature.');
        }
        $package->update([
            'proof_photo' => $event['photo'] ?? $package->proof_photo,
            'recipient_signature' => $event['signature'] ?? $package->recipient_signature,
        ]);
        return 'proof_uploaded';
    }

    private function exception(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package,
        array $event
    ): string {
        if (in_array($package->status, self::TERMINAL_STATUSES, true)) {
            throw new DomainException("A {$package->status} package cannot receive a delivery exception.");
        }
        if (empty($event['exception_reason'])) {
            throw new DomainException('exception_reason is required.');
        }
        $wasException = in_array($package->status, ['failed', 'unable_to_deliver'], true);
        $package->update([
            'status' => 'unable_to_deliver',
            'exception_reason' => $event['exception_reason'],
            'last_exception_at' => now(),
            'notes' => $event['notes'] ?? $package->notes,
        ]);
        $route->update([
            'optimization_status' => 'reoptimization_required',
            'optimization_error' => null,
        ]);
        if (!$wasException) {
            $route->increment('failed_packages');
        }
        return 'exception';
    }

    private function returnPackage(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package,
        array $event
    ): string {
        $this->requireStatus($package, [
            'unable_to_deliver', 'failed', 'return_required',
            'returning_to_pickup', 'returning_to_hub', 'returning_to_business',
            'returned_to_pickup', 'returned_to_hub', 'returned_to_business',
        ]);
        $destination = (string) ($event['return_destination'] ?? 'business');
        $status = match ($destination) {
            'pickup' => 'returned_to_pickup',
            'hub' => 'returned_to_hub',
            'business' => 'returned_to_business',
            default => throw new DomainException('Unsupported return_destination.'),
        };
        $wasReturned = str_starts_with((string) $package->status, 'returned_to_');
        $package->update([
            'status' => $status,
            'return_required' => false,
            'returned_at' => $package->returned_at ?: now(),
            'return_location' => $event['return_location'] ?? $package->return_location,
        ]);
        $route->update([
            'optimization_status' => 'reoptimization_required',
            'optimization_error' => null,
        ]);
        if (!$wasReturned) {
            $route->increment('returned_packages');
        }
        return 'return_scan';
    }

    private function redelivery(
        UrbanGoodzDedicatedRoute $route,
        UrbanGoodzRoutePackage $package
    ): string {
        $this->requireStatus($package, [
            'unable_to_deliver', 'failed', 'return_required',
            'returned_to_pickup', 'returned_to_hub', 'returned_to_business',
            'redelivery_pending',
        ]);
        $wasPending = $package->status === 'redelivery_pending';
        $package->update([
            'status' => 'redelivery_pending',
            'return_required' => false,
            'redelivery_attempts' => $wasPending
                ? $package->redelivery_attempts
                : ((int) $package->redelivery_attempts + 1),
        ]);
        $route->update([
            'optimization_status' => 'reoptimization_required',
            'optimization_error' => null,
        ]);
        if (!$wasPending && $route->failed_packages > 0) {
            $route->decrement('failed_packages');
        }
        return 'redelivery';
    }

    private function requireStatus(UrbanGoodzRoutePackage $package, array $allowed): void
    {
        if (!in_array($package->status, $allowed, true)) {
            throw new DomainException(
                "Package status {$package->status} does not allow this event."
            );
        }
    }

    private function result(
        UrbanGoodzRoutePackage $package,
        UrbanGoodzPackageScan $scan,
        bool $duplicate
    ): array {
        return [
            'duplicate' => $duplicate,
            'event_id' => $scan->id,
            'idempotency_key' => $scan->idempotency_key,
            'action' => $scan->scan_type,
            'package' => [
                'id' => $package->id,
                'tracking_id' => $package->tracking_id,
                'barcode' => $package->barcode,
                'qr_code' => $package->qr_code,
                'status' => $package->status,
                'redelivery_attempts' => (int) $package->redelivery_attempts,
            ],
        ];
    }
}
