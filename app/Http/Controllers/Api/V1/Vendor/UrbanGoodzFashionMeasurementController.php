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
        return response()->json([
            'success' => true,
            'data' => MeasurementRequest::query()
                ->forVendor($this->vendorId($request))
                ->latest()
                ->paginate($request->input('limit', 20)),
        ]);
    }

    public function show(Request $request, $id)
    {
        $measurement = MeasurementRequest::query()
            ->forVendor($this->vendorId($request))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $measurement]);
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

        return response()->json(['success' => true, 'data' => $measurement]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'vendor_review_fee' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor tester review fee accepted. Existing requests keep their own fee until reviewed.',
            'data' => [
                'vendor_review_fee' => (float) $data['vendor_review_fee'],
                'payment_status' => 'waived_in_tester_mode',
            ],
        ]);
    }

    private function vendorId(Request $request): ?int
    {
        return $request->vendor?->id ?? auth('vendor')->id() ?? ($request->filled('vendor_id') ? (int) $request->input('vendor_id') : null);
    }
}
