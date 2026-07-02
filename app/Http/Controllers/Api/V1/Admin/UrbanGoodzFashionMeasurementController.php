<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Support\UrbanGoodzMeasurementSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => MeasurementRequest::query()->latest()->paginate($request->input('limit', 20)),
            'settings' => UrbanGoodzMeasurementSettings::all(),
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => MeasurementRequest::findOrFail($id),
            'settings' => UrbanGoodzMeasurementSettings::all(),
        ]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'fashion_measurements_enabled' => ['nullable', 'boolean'],
            'photo_assisted_measurements_enabled' => ['nullable', 'boolean'],
            'measurement_free_tester_mode' => ['nullable', 'boolean'],
            'platform_measurement_fee' => ['nullable', 'numeric', 'min:0'],
            'paid_measurements_enabled' => ['nullable', 'boolean'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'face_blur_required' => ['nullable', 'boolean'],
            'creator_space_measurement_photo_block_enabled' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'data' => UrbanGoodzMeasurementSettings::setMany($data),
        ]);
    }

    public function privacyStatus(Request $request, $id)
    {
        $data = $request->validate([
            'privacy_review_status' => ['required', Rule::in(['approved', 'needs_review', 'blocked'])],
            'face_blur_status' => ['nullable', Rule::in(MeasurementRequest::FACE_BLUR_STATUSES)],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $measurement = MeasurementRequest::findOrFail($id);
        $measurement->fill($data);
        $measurement->save();

        return response()->json(['success' => true, 'data' => $measurement]);
    }

    public function status(Request $request, $id)
    {
        $data = $request->validate([
            'measurement_status' => ['nullable', Rule::in(MeasurementRequest::MEASUREMENT_STATUSES)],
            'review_status' => ['nullable', Rule::in(MeasurementRequest::REVIEW_STATUSES)],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $measurement = MeasurementRequest::findOrFail($id);
        $measurement->fill($data);
        $measurement->save();

        return response()->json(['success' => true, 'data' => $measurement]);
    }
}
