<?php

namespace App\Services;

use App\Models\DeliveryMan;
use App\Models\UserNotification;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzDedicatedRoute;

class UrbanGoodzDriverDispatchNotificationService
{
    private const ALLOWED_PAYLOAD_KEYS = [
        'type',
        'title',
        'description',
        'job_type',
        'job_id',
        'order_id',
        'priority',
        'requires_action',
        'review_flags',
        'route_id',
        'package_id',
        'tracking_id',
        'created_by_system',
        'dedupe_key',
    ];

    public function createForDriver(
        int $deliveryManId,
        string $type,
        string $title,
        string $description,
        array $payload = [],
        ?string $dedupeKey = null
    ): ?UserNotification {
        if ($deliveryManId <= 0) {
            return null;
        }

        if (!DeliveryMan::where('id', $deliveryManId)->exists()) {
            return null;
        }

        $payload['type'] = $type;
        $payload['title'] = $title;
        $payload['description'] = $description;
        $payload['created_by_system'] = $payload['created_by_system'] ?? 'urban_goodz_dispatch';
        if ($dedupeKey !== null) {
            $payload['dedupe_key'] = $dedupeKey;
        }

        $clean = $this->allowlist($payload);

        if ($dedupeKey !== null && $this->alreadyExists($deliveryManId, $dedupeKey)) {
            return null;
        }

        return UserNotification::create([
            'delivery_man_id' => $deliveryManId,
            'data' => json_encode($clean),
        ]);
    }

    private function allowlist(array $payload): array
    {
        $out = [];
        foreach (self::ALLOWED_PAYLOAD_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if ($key === 'review_flags' && is_array($value)) {
                $value = array_values(array_filter(array_map('strval', $value)));
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private function alreadyExists(int $deliveryManId, string $dedupeKey): bool
    {
        return UserNotification::where('delivery_man_id', $deliveryManId)
            ->get()
            ->contains(function (UserNotification $n) use ($dedupeKey) {
                $data = $n->data ?? [];
                if (!is_array($data)) {
                    return false;
                }

                return ($data['dedupe_key'] ?? null) === $dedupeKey;
            });
    }

    public function notifyBusinessCourierAssigned(UrbanGoodzBusinessClientJob $job): void
    {
        $driverId = (int) $job->assigned_delivery_man_id;
        if (!$driverId) {
            return;
        }

        $this->createForDriver(
            $driverId,
            'business_courier_assigned',
            'New business courier job assigned',
            'A business courier job has been assigned to you.',
            [
                'job_type' => 'business_courier',
                'job_id' => $job->id,
                'priority' => 'normal',
                'requires_action' => true,
            ],
            'business_courier_assigned:' . $job->id . ':' . $driverId
        );

        $this->createForDriver(
            $driverId,
            'proof_required',
            'Proof required for assigned job',
            'Pickup and delivery proof will be required for this job.',
            [
                'job_type' => 'business_courier',
                'job_id' => $job->id,
                'priority' => 'normal',
                'requires_action' => true,
            ],
            'proof_required:business_courier:' . $job->id . ':' . $driverId
        );

        if ($job->job_type === 'medical_courier' || $job->courier_certification_required) {
            $this->createForDriver(
                $driverId,
                'medical_review_required',
                'Medical courier review required',
                'This assigned job requires medical courier review or training.',
                [
                    'job_type' => 'business_courier',
                    'job_id' => $job->id,
                    'priority' => 'high',
                    'requires_action' => true,
                    'review_flags' => ['medical_review_required'],
                ],
                'medical_review_required:business_courier:' . $job->id . ':' . $driverId
            );
        }
    }

    public function notifyBusinessCourierUpdated(UrbanGoodzBusinessClientJob $job): void
    {
        $driverId = (int) $job->assigned_delivery_man_id;
        if (!$driverId) {
            return;
        }

        $this->createForDriver(
            $driverId,
            'business_courier_updated',
            'Business courier job updated',
            'Your assigned business courier job status was updated.',
            [
                'job_type' => 'business_courier',
                'job_id' => $job->id,
                'priority' => 'normal',
                'requires_action' => false,
            ],
            'business_courier_updated:' . $job->id . ':' . $driverId
        );
    }

    public function notifyDedicatedRouteAssigned(UrbanGoodzDedicatedRoute $route): void
    {
        $driverId = (int) $route->assigned_driver_id;
        if (!$driverId) {
            return;
        }

        $this->createForDriver(
            $driverId,
            'dedicated_route_assigned',
            'Dedicated route assigned',
            'A dedicated route has been assigned to you.',
            [
                'job_type' => 'dedicated_route',
                'route_id' => $route->id,
                'priority' => 'normal',
                'requires_action' => true,
            ],
            'dedicated_route_assigned:' . $route->id . ':' . $driverId
        );

        if ($route->contains_age_restricted_items) {
            $this->createForDriver(
                $driverId,
                'age_verification_required',
                'Age verification required',
                'This assigned route contains age-restricted items requiring ID verification.',
                [
                    'job_type' => 'dedicated_route',
                    'route_id' => $route->id,
                    'priority' => 'high',
                    'requires_action' => true,
                    'review_flags' => ['age_restricted_review'],
                ],
                'age_verification_required:dedicated_route:' . $route->id . ':' . $driverId
            );
        }

        if ($route->route_type === 'medical_courier') {
            $this->createForDriver(
                $driverId,
                'medical_review_required',
                'Medical courier review required',
                'This assigned route requires medical courier review or training.',
                [
                    'job_type' => 'dedicated_route',
                    'route_id' => $route->id,
                    'priority' => 'high',
                    'requires_action' => true,
                    'review_flags' => ['medical_review_required'],
                ],
                'medical_review_required:dedicated_route:' . $route->id . ':' . $driverId
            );
        }
    }

    public function notifyPackageException(UrbanGoodzBusinessClientJob $job, ?string $reason = null): void
    {
        $driverId = (int) $job->assigned_delivery_man_id;
        if (!$driverId) {
            return;
        }

        $this->createForDriver(
            $driverId,
            'package_exception',
            'Package/job exception reported',
            $reason ? 'An exception was reported: ' . substr($reason, 0, 200) : 'An exception was reported for your assigned job.',
            [
                'job_type' => 'business_courier',
                'job_id' => $job->id,
                'priority' => 'high',
                'requires_action' => true,
            ],
            'package_exception:business_courier:' . $job->id . ':' . $driverId
        );
    }
}
