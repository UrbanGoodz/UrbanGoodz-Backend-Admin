<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzFashionMeasurementController extends Controller
{
    public function index(Request $request)
    {
        $measurements = MeasurementRequest::query()
            ->forVendor($this->vendorId($request))
            ->latest()
            ->paginate($request->input('limit', 20));
        $measurements->through(fn (MeasurementRequest $measurement) => $this->forVendorResponse($measurement));

        return response()->json([
            'success' => true,
            'data' => $measurements,
        ]);
    }

    public function show(Request $request, $id)
    {
        $measurement = MeasurementRequest::query()
            ->forVendor($this->vendorId($request))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->forVendorResponse($measurement)]);
    }

    public function review(Request $request, $id)
    {
        $data = $request->validate([
            'vendor_review_fee' => ['nullable', 'numeric', 'min:0'],
            'tailor_notes' => ['nullable', 'string'],
            'review_status' => ['nullable', Rule::in(MeasurementRequest::REVIEW_STATUSES)],
            'measurement_status' => ['nullable', Rule::in(['ready_for_tailor_review', 'tailor_adjusted', 'approved', 'cancelled'])],
        ]);

        $measurement = MeasurementRequest::query()
            ->forVendor($this->vendorId($request))
            ->findOrFail($id);

        if (array_key_exists('vendor_review_fee', $data)) {
            $measurement->vendor_review_fee = (float) $data['vendor_review_fee'];
            $measurement->total_measurement_fee = (float) $measurement->platform_measurement_fee + (float) $data['vendor_review_fee'];
        }

        $measurement->fill(collect($data)->except('vendor_review_fee')->all());
        $measurement->payment_status = $measurement->free_tester_mode ? 'waived' : $measurement->payment_status;
        $measurement->payment_required = ! $measurement->free_tester_mode && $measurement->total_measurement_fee > 0;
        $measurement->save();

        return response()->json(['success' => true, 'data' => $this->forVendorResponse($measurement)]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'vendor_review_fee' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor review fee updated.',
            'data' => [
                'vendor_review_fee' => (float) $data['vendor_review_fee'],
            ],
        ]);
    }

    private function vendorId(Request $request): int
    {
        $vendorId = $request->vendor?->id;
        abort_unless($vendorId, 401, 'Authenticated vendor is required.');

        return (int) $vendorId;
    }

    private function forVendorResponse(MeasurementRequest $measurement): array
    {
        $data = $measurement->toArray();
        $canViewPhotos = $measurement->consent_to_share_photos
            && $measurement->privacy_review_status === 'approved';

        if (! $canViewPhotos) {
            foreach ([
                'front_photo_path', 'side_photo_path', 'back_photo_path',
                'front_photo_file_id', 'side_photo_file_id', 'back_photo_file_id',
            ] as $key) {
                unset($data[$key]);
            }
            $data['photos_available'] = false;
        } else {
            $data['photos_available'] = true;
        }

        unset($data['admin_notes']);

        return $data;
    }
}
