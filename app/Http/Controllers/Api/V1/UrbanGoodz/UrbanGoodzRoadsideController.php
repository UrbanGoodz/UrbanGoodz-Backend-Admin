<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzRoadsideRequest;
use App\Models\UrbanGoodzRoadsideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UrbanGoodzRoadsideController extends Controller
{
    /**
     * Catalogue for the request flow. Deliberately unauthenticated: the home
     * screen CTA has to be able to show what help is available before we ask
     * anyone stranded on a shoulder to log in.
     */
    public function services(Request $request): JsonResponse
    {
        $services = UrbanGoodzRoadsideService::enabled()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (UrbanGoodzRoadsideService $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'description' => $s->description,
                'icon' => $s->icon,
                'price_min_minor' => $s->base_price_min_minor,
                'price_max_minor' => $s->base_price_max_minor,
                'currency' => $s->currency,
                'pricing_note' => $s->pricing_note,
                'is_quote_only' => $s->is_quote_only,
                'samaritan_eligible' => $s->samaritan_eligible,
                'typical_duration_minutes' => $s->typical_duration_minutes,
            ]);

        return response()->json([
            'status' => 'success',
            'total_size' => $services->count(),
            'services' => $services,
        ]);
    }

    /**
     * Create a roadside request. Created as `draft`: nothing is broadcast
     * until payment succeeds, per the spec's pay-before-dispatch rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_slug' => 'required|string|max:60',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'location_notes' => 'nullable|string|max:2000',
            'vehicle_make' => 'nullable|string|max:60',
            'vehicle_model' => 'nullable|string|max:60',
            'vehicle_year' => 'nullable|string|max:8',
            'vehicle_color' => 'nullable|string|max:40',
            'vehicle_plate' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:6',
            'photos.*' => 'string|max:500',
            'is_emergency' => 'nullable|boolean',
            'allow_samaritans' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = UrbanGoodzRoadsideService::enabled()
            ->where('slug', $request->input('service_slug'))
            ->first();

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unknown or disabled roadside service.',
            ], 404);
        }

        // A customer may opt in to Samaritans, but the service itself decides
        // whether one is ever eligible. Towing and recovery stay professional
        // regardless of what the client sends.
        $allowSamaritans = (bool) $request->input('allow_samaritans', true)
            && $service->samaritan_eligible;

        $roadside = UrbanGoodzRoadsideRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_number' => 'RS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'user_id' => $request->user()?->id,
            'zone_id' => $this->resolveZoneId($request),
            'service_id' => $service->id,
            'service_slug' => $service->slug,
            'status' => 'draft',
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
            'location_notes' => $request->input('location_notes'),
            'vehicle_make' => $request->input('vehicle_make'),
            'vehicle_model' => $request->input('vehicle_model'),
            'vehicle_year' => $request->input('vehicle_year'),
            'vehicle_color' => $request->input('vehicle_color'),
            'vehicle_plate' => $request->input('vehicle_plate'),
            'notes' => $request->input('notes'),
            'photos' => $request->input('photos', []),
            'is_emergency' => (bool) $request->input('is_emergency', false),
            'allow_samaritans' => $allowSamaritans,
            'quoted_amount_minor' => $service->base_price_min_minor,
            'currency' => $service->currency,
            'payment_status' => 'unpaid',
            'broadcast_radius_miles' => UrbanGoodzRoadsideRequest::RADIUS_LADDER[0],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->present($roadside),
        ], 201);
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $roadside = $this->findForUser($request, $record);

        if (!$roadside) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $this->present($roadside)]);
    }

    public function cancel(Request $request, string $record): JsonResponse
    {
        $roadside = $this->findForUser($request, $record);

        if (!$roadside) {
            return response()->json(['status' => 'error', 'message' => 'Request not found.'], 404);
        }

        if ($roadside->isTerminal()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This request is already ' . $roadside->status . '.',
            ], 409);
        }

        $roadside->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        return response()->json(['status' => 'success', 'data' => $this->present($roadside)]);
    }

    private function findForUser(Request $request, string $record): ?UrbanGoodzRoadsideRequest
    {
        return UrbanGoodzRoadsideRequest::query()
            ->where(fn ($q) => $q->where('uuid', $record)->orWhere('request_number', $record))
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();
    }

    private function resolveZoneId(Request $request): ?int
    {
        $zone = json_decode((string) $request->header('zoneId'), true);
        return is_array($zone) && isset($zone[0]) ? (int) $zone[0] : null;
    }

    private function present(UrbanGoodzRoadsideRequest $r): array
    {
        return [
            'uuid' => $r->uuid,
            'request_number' => $r->request_number,
            'status' => $r->status,
            'service_slug' => $r->service_slug,
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'address' => $r->address,
            'is_emergency' => $r->is_emergency,
            'allow_samaritans' => $r->allow_samaritans,
            'quoted_amount_minor' => $r->quoted_amount_minor,
            'currency' => $r->currency,
            'payment_status' => $r->payment_status,
            'broadcast_radius_miles' => $r->broadcast_radius_miles,
            'assigned_provider_type' => $r->assigned_provider_type,
            'assigned_provider_id' => $r->assigned_provider_id,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
