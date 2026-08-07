<?php

namespace App\Services\UrbanGoodz\Routing\Services;

use App\Models\UrbanGoodzBatchPackage;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzIntakeBatch;
use App\Models\UrbanGoodzRoutePackage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a scanned intake batch into a route a driver can actually run.
 *
 * This was the missing link. Scanning built rows in urban_goodz_batch_packages;
 * the optimiser reads urban_goodz_route_packages via
 * UrbanGoodzDedicatedRoute::packages(). Nothing joined the two, and
 * dedicated_routes.intake_batch_id existed but was never written -- so a
 * scanned stack could never become a sorted run.
 *
 * Batch packages are materialised into route packages rather than the
 * optimiser being pointed at the intake table. Intake is a record of what
 * arrived; a route is a plan for what gets driven. They diverge the moment a
 * package is reassigned or a second attempt is made, so they stay separate,
 * with dedicated_route_id on the batch package carrying the provenance.
 */
class BatchToRouteService
{
    /**
     * A package with no coordinate cannot be sequenced.
     *
     * This is the exact failure that produced "This route has no active stops
     * to optimize" on the existing production routes, so ungeocoded packages
     * are reported back by tracking id rather than silently dropped into a
     * route that will not optimise.
     */
    public function convert(
        UrbanGoodzIntakeBatch $batch,
        array $options = [],
        ?int $actorId = null
    ): array {
        $existing = UrbanGoodzDedicatedRoute::where('intake_batch_id', $batch->id)->first();

        if ($existing && !($options['allow_reconvert'] ?? false)) {
            throw new RuntimeException(
                "Batch {$batch->id} has already been routed as route #{$existing->id}."
            );
        }

        $packages = UrbanGoodzBatchPackage::where('intake_batch_id', $batch->id)
            ->where('is_active', true)
            ->whereNull('dedicated_route_id')
            ->orderBy('id')
            ->get();

        if ($packages->isEmpty()) {
            throw new RuntimeException("Batch {$batch->id} has no unrouted packages.");
        }

        [$routable, $skipped] = $packages->partition(
            fn (UrbanGoodzBatchPackage $p) => $p->dropoff_lat !== null && $p->dropoff_lng !== null
        );

        if ($routable->isEmpty()) {
            throw new RuntimeException(
                "None of the {$packages->count()} packages in batch {$batch->id} have delivery coordinates yet. "
                . 'They need geocoding before the route can be sequenced.'
            );
        }

        // Pickup is what the optimiser measures the first leg from. Without it
        // there is no start point and the route cannot be sequenced at all.
        $pickup = $this->resolvePickup($routable, $options);

        if ($pickup['lat'] === null || $pickup['lng'] === null) {
            throw new RuntimeException(
                'No pickup coordinates could be determined for this batch. '
                . 'Set a pickup location on the batch or pass one in.'
            );
        }

        return DB::transaction(function () use ($batch, $routable, $skipped, $pickup, $options, $actorId) {
            $route = UrbanGoodzDedicatedRoute::create([
                'business_client_id' => $batch->business_client_id,
                'intake_batch_id' => $batch->id,
                // Plain hyphen, not an em dash: route names travel through
                // scanners, CSV exports and terminal output, and a stray
                // multi-byte character reads as a mojibake box in at least one
                // of them.
                'route_name' => $options['route_name'] ?? ($batch->batch_name ?: 'Batch ' . $batch->id) . ' - Run',
                'route_type' => 'dedicated',
                'source_module' => 'package_routes',
                'status' => 'active',
                'pickup_location' => $pickup['label'],
                'pickup_lat' => $pickup['lat'],
                'pickup_lng' => $pickup['lng'],
                // The finish is left unset on purpose. The driver chooses it,
                // and choosing it is what triggers optimisation.
                'return_to_origin' => false,
                'end_lat' => null,
                'end_lng' => null,
                'assigned_driver_id' => $options['driver_id'] ?? null,
                'total_packages' => $routable->count(),
                'optimization_status' => 'not_optimized',
                'scheduled_date' => $batch->service_date ?? now()->toDateString(),
                'created_by' => $actorId,
                'driver_pay_per_package' => $options['pay_per_package'] ?? 0,
                'route_completion_bonus' => $options['completion_bonus'] ?? 0,
                'payout_model' => 'per_package',
            ]);

            foreach ($routable->values() as $i => $pkg) {
                UrbanGoodzRoutePackage::create([
                    'dedicated_route_id' => $route->id,
                    'business_client_id' => $pkg->business_client_id ?: $batch->business_client_id,
                    'tracking_id' => $pkg->tracking_id,
                    'external_reference' => $pkg->external_package_id,
                    'barcode' => $pkg->barcode,
                    'source_module' => 'package_routes',
                    'source_record_id' => $pkg->id,
                    'dropoff_name' => $pkg->recipient_name,
                    'dropoff_phone' => $pkg->recipient_phone,
                    'dropoff_address' => $pkg->dropoff_address,
                    'dropoff_city' => $pkg->dropoff_city,
                    'dropoff_state' => $pkg->dropoff_state,
                    'dropoff_zip' => $pkg->dropoff_zip,
                    'dropoff_lat' => $pkg->dropoff_lat,
                    'dropoff_lng' => $pkg->dropoff_lng,
                    'delivery_window_start' => $pkg->delivery_window_start,
                    'delivery_window_end' => $pkg->delivery_window_end,
                    'package_type' => $pkg->package_type ?: 'parcel',
                    'weight' => $pkg->weight_lbs,
                    'priority' => $pkg->priority ?: 'normal',
                    'requires_signature' => (bool) $pkg->requires_signature,
                    'requires_photo' => (bool) $pkg->requires_photo,
                    'requires_custody' => (bool) $pkg->requires_custody,
                    'age_restricted' => (bool) $pkg->age_restricted,
                    // Scan order, which the optimiser will replace. It is kept
                    // so the original sequence is recoverable and so the
                    // before/after comparison means something.
                    'stop_order' => $i + 1,
                    'status' => 'pending',
                ]);

                $pkg->update([
                    'dedicated_route_id' => $route->id,
                    'route_assignment_status' => 'assigned',
                    'stop_order' => $i + 1,
                    'finalized_at' => now(),
                ]);
            }

            return [
                'route' => $route->fresh(),
                'routed' => $routable->count(),
                // Reported, never silently dropped: somebody has to geocode
                // these before they can be delivered.
                'skipped_no_coordinates' => $skipped->pluck('tracking_id')->values()->all(),
            ];
        });
    }

    /**
     * Pickup, in order of trust: an explicit value, then the common pickup
     * recorded on the scanned packages themselves.
     */
    private function resolvePickup($routable, array $options): array
    {
        if (!empty($options['pickup_lat']) && !empty($options['pickup_lng'])) {
            return [
                'lat' => (float) $options['pickup_lat'],
                'lng' => (float) $options['pickup_lng'],
                'label' => $options['pickup_label'] ?? 'Batch pickup',
            ];
        }

        $withPickup = $routable->first(
            fn (UrbanGoodzBatchPackage $p) => $p->pickup_lat !== null && $p->pickup_lng !== null
        );

        if ($withPickup) {
            return [
                'lat' => (float) $withPickup->pickup_lat,
                'lng' => (float) $withPickup->pickup_lng,
                'label' => trim(implode(', ', array_filter([
                    $withPickup->pickup_address, $withPickup->pickup_city, $withPickup->pickup_state,
                ]))) ?: 'Batch pickup',
            ];
        }

        return ['lat' => null, 'lng' => null, 'label' => null];
    }
}
