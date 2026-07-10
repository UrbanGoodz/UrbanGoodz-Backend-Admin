<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\OrderAnywhereRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverJobDiscoveryController extends Controller
{
    private const DISCOVERY_TYPES = [
        'business_courier',
        'package_pool',
        'dedicated_route',
    ];

    private const BUSINESS_AVAILABLE_STATUSES = [
        'submitted', 'under_review', 'accepted', 'quoted', 'quote_accepted',
    ];

    private const PACKAGE_AVAILABLE_STATUSES = [
        'pending', 'pending_review', 'ready_for_route',
    ];

    private const ROUTE_AVAILABLE_STATUSES = [
        'pending', 'pending_review', 'approved',
    ];

    private const ORDER_ANYWHERE_AVAILABLE_STATUSES = [
        'pending_review', 'reviewing', 'quote_needed',
    ];

    private function authDriver(Request $request): DeliveryMan
    {
        $driver = $request->user('delivery_man');
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }

        return $driver->loadMissing('vehicle');
    }

    private function normalizeZone(string $value): string
    {
        return strtolower(trim($value));
    }

    private function zoneMatch(?string $zoneName, array $preferredZones): bool
    {
        if (!$zoneName || empty($preferredZones)) {
            return false;
        }

        $candidate = $this->normalizeZone($zoneName);

        foreach ($preferredZones as $zone) {
            $pref = $this->normalizeZone((string) $zone);
            if ($pref === '' ) {
                continue;
            }
            if ($pref === $candidate || str_contains($candidate, $pref) || str_contains($pref, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isMedicalType(?string $type): bool
    {
        return in_array($type, ['medical_courier', 'medical'], true);
    }

    private function businessJobZoneName(UrbanGoodzBusinessClientJob $job): ?string
    {
        $loc = $job->pickupLocation;
        if (!$loc) {
            return null;
        }

        return trim(implode(', ', array_filter([$loc->city, $loc->state])));
    }

    private function packageZoneName(UrbanGoodzRoutePackage $pkg): ?string
    {
        if ($pkg->dropoff_city || $pkg->dropoff_state) {
            return trim(implode(', ', array_filter([$pkg->dropoff_city, $pkg->dropoff_state])));
        }

        return null;
    }

    private function normalizeBusinessJob(UrbanGoodzBusinessClientJob $job, DeliveryMan $driver): array
    {
        $zoneName = $this->businessJobZoneName($job);
        $requiresMedical = $this->isMedicalType($job->job_type);
        $ageRestricted = false;

        $matchReasons = [];
        if ($this->zoneMatch($zoneName, $driver->preferred_zones ?? [])) {
            $matchReasons[] = 'preferred_zone_match';
        }
        if ($job->vehicle_type_needed && $driver->vehicle_type && $this->normalizeZone($job->vehicle_type_needed) === $this->normalizeZone($driver->vehicle_type)) {
            $matchReasons[] = 'vehicle_type_match';
        }
        if (in_array($job->job_type, $driver->preferred_work_types ?? [], true) || in_array($job->job_type, $driver->capability_tags ?? [], true)) {
            $matchReasons[] = 'work_type_match';
        }
        if ($job->needs_liftgate && $driver->has_liftgate) {
            $matchReasons[] = 'liftgate_match';
        }

        $reviewFlags = [];
        if ($requiresMedical && !$driver->has_medical_courier_training) {
            $reviewFlags[] = 'medical_training_required';
        }

        return [
            'job_type' => 'business_courier',
            'job_id' => $job->id,
            'title' => $job->job_number . ' (' . $job->job_type . ')',
            'status' => $job->status,
            'zone_id' => null,
            'zone_name' => $zoneName,
            'pickup_address' => $job->pickupLocation?->address,
            'dropoff_address' => $job->dropoffLocation?->address,
            'estimated_package_count' => 1,
            'vehicle_type_required' => $job->vehicle_type_needed,
            'requires_medical_training' => $requiresMedical,
            'age_restricted' => $ageRestricted,
            'match_reasons' => $matchReasons,
            'review_flags' => $reviewFlags,
            'can_view' => true,
            'can_claim' => false,
        ];
    }

    private function normalizePackage(UrbanGoodzRoutePackage $pkg, DeliveryMan $driver): array
    {
        $zoneName = $this->packageZoneName($pkg);
        $requiresMedical = $this->isMedicalType($pkg->priority);
        $ageRestricted = (bool) ($pkg->age_restricted || $pkg->requires_id_verification);

        $matchReasons = [];
        if ($this->zoneMatch($zoneName, $driver->preferred_zones ?? [])) {
            $matchReasons[] = 'preferred_zone_match';
        }
        if ($pkg->package_type && in_array($pkg->package_type, $driver->preferred_work_types ?? [], true)) {
            $matchReasons[] = 'work_type_match';
        }

        $reviewFlags = [];
        if ($requiresMedical && !$driver->has_medical_courier_training) {
            $reviewFlags[] = 'medical_training_required';
        }
        if ($ageRestricted) {
            $reviewFlags[] = 'age_restricted_review';
        }

        return [
            'job_type' => 'package_pool',
            'job_id' => $pkg->id,
            'title' => 'Package ' . ($pkg->tracking_id ?? $pkg->id),
            'status' => $pkg->status,
            'zone_id' => null,
            'zone_name' => $zoneName,
            'pickup_address' => $pkg->pickup_address,
            'dropoff_address' => $pkg->dropoff_address,
            'estimated_package_count' => 1,
            'vehicle_type_required' => null,
            'requires_medical_training' => $requiresMedical,
            'age_restricted' => $ageRestricted,
            'match_reasons' => $matchReasons,
            'review_flags' => $reviewFlags,
            'can_view' => true,
            'can_claim' => false,
        ];
    }

    private function normalizeRoute(UrbanGoodzDedicatedRoute $route, DeliveryMan $driver): array
    {
        $requiresMedical = $this->isMedicalType($route->route_type);
        $ageRestricted = (bool) $route->contains_age_restricted_items;

        $matchReasons = [];
        if ($route->pickup_location && $this->zoneMatch($route->pickup_location, $driver->preferred_zones ?? [])) {
            $matchReasons[] = 'preferred_zone_match';
        }
        if ($route->vehicle_type_required && $driver->vehicle_type && $this->normalizeZone($route->vehicle_type_required) === $this->normalizeZone($driver->vehicle_type)) {
            $matchReasons[] = 'vehicle_type_match';
        }
        if (in_array($route->route_type, $driver->preferred_work_types ?? [], true)) {
            $matchReasons[] = 'work_type_match';
        }

        $reviewFlags = [];
        if ($requiresMedical && !$driver->has_medical_courier_training) {
            $reviewFlags[] = 'medical_training_required';
        }
        if ($ageRestricted) {
            $reviewFlags[] = 'age_restricted_review';
        }

        return [
            'job_type' => 'dedicated_route',
            'job_id' => $route->id,
            'title' => 'Route ' . ($route->route_name ?? $route->id),
            'status' => $route->status,
            'zone_id' => null,
            'zone_name' => $route->pickup_location,
            'pickup_address' => $route->pickup_location,
            'dropoff_address' => $route->end_location,
            'estimated_package_count' => (int) $route->total_packages,
            'vehicle_type_required' => $route->vehicle_type_required,
            'requires_medical_training' => $requiresMedical,
            'age_restricted' => $ageRestricted,
            'match_reasons' => $matchReasons,
            'review_flags' => $reviewFlags,
            'can_view' => true,
            'can_claim' => false,
        ];
    }

    private function availableBusinessJobs(DeliveryMan $driver)
    {
        return UrbanGoodzBusinessClientJob::with(['pickupLocation', 'dropoffLocation'])
            ->whereNull('assigned_delivery_man_id')
            ->whereIn('status', self::BUSINESS_AVAILABLE_STATUSES)
            ->get()
            ->map(function ($job) use ($driver) {
                return $this->normalizeBusinessJob($job, $driver);
            });
    }

    private function availablePackages(DeliveryMan $driver)
    {
        return UrbanGoodzRoutePackage::query()
            ->whereNull('dedicated_route_id')
            ->whereIn('status', self::PACKAGE_AVAILABLE_STATUSES)
            ->get()
            ->map(function ($pkg) use ($driver) {
                return $this->normalizePackage($pkg, $driver);
            });
    }

    private function availableRoutes(DeliveryMan $driver)
    {
        return UrbanGoodzDedicatedRoute::query()
            ->whereNull('assigned_driver_id')
            ->whereIn('status', self::ROUTE_AVAILABLE_STATUSES)
            ->get()
            ->map(function ($route) use ($driver) {
                return $this->normalizeRoute($route, $driver);
            });
    }

    public function index(Request $request)
    {
        $driver = $this->authDriver($request);

        $rows = $this->availableBusinessJobs($driver)
            ->concat($this->availablePackages($driver))
            ->concat($this->availableRoutes($driver))
            ->values();

        return response()->json([
            'discovery' => $rows,
            'counts' => [
                'total' => $rows->count(),
                'business_courier' => $rows->where('job_type', 'business_courier')->count(),
                'package_pool' => $rows->where('job_type', 'package_pool')->count(),
                'dedicated_route' => $rows->where('job_type', 'dedicated_route')->count(),
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $driver = $this->authDriver($request);

        $business = $this->availableBusinessJobs($driver);
        $packages = $this->availablePackages($driver);
        $routes = $this->availableRoutes($driver);

        $orderAnywhereAvailable = OrderAnywhereRequest::query()
            ->whereNull('assigned_delivery_man_id')
            ->whereIn('status', self::ORDER_ANYWHERE_AVAILABLE_STATUSES)
            ->count();

        $medicalReview = $business->where('requires_medical_training', true)->count()
            + $packages->where('requires_medical_training', true)->count()
            + $routes->where('requires_medical_training', true)->count();

        $matchedBusiness = $business->where('match_reasons', '!=', collect())->count();
        $matchedPackages = $packages->where('match_reasons', '!=', collect())->count();
        $matchedRoutes = $routes->where('match_reasons', '!=', collect())->count();

        return response()->json([
            'summary' => [
                'business_courier_available' => $business->count(),
                'package_pool_available' => $packages->count(),
                'dedicated_routes_available' => $routes->count(),
                'order_anywhere_available' => $orderAnywhereAvailable,
                'medical_courier_review_only' => $medicalReview,
            ],
            'match_stats' => [
                'business_courier_matched' => $matchedBusiness,
                'package_pool_matched' => $matchedPackages,
                'dedicated_routes_matched' => $matchedRoutes,
            ],
        ]);
    }

    public function detail(Request $request, $type, $id)
    {
        $driver = $this->authDriver($request);

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['type' => $type, 'id' => $id],
            [
                'type' => ['required', 'string', Rule::in(self::DISCOVERY_TYPES)],
                'id' => ['required', 'integer', 'min:1'],
            ]
        );

        if ($validator->fails()) {
            abort(404);
        }

        $id = (int) $id;

        if ($type === 'business_courier') {
            $job = UrbanGoodzBusinessClientJob::with(['pickupLocation', 'dropoffLocation'])
                ->whereNull('assigned_delivery_man_id')
                ->whereIn('status', self::BUSINESS_AVAILABLE_STATUSES)
                ->whereKey($id)
                ->first();
            if (!$job) {
                abort(404);
            }
            $row = $this->normalizeBusinessJob($job, $driver);
        } elseif ($type === 'package_pool') {
            $pkg = UrbanGoodzRoutePackage::query()
                ->whereNull('dedicated_route_id')
                ->whereIn('status', self::PACKAGE_AVAILABLE_STATUSES)
                ->whereKey($id)
                ->first();
            if (!$pkg) {
                abort(404);
            }
            $row = $this->normalizePackage($pkg, $driver);
        } else {
            $route = UrbanGoodzDedicatedRoute::query()
                ->whereNull('assigned_driver_id')
                ->whereIn('status', self::ROUTE_AVAILABLE_STATUSES)
                ->whereKey($id)
                ->first();
            if (!$route) {
                abort(404);
            }
            $row = $this->normalizeRoute($route, $driver);
        }

        return response()->json([
            'job' => $row,
        ]);
    }
}
