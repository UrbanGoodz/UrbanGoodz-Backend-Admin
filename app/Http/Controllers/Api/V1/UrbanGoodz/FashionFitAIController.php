<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\UrbanGoodzServiceProvider;
use App\Services\UrbanGoodz\FashionFitAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FashionFitAIController extends Controller
{
    public function __construct(
        private FashionFitAIService $fashionFitAI
    ) {}

    // ─── PHOTO MEASUREMENT EXTRACTION ──────────────────────────────────

    public function extractMeasurements(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'string'], // base64 or data URL
            'garment_type' => ['nullable', 'string', 'in:tshirt,dress_shirt,pants,suit_jacket,dress'],
            'style_notes' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $result = $this->fashionFitAI->extractMeasurementsFromPhoto($data['photo'], [
            'garment_type' => $data['garment_type'] ?? null,
            'style_notes' => $data['style_notes'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        // If customer_id provided, create measurement request record
        if (!empty($data['customer_id']) && ($result['measurements'] ?? null)) {
            $requestId = \DB::table('urban_goodz_measurement_requests')->insertGetId([
                'customer_id' => $data['customer_id'],
                'preferred_fit' => $data['garment_type'] ?? 'regular',
                'height' => $result['measurements']['height'] ?? null,
                'chest_bust' => $result['measurements']['chest'] ?? null,
                'waist' => $result['measurements']['waist'] ?? null,
                'hips' => $result['measurements']['hips'] ?? null,
                'inseam' => $result['measurements']['inseam'] ?? null,
                'shoulder_width' => $result['measurements']['shoulders'] ?? null,
                'source' => 'ai_photo',
                'item_wanted' => $data['garment_type'] ?? null,
                'request_type' => $data['garment_type'] ?? null,
                'measurement_status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $result['measurement_request_id'] = $requestId;
        }

        return response()->json($result);
    }

    // ─── SIZE MATCHING ──────────────────────────────────────────────────

    public function matchSize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'measurements' => ['required', 'array'],
            'measurements.chest' => ['nullable', 'numeric', 'min:0'],
            'measurements.waist' => ['nullable', 'numeric', 'min:0'],
            'measurements.hips' => ['nullable', 'numeric', 'min:0'],
            'measurements.inseam' => ['nullable', 'numeric', 'min:0'],
            'measurements.shoulders' => ['nullable', 'numeric', 'min:0'],
            'measurements.neck' => ['nullable', 'numeric', 'min:0'],
            'measurements.sleeve' => ['nullable', 'numeric', 'min:0'],
            'garment_type' => ['required', 'string', 'in:tshirt,dress_shirt,pants,suit_jacket,dress'],
            'fit_preference' => ['nullable', 'string', 'in:loose,regular,slim'],
        ]);

        $result = $this->fashionFitAI->matchSizeToMeasurements(
            $data['measurements'],
            $data['garment_type'],
            $data['fit_preference'] ?? 'regular'
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── GARMENT ADJUSTMENTS ────────────────────────────────────────────

    public function suggestAdjustments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'measurements' => ['required', 'array'],
            'garment_type' => ['required', 'string', 'in:tshirt,dress_shirt,pants,suit_jacket,dress'],
            'style_notes' => ['nullable', 'string'],
        ]);

        $result = $this->fashionFitAI->suggestGarmentAdjustments(
            $data['measurements'],
            $data['garment_type'],
            $data['style_notes'] ?? ''
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── SIZE PROFILE ────────────────────────────────────────────────────

    public function generateSizeProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'measurements' => ['required', 'array'],
        ]);

        $result = $this->fashionFitAI->generateSizeProfile($data['measurements']);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ─── PROVIDER MATCHING ──────────────────────────────────────────────

    public function matchProviders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'garment_type' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'service_type' => ['nullable', 'string'],
        ]);

        $query = Vendor::where('type', 'fashion_fit_provider')
            ->where('is_active', true);

        if ($data['garment_type'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->where('name', 'LIKE', "%{$data['garment_type']}%")
                  ->orWhere('description', 'LIKE', "%{$data['garment_type']}%");
            });
        }

        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->where('address', 'LIKE', "%{$data['location']}%")
                  ->orWhere('city', 'LIKE', "%{$data['location']}%")
                  ->orWhere('state', 'LIKE', "%{$data['location']}%");
            });
        }

        $providers = $query->limit(10)->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'phone' => $v->phone,
                'email' => $v->email,
                'address' => $v->address,
                'rating' => $v->rating ?? 0,
            ])->toArray();

        if (empty($providers)) {
            $providers = UrbanGoodzServiceProvider::where('service_category', 'LIKE', '%fashion%')
                ->orWhere('service_category', 'LIKE', '%tailor%')
                ->where('is_active', true)
                ->limit(10)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->business_name,
                    'category' => $p->service_category,
                    'rating' => $p->rating,
                ])
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
            'total_found' => count($providers),
        ]);
    }

    // ─── QUOTE REQUEST ──────────────────────────────────────────────────

    public function requestQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'profile_uuid' => ['nullable', 'string'],
            'vendor_id' => ['required', 'integer'],
            'service_type' => ['nullable', 'string'],
            'garment_type' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'share_measurements' => ['required', 'boolean'],
            'share_photos' => ['required', 'boolean'],
        ]);

        $requestNumber = 'BA-' . strtoupper(uniqid());
        $requestId = \DB::table('urban_goodz_book_anywhere_requests')->insertGetId([
            'request_number' => $requestNumber,
            'customer_id' => $data['customer_id'],
            'service_name' => $data['garment_type'] ?? 'Fashion Fit',
            'description' => $data['notes'] ?? null,
            'preferred_date' => $data['due_date'] ?? null,
            'budget_amount' => $data['budget'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'request_id' => $requestId,
            'request_number' => $requestNumber,
            'message' => 'Quote request submitted. Provider will respond with estimate.',
        ]);
    }

    // ─── MEASUREMENT REQUESTS ───────────────────────────────────────────

    public function getMeasurementRequests(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id') ?? auth('api')->id();

        $requests = \DB::table('urban_goodz_measurement_requests')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'request_type' => $r->request_type,
                'item_wanted' => $r->item_wanted,
                'preferred_fit' => $r->preferred_fit,
                'budget' => $r->budget,
                'measurement_status' => $r->measurement_status,
                'height' => $r->height,
                'chest_bust' => $r->chest_bust,
                'waist' => $r->waist,
                'hips' => $r->hips,
                'inseam' => $r->inseam,
                'shoulder_width' => $r->shoulder_width,
                'source' => $r->source,
                'created_at' => $r->created_at,
            ]);

        return response()->json([
            'success' => true,
            'requests' => $requests,
        ]);
    }

    public function updateMeasurements(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer'],
            'measurements' => ['required', 'array'],
            'measurements.height' => ['nullable', 'numeric'],
            'measurements.chest_bust' => ['nullable', 'numeric'],
            'measurements.waist' => ['nullable', 'numeric'],
            'measurements.hips' => ['nullable', 'numeric'],
            'measurements.inseam' => ['nullable', 'numeric'],
            'measurements.shoulder_width' => ['nullable', 'numeric'],
            'measurements.neck' => ['nullable', 'numeric'],
            'measurements.sleeve' => ['nullable', 'numeric'],
        ]);

        $updated = \DB::table('urban_goodz_measurement_requests')
            ->where('id', $data['request_id'])
            ->update(array_merge(
                ['measurement_status' => 'completed', 'updated_at' => now()],
                array_map(fn($k, $v) => [$k => $v], array_keys($data['measurements']), array_values($data['measurements']))
            ));

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated ? 'Measurements updated' : 'Request not found',
        ]);
    }
}