<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Support\UrbanGoodzMeasurementSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function profile(Request $request)
    {
        $customerId = $this->customerId($request);

        $profile = MeasurementRequest::query()
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $profile,
            'settings' => UrbanGoodzMeasurementSettings::all(),
        ]);
    }

    public function saveProfile(Request $request)
    {
        $data = $this->validateMeasurementData($request);

        $measurement = MeasurementRequest::create(array_merge(
            MeasurementRequest::testerDefaults(),
            $data,
            [
                'customer_id' => $this->customerId($request),
                'source' => $data['source'] ?? 'manual',
                'measurement_status' => 'manual_only',
                'payment_required' => false,
                'payment_status' => 'waived',
                'free_tester_mode' => true,
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Measurement profile saved for tester review.',
            'data' => $measurement,
        ], 201);
    }

    public function createRequest(Request $request)
    {
        $data = $this->validateMeasurementData($request);
        $settings = UrbanGoodzMeasurementSettings::all();
        $source = $request->input('source', 'manual');
        $needsPhotos = $source === 'photo_assisted';

        $measurement = MeasurementRequest::create(array_merge(
            MeasurementRequest::testerDefaults(),
            $data,
            [
                'customer_id' => $this->customerId($request),
                'source' => $source,
                'platform_measurement_fee' => 0,
                'vendor_review_fee' => (float) ($data['vendor_review_fee'] ?? 0),
                'total_measurement_fee' => (float) ($data['vendor_review_fee'] ?? 0),
                'currency' => $settings['default_currency'] ?? 'USD',
                'payment_required' => false,
                'payment_status' => 'waived',
                'free_tester_mode' => true,
                'measurement_status' => $needsPhotos ? 'photos_needed' : 'manual_only',
                'review_status' => 'pending',
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Measurement request created in free tester mode.',
            'data' => $measurement,
        ], 201);
    }

    public function photos(Request $request)
    {
        $data = $request->validate([
            'measurement_request_id' => ['required', 'integer', 'exists:urban_goodz_measurement_requests,id'],
            'front_photo' => ['nullable'],
            'side_photo' => ['nullable'],
            'back_photo' => ['nullable'],
        ]);

        $measurement = MeasurementRequest::findOrFail($data['measurement_request_id']);

        foreach (['front_photo_path' => 'front_photo', 'side_photo_path' => 'side_photo', 'back_photo_path' => 'back_photo'] as $column => $field) {
            if ($request->has($field) || $request->hasFile($field)) {
                $measurement->{$column} = 'tester-placeholder://urban-goodz/measurements/' . $measurement->id . '/' . $field;
            }
        }

        $measurement->source = 'photo_assisted';
        $measurement->measurement_status = 'photos_uploaded';
        $measurement->face_blur_enabled = true;
        $measurement->face_blur_status = 'unavailable';
        $measurement->privacy_review_status = $measurement->privacy_review_status ?: 'pending';
        $measurement->payment_required = false;
        $measurement->payment_status = 'waived';
        $measurement->free_tester_mode = true;
        $measurement->save();

        return response()->json([
            'success' => true,
            'message' => 'Tester photo placeholders attached. Production storage and face blur are not claimed.',
            'data' => $measurement,
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => MeasurementRequest::findOrFail($id),
        ]);
    }

    private function validateMeasurementData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'tailor_id' => ['nullable', 'integer'],
            'measurement_profile_id' => ['nullable', 'integer'],
            'preferred_fit' => ['nullable', 'string', 'max:100'],
            'height' => ['nullable', 'numeric'],
            'chest_bust' => ['nullable', 'numeric'],
            'waist' => ['nullable', 'numeric'],
            'hips' => ['nullable', 'numeric'],
            'inseam' => ['nullable', 'numeric'],
            'sleeve_length' => ['nullable', 'numeric'],
            'shoulder_width' => ['nullable', 'numeric'],
            'source' => ['nullable', Rule::in(['manual', 'photo_assisted'])],
            'vendor_review_fee' => ['nullable', 'numeric', 'min:0'],
            'tailor_notes' => ['nullable', 'string'],
        ]);
    }

    private function customerId(Request $request): ?int
    {
        return $request->user()?->id ?? ($request->filled('customer_id') ? (int) $request->input('customer_id') : null);
    }
}
