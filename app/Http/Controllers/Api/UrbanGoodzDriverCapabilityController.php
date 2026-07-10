<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UrbanGoodzDriverCapabilityController extends Controller
{
    private const VEHICLE_TYPES = [
        'car' => 'Car',
        'suv' => 'SUV',
        'pickup_truck' => 'Pickup Truck',
        'cargo_van' => 'Cargo Van',
        'passenger_van' => 'Passenger Van',
        'sprinter_van' => 'Sprinter Van',
        'box_truck' => 'Box Truck',
        'straight_truck' => 'Straight Truck',
        'bicycle' => 'Bicycle',
        'motorcycle' => 'Motorcycle',
        'scooter_moped' => 'Scooter/Moped',
        'tractor_trailer_18_wheeler' => 'Tractor Trailer / 18-Wheeler',
        'flatbed_truck' => 'Flatbed Truck',
        'tow_truck' => 'Tow Truck',
        'refrigerated_truck' => 'Refrigerated Truck',
        'other_commercial_vehicle' => 'Other Commercial Vehicle',
    ];

    private const TRAILER_TYPES = [
        'utility' => 'Utility',
        'enclosed' => 'Enclosed',
        'flatbed' => 'Flatbed',
        'car_hauler' => 'Car Hauler',
        'gooseneck' => 'Gooseneck',
        'fifth_wheel' => 'Fifth Wheel',
        'step_deck' => 'Step Deck',
        'lowboy' => 'Lowboy',
        'refrigerated' => 'Refrigerated',
        'dry_van' => 'Dry Van',
        'other' => 'Other',
    ];

    private const HITCH_TYPES = [
        'ball' => 'Ball',
        'pintle' => 'Pintle',
        'gooseneck' => 'Gooseneck',
        'fifth_wheel' => 'Fifth Wheel',
        'bumper_pull' => 'Bumper Pull',
        'receiver' => 'Receiver',
        'other' => 'Other',
    ];

    private const CDL_CLASSES = [
        'none' => 'No CDL',
        'class_a' => 'Class A',
        'class_b' => 'Class B',
        'class_c' => 'Class C',
    ];

    private const CDL_STATUSES = [
        'none' => 'No CDL',
        'valid' => 'Valid',
        'expired' => 'Expired',
        'suspended' => 'Suspended',
        'pending' => 'Pending',
    ];

    private const CAPABILITY_TAGS = [
        'food_delivery',
        'retail_delivery',
        'business_courier',
        'package_routes',
        'medical_courier',
        'order_anywhere',
        'event_runner',
        'rental_support',
        'hotshot',
        'expedited_freight',
        'last_mile',
        'white_glove',
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
        'load_board',
        'hotshot',
        'dedicated_route',
    ];

    private const LIGHT_VEHICLE_TYPES = ['car', 'suv', 'bicycle', 'motorcycle', 'scooter_moped'];

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

    private function isLightVehicle(?string $vehicleType): bool
    {
        return $vehicleType && in_array($vehicleType, self::LIGHT_VEHICLE_TYPES, true);
    }

    private function capabilitySummary(DeliveryMan $driver): array
    {
        $vehicleType = $this->normalizedVehicleType($driver);
        $tags = $this->cleanList($driver->capability_tags ?? []);

        if ($vehicleType && array_key_exists($vehicleType, self::VEHICLE_TYPES) && !in_array($vehicleType, $tags, true)) {
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
            'trailer' => [
                'has_trailer' => (bool) $driver->has_trailer,
                'trailer_type' => $driver->trailer_type,
                'trailer_length_feet' => $driver->trailer_length_feet !== null ? (float) $driver->trailer_length_feet : null,
                'trailer_width_feet' => $driver->trailer_width_feet !== null ? (float) $driver->trailer_width_feet : null,
                'trailer_capacity_lbs' => $driver->trailer_capacity_lbs !== null ? (float) $driver->trailer_capacity_lbs : null,
                'hitch_type' => $driver->hitch_type,
            ],
            'commercial' => [
                'cdl_status' => $driver->cdl_status,
                'cdl_class' => $driver->cdl_class,
                'cdl_number' => $driver->cdl_number,
                'dot_number' => $driver->dot_number,
                'mc_number' => $driver->mc_number,
                'has_hazmat' => (bool) $driver->has_hazmat,
                'has_cargo_insurance' => (bool) $driver->has_cargo_insurance,
            ],
            'capacity' => [
                'cargo_capacity_notes' => $driver->cargo_capacity_notes,
                'max_package_count' => $driver->max_package_count,
                'max_weight_lbs' => $driver->max_weight_lbs !== null ? (float) $driver->max_weight_lbs : null,
                'max_payload_lbs' => $driver->max_payload_lbs !== null ? (float) $driver->max_payload_lbs : null,
                'cargo_length_inches' => $driver->cargo_length_inches !== null ? (float) $driver->cargo_length_inches : null,
                'cargo_width_inches' => $driver->cargo_width_inches !== null ? (float) $driver->cargo_width_inches : null,
                'cargo_height_inches' => $driver->cargo_height_inches !== null ? (float) $driver->cargo_height_inches : null,
                'has_cargo_space' => (bool) $driver->has_cargo_space,
                'has_cooler_bag' => (bool) $driver->has_cooler_bag,
                'has_medical_courier_training' => (bool) $driver->has_medical_courier_training,
                'has_liftgate' => (bool) $driver->has_liftgate,
                'has_pallet_jack' => (bool) $driver->has_pallet_jack,
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
                'can_handle_load_board' => in_array('load_board', $this->cleanList($driver->preferred_work_types ?? []), true),
                'can_handle_hotshot' => in_array('hotshot', $tags, true),
                'can_tow_trailer' => (bool) $driver->has_trailer,
                'requires_cdl' => in_array($driver->cdl_status ?? 'none', ['valid'], true),
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
                'has_trailer' => (bool) $driver->has_trailer,
                'trailer_type' => $driver->trailer_type,
                'trailer_length_feet' => $driver->trailer_length_feet !== null ? (float) $driver->trailer_length_feet : null,
                'trailer_width_feet' => $driver->trailer_width_feet !== null ? (float) $driver->trailer_width_feet : null,
                'trailer_capacity_lbs' => $driver->trailer_capacity_lbs !== null ? (float) $driver->trailer_capacity_lbs : null,
                'hitch_type' => $driver->hitch_type,
                'trailer_plate_number' => $driver->trailer_plate_number,
                'cdl_status' => $driver->cdl_status,
                'cdl_class' => $driver->cdl_class,
                'cdl_number' => $driver->cdl_number,
                'dot_number' => $driver->dot_number,
                'mc_number' => $driver->mc_number,
                'has_pallet_jack' => (bool) $driver->has_pallet_jack,
                'has_hazmat' => (bool) $driver->has_hazmat,
                'has_cargo_insurance' => (bool) $driver->has_cargo_insurance,
                'cargo_insurance_expiration' => $driver->cargo_insurance_expiration?->toDateString(),
                'max_payload_lbs' => $driver->max_payload_lbs !== null ? (float) $driver->max_payload_lbs : null,
                'cargo_length_inches' => $driver->cargo_length_inches !== null ? (float) $driver->cargo_length_inches : null,
                'cargo_width_inches' => $driver->cargo_width_inches !== null ? (float) $driver->cargo_width_inches : null,
                'cargo_height_inches' => $driver->cargo_height_inches !== null ? (float) $driver->cargo_height_inches : null,
                'vehicle_photos' => $driver->vehicle_photos,
                'registration_expiration' => $driver->registration_expiration?->toDateString(),
                'insurance_expiration' => $driver->insurance_expiration?->toDateString(),
                'inspection_expiration' => $driver->inspection_expiration?->toDateString(),
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
            'allowed_values' => self::vehicleOptions(),
        ];
    }

    public static function vehicleOptions(): array
    {
        return [
            'vehicle_types' => self::VEHICLE_TYPES,
            'trailer_types' => self::TRAILER_TYPES,
            'hitch_types' => self::HITCH_TYPES,
            'cdl_classes' => self::CDL_CLASSES,
            'cdl_statuses' => self::CDL_STATUSES,
            'capability_tags' => self::CAPABILITY_TAGS,
            'work_types' => self::WORK_TYPES,
            'availability_preferences' => ['standard', 'weekdays', 'weekends', 'evenings', 'overnight', 'on_demand'],
        ];
    }

    public static function vehicleTypesForValidation(): array
    {
        return self::VEHICLE_TYPES;
    }

    public function vehicleOptionsEndpoint(Request $request): JsonResponse
    {
        return response()->json([
            'vehicle_types' => self::VEHICLE_TYPES,
            'trailer_types' => self::TRAILER_TYPES,
            'hitch_types' => self::HITCH_TYPES,
            'cdl_classes' => self::CDL_CLASSES,
            'cdl_statuses' => self::CDL_STATUSES,
            'capability_tags' => self::CAPABILITY_TAGS,
            'work_types' => self::WORK_TYPES,
            'availability_preferences' => ['standard', 'weekdays', 'weekends', 'evenings', 'overnight', 'on_demand'],
            'validation_rules' => [
                'vehicle_type' => 'One of: ' . implode(', ', array_keys(self::VEHICLE_TYPES)),
                'trailer_type' => 'One of: ' . implode(', ', array_keys(self::TRAILER_TYPES)),
                'trailer_length_feet' => 'Required when has_trailer=true. Numeric, min:0, max:100.',
                'trailer_capacity_lbs' => 'Nullable numeric. Required when has_trailer=true.',
                'hitch_type' => 'One of: ' . implode(', ', array_keys(self::HITCH_TYPES)),
                'cdl_class' => 'One of: ' . implode(', ', array_keys(self::CDL_CLASSES)),
                'cdl_status' => 'One of: ' . implode(', ', array_keys(self::CDL_STATUSES)),
                'cdl_number' => 'Required when cdl_status=valid.',
                'max_payload_lbs' => 'Nullable numeric, min:0, max:100000.',
                'cargo_dimensions' => 'Nullable numeric inches. Length x Width x Height.',
                'light_vehicle_exemptions' => 'CDL, DOT, MC, pallet_jack, hazmat, cargo_insurance, and commercial cargo dimension fields are NOT required for: ' . implode(', ', self::LIGHT_VEHICLE_TYPES) . '.',
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($this->authDriver($request)));
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'normalized_capability_summary' => $this->capabilitySummary($this->authDriver($request)),
        ]);
    }

    public function updateVehicle(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'vehicle_type' => ['nullable', 'string', Rule::in(array_keys(self::VEHICLE_TYPES))],
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

    public function updateTrailer(Request $request)
    {
        $driver = $this->authDriver($request);
        $data = $request->all();
        $hasTrailer = $data['has_trailer'] ?? $driver->has_trailer;

        $rules = [
            'has_trailer' => ['nullable', 'boolean'],
            'trailer_type' => ['nullable', 'string', Rule::in(array_keys(self::TRAILER_TYPES))],
            'trailer_length_feet' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trailer_width_feet' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'trailer_capacity_lbs' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'hitch_type' => ['nullable', 'string', Rule::in(array_keys(self::HITCH_TYPES))],
            'trailer_plate_number' => ['nullable', 'string', 'max:20'],
            'trailer_registration_expiration' => ['nullable', 'date'],
            'trailer_insurance_expiration' => ['nullable', 'date'],
        ];

        if ($hasTrailer) {
            $rules['trailer_type'] = ['required', 'string', Rule::in(array_keys(self::TRAILER_TYPES))];
            $rules['trailer_length_feet'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fields = [
            'has_trailer', 'trailer_type', 'trailer_length_feet', 'trailer_width_feet',
            'trailer_capacity_lbs', 'hitch_type', 'trailer_plate_number',
            'trailer_registration_expiration', 'trailer_insurance_expiration',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $driver->{$field} = $request->{$field};
            }
        }

        if (!$hasTrailer) {
            foreach (['trailer_type', 'trailer_length_feet', 'trailer_width_feet', 'trailer_capacity_lbs', 'hitch_type', 'trailer_plate_number', 'trailer_registration_expiration', 'trailer_insurance_expiration'] as $field) {
                if (!$request->has($field)) {
                    $driver->{$field} = null;
                }
            }
        }

        $driver->save();

        return response()->json([
            'message' => 'Trailer information updated',
        ] + $this->profilePayload($driver));
    }

    public function updateCommercial(Request $request)
    {
        $driver = $this->authDriver($request);
        $vehicleType = $request->vehicle_type ?? $this->normalizedVehicleType($driver);
        $isLight = $this->isLightVehicle($vehicleType);

        $rules = [
            'cdl_status' => ['nullable', 'string', Rule::in(array_keys(self::CDL_STATUSES))],
            'cdl_class' => ['nullable', 'string', Rule::in(array_keys(self::CDL_CLASSES))],
            'cdl_number' => ['nullable', 'string', 'max:50'],
            'dot_number' => ['nullable', 'string', 'max:50'],
            'mc_number' => ['nullable', 'string', 'max:50'],
            'has_pallet_jack' => ['nullable', 'boolean'],
            'has_hazmat' => ['nullable', 'boolean'],
            'has_cargo_insurance' => ['nullable', 'boolean'],
            'cargo_insurance_expiration' => ['nullable', 'date'],
            'registration_expiration' => ['nullable', 'date'],
            'insurance_expiration' => ['nullable', 'date'],
            'inspection_expiration' => ['nullable', 'date'],
            'vehicle_photos' => ['nullable', 'array', 'max:10'],
            'vehicle_photos.*' => ['string', 'max:500'],
        ];

        if (!$isLight) {
            $data = $request->all();
            if (($data['cdl_status'] ?? $driver->cdl_status) === 'valid') {
                $rules['cdl_class'] = ['required', 'string', Rule::in(array_keys(self::CDL_CLASSES))];
                $rules['cdl_number'] = ['required', 'string', 'max:50'];
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fields = [
            'cdl_status', 'cdl_class', 'cdl_number', 'dot_number', 'mc_number',
            'has_pallet_jack', 'has_hazmat', 'has_cargo_insurance', 'cargo_insurance_expiration',
            'registration_expiration', 'insurance_expiration', 'inspection_expiration', 'vehicle_photos',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $driver->{$field} = $request->{$field};
            }
        }

        $driver->save();

        return response()->json([
            'message' => 'Commercial credentials updated',
        ] + $this->profilePayload($driver));
    }

    public function updateCargo(Request $request)
    {
        $driver = $this->authDriver($request);
        $validator = Validator::make($request->all(), [
            'cargo_capacity_notes' => ['nullable', 'string', 'max:1000'],
            'max_package_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'max_weight_lbs' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_payload_lbs' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cargo_length_inches' => ['nullable', 'numeric', 'min:0', 'max:1200'],
            'cargo_width_inches' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'cargo_height_inches' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'has_cargo_space' => ['nullable', 'boolean'],
            'has_cooler_bag' => ['nullable', 'boolean'],
            'has_medical_courier_training' => ['nullable', 'boolean'],
            'has_liftgate' => ['nullable', 'boolean'],
            'has_pallet_jack' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach (['cargo_capacity_notes', 'max_package_count', 'max_weight_lbs', 'max_payload_lbs', 'cargo_length_inches', 'cargo_width_inches', 'cargo_height_inches', 'has_cargo_space', 'has_cooler_bag', 'has_medical_courier_training', 'has_liftgate', 'has_pallet_jack'] as $field) {
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
