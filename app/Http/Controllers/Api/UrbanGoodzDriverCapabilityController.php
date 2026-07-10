<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverCapabilityController extends Controller
{
    private const CAPABILITY_TAGS = [
        'food_delivery',
        'retail_delivery',
        'business_courier',
        'package_routes',
        'medical_courier',
        'order_anywhere',
        'cargo_van',
        'pickup_truck',
        'box_truck',
        'car',
        'suv',
        'event_runner',
        'rental_support',
    ];

    private const VEHICLE_TYPES = [
        'car',
        'suv',
        'cargo_van',
        'pickup_truck',
        'box_truck',
        'van',
        'bike',
        'motorcycle',
    ];

    private const WORK_TYPES = [
        'food_delivery',
        'retail_delivery',
        'business_courier',
        'package_routes',
        'medical_courier',
        'order_anywhere',
        'event_runner',
        'rental_support',
    ];

    private function authDriver(Request $request): DeliveryMan
    {
        $driver = $request->user('delivery_man');
        if (!$driver) {
            abort(401, 'Unauthenticated driver');
        }

        return $driver->loadMissing('vehicle');
    }

    private function cleanList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                continue;
            }

            $clean = trim((string) $item);
            if ($clean !== '') {
                $items[] = $clean;
            }
        }

        return array_values(array_unique($items));
    }

    private function normalizedVehicleType(DeliveryMan $driver): ?string
    {
        if ($driver->vehicle_type) {
            return $driver->vehicle_type;
        }

        $vehicleType = $driver->vehicle?->type;
        if (!$vehicleType) {
            return null;
        }

        return strtolower(str_replace([' ', '-'], '_', trim($vehicleType)));
    }

    private function capabilitySummary(DeliveryMan $driver): array
    {
        $vehicleType = $this->normalizedVehicleType($driver);
        $tags = $this->cleanList($driver->capability_tags ?? []);

        if ($vehicleType && in_array($vehicleType, self::CAPABILITY_TAGS, true) && !in_array($vehicleType, $tags, true)) {
            $tags[] = $vehicleType;
        }

        return [
            'driver_id' => $driver->id,
            'vehicle' => [
                'vehicle_id' => $driver->vehicle_id,
                'vehicle_type' => $vehicleType,
                'vehicle_name' => $driver->vehicle?->type,
                'vehicle_status' => $driver->vehicle?->status,
            ],
            'capacity' => [
                'cargo_capacity_notes' => $driver->cargo_capacity_notes,
                'max_package_count' => $driver->max_package_count,
                'max_weight_lbs' => $driver->max_weight_lbs !== null ? (float) $driver->max_weight_lbs : null,
                'has_cargo_space' => (bool) $driver->has_cargo_space,
                'has_cooler_bag' => (bool) $driver->has_cooler_bag,
                'has_medical_courier_training' => (bool) $driver->has_medical_courier_training,
                'has_liftgate' => (bool) $driver->has_liftgate,
            ],
            'preferences' => [
                'preferred_zones' => $this->cleanList($driver->preferred_zones ?? []),
                'preferred_work_types' => $this->cleanList($driver->preferred_work_types ?? []),
                'availability_preference' => $driver->availability_preference ?: 'standard',
            ],
            'availability' => [
                'available_for_business_courier' => (bool) $driver->available_for_business_courier,
                'available_for_package_routes' => (bool) $driver->available_for_package_routes,
                'available_for_order_anywhere' => (bool) $driver->available_for_order_anywhere,
                'available_for_medical_courier' => (bool) $driver->available_for_medical_courier,
            ],
            'capability_tags' => array_values(array_unique($tags)),
            'dispatch_matching' => [
                'can_handle_food_delivery' => in_array('food_delivery', $tags, true),
                'can_handle_retail_delivery' => in_array('retail_delivery', $tags, true),
                'can_handle_business_courier' => (bool) $driver->available_for_business_courier || in_array('business_courier', $tags, true),
                'can_handle_package_routes' => (bool) $driver->available_for_package_routes || in_array('package_routes', $tags, true),
                'can_handle_order_anywhere' => (bool) $driver->available_for_order_anywhere || in_array('order_anywhere', $tags, true),
                'can_handle_medical_courier' => (bool) $driver->available_for_medical_courier || in_array('medical_courier', $tags, true),
                'has_structured_vehicle_type' => $vehicleType !== null,
                'has_capacity_profile' => $driver->max_package_count !== null || $driver->max_weight_lbs !== null || $driver->cargo_capacity_notes !== null,
            ],
        ];
    }

    private function profilePayload(DeliveryMan $driver): array
    {
        return [
            'profile' => [
                'driver_id' => $driver->id,
                'vehicle_type' => $this->normalizedVehicleType($driver),
                'vehicle_id' => $driver->vehicle_id,
                'cargo_capacity_notes' => $driver->cargo_capacity_notes,
                'max_package_count' => $driver->max_package_count,
                'max_weight_lbs' => $driver->max_weight_lbs !== null ? (float) $driver->max_weight_lbs : null,
                'has_cargo_space' => (bool) $driver->has_cargo_space,
                'has_cooler_bag' => (bool) $driver->has_cooler_bag,
                'has_medical_courier_training' => (bool) $driver->has_medical_courier_training,
                'has_liftgate' => (bool) $driver->has_liftgate,
                'preferred_zones' => $this->cleanList($driver->preferred_zones ?? []),
                'preferred_work_types' => $this->cleanList($driver->preferred_work_types ?? []),
                'capability_tags' => $this->cleanList($driver->capability_tags ?? []),
                'availability_preference' => $driver->availability_preference ?: 'standard',
                'available_for_business_courier' => (bool) $driver->available_for_business_courier,
                'available_for_package_routes' => (bool) $driver->available_for_package_routes,
                'available_for_order_anywhere' => (bool) $driver->available_for_order_anywhere,
                'available_for_medical_courier' => (bool) $driver->available_for_medical_courier,
            ],
            'vehicle' => $driver->vehicle ? [
                'id' => $driver->vehicle->id,
                'type' => $driver->vehicle->type,
                'status' => $driver->vehicle->status,
            ] : null,
            'normalized_capability_summary' => $this->capabilitySummary($driver),
            'allowed_values' => [
                'vehicle_types' => self::VEHICLE_TYPES,
                'capability_tags' => self::CAPABILITY_TAGS,
                'preferred_work_types' => self::WORK_TYPES,
                'availability_preferences' => ['standard', 'weekdays', 'weekends', 'evenings', 'overnight', 'on_demand'],
            ],
        ];
    }

    public function profile(Request $request)
    {
        return response()->json($this->profilePayload($this->authDriver($request)));
    }

    public function summary(Request $request)
    {
        return response()->json([
            'normalized_capability_summary' => $this->capabilitySummary($this->authDriver($request)),
        ]);
    }

    public function updateVehicle(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'vehicle_type' => ['nullable', 'string', Rule::in(self::VEHICLE_TYPES)],
            'vehicle_id' => ['nullable', 'integer', 'exists:d_m_vehicles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('vehicle_type')) {
            $driver->vehicle_type = $request->vehicle_type;
        }
        if ($request->has('vehicle_id')) {
            $driver->vehicle_id = $request->vehicle_id;
        }

        $driver->save();
        $driver->load('vehicle');

        return response()->json([
            'message' => 'Vehicle profile updated',
        ] + $this->profilePayload($driver));
    }

    public function updateCargo(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'cargo_capacity_notes' => ['nullable', 'string', 'max:1000'],
            'max_package_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'max_weight_lbs' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'has_cargo_space' => ['nullable', 'boolean'],
            'has_cooler_bag' => ['nullable', 'boolean'],
            'has_medical_courier_training' => ['nullable', 'boolean'],
            'has_liftgate' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach (['cargo_capacity_notes', 'max_package_count', 'max_weight_lbs', 'has_cargo_space', 'has_cooler_bag', 'has_medical_courier_training', 'has_liftgate'] as $field) {
            if ($request->has($field)) {
                $driver->{$field} = $request->{$field};
            }
        }

        $driver->save();

        return response()->json([
            'message' => 'Cargo capacity updated',
        ] + $this->profilePayload($driver));
    }

    public function updateZones(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'preferred_zones' => ['required', 'array', 'max:100'],
            'preferred_zones.*' => ['string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $driver->preferred_zones = $this->cleanList($request->preferred_zones);
        $driver->save();

        return response()->json([
            'message' => 'Preferred zones updated',
        ] + $this->profilePayload($driver));
    }

    public function updateWorkTypes(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'preferred_work_types' => ['required', 'array', 'max:50'],
            'preferred_work_types.*' => ['string', Rule::in(self::WORK_TYPES)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $driver->preferred_work_types = $this->cleanList($request->preferred_work_types);
        $driver->save();

        return response()->json([
            'message' => 'Preferred work types updated',
        ] + $this->profilePayload($driver));
    }

    public function updateTags(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'capability_tags' => ['required', 'array', 'max:50'],
            'capability_tags.*' => ['string', Rule::in(self::CAPABILITY_TAGS)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $driver->capability_tags = $this->cleanList($request->capability_tags);
        $driver->save();

        return response()->json([
            'message' => 'Capability tags updated',
        ] + $this->profilePayload($driver));
    }

    public function updateAvailability(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'availability_preference' => ['nullable', 'string', Rule::in(['standard', 'weekdays', 'weekends', 'evenings', 'overnight', 'on_demand'])],
            'available_for_business_courier' => ['nullable', 'boolean'],
            'available_for_package_routes' => ['nullable', 'boolean'],
            'available_for_order_anywhere' => ['nullable', 'boolean'],
            'available_for_medical_courier' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach (['availability_preference', 'available_for_business_courier', 'available_for_package_routes', 'available_for_order_anywhere', 'available_for_medical_courier'] as $field) {
            if ($request->has($field)) {
                $driver->{$field} = $request->{$field};
            }
        }

        $driver->save();

        return response()->json([
            'message' => 'Availability preferences updated',
        ] + $this->profilePayload($driver));
    }
}