<?php

namespace App\Http\Controllers\Api\V1\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\LoadBoardNLPService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use App\Services\UrbanGoodz\UrbanGoodzLoadBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatcherAIController extends Controller
{
    public function __construct(
        private LoadBoardNLPService $nlpService,
        private UrbanGoodzLoadBoardService $loadBoardService,
        private UrbanGoodzAIService $ai
    ) {}

    // ─── LOAD RANKING ──────────────────────────────────────────────────

    public function rankLoads(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'filters.origin_state' => ['nullable', 'string', 'size:2'],
            'filters.destination_state' => ['nullable', 'string', 'size:2'],
            'filters.equipment_type' => ['nullable', 'string'],
            'filters.min_rate' => ['nullable', 'numeric', 'min:0'],
            'filters.max_rate' => ['nullable', 'numeric', 'min:0'],
            'filters.max_weight' => ['nullable', 'numeric', 'min:0'],
            'driver_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = UrbanGoodzLoadBoardLoad::where('status', 'available');

        if (!empty($data['filters'])) {
            $f = $data['filters'];
            if (!empty($f['origin_state'])) $query->where('origin_state', $f['origin_state']);
            if (!empty($f['destination_state'])) $query->where('destination_state', $f['destination_state']);
            if (!empty($f['equipment_type'])) $query->where('equipment_type', $f['equipment_type']);
            if (!empty($f['min_rate'])) $query->where('payout_amount', '>=', $f['min_rate']);
            if (!empty($f['max_rate'])) $query->where('payout_amount', '<=', $f['max_rate']);
            if (!empty($f['max_weight'])) $query->where('weight_lbs', '<=', $f['max_weight']);
        }

        $loads = $query->orderByDesc('created_at')
            ->limit($data['limit'] ?? 50)
            ->get();

        $driverId = $data['driver_id'] ?? null;
        $driver = $driverId ? DeliveryMan::find($driverId) : null;

        $ranked = [];
        foreach ($loads as $load) {
            $match = null;
            if ($driver) {
                $matchResult = $this->nlpService->matchLoadToDriver(
                    $load->toArray(),
                    [$driver->toArray()]
                );
                $match = $matchResult['rankings'][0] ?? null;
            }

            $rateAnalysis = $this->nlpService->estimateFairRate($load->toArray());

            $ranked[] = [
                'load' => [
                    'id' => $load->id,
                    'load_number' => $load->load_number,
                    'origin' => $load->origin_full,
                    'destination' => $load->destination_full,
                    'equipment_type' => $load->equipment_type,
                    'weight_lbs' => $load->weight_lbs,
                    'payout_amount' => $load->payout_amount,
                    'rate_per_mile' => $load->rate_per_mile,
                    'distance_miles' => $load->distance_miles,
                    'is_hazmat' => $load->is_hazmat,
                    'is_expedited' => $load->is_expedited,
                ],
                'driver_match' => $match,
                'fair_rate' => $rateAnalysis,
                'margin_estimate' => $match && $rateAnalysis['estimated_rate'] ?? null
                    ? round((($load->payout_amount - $rateAnalysis['estimated_rate']) / $load->payout_amount) * 100, 1)
                    : null,
            ];
        }

        // Sort by driver match score, then by margin
        usort($ranked, fn($a, $b) => ($b['driver_match']['score'] ?? 0) <=> ($a['driver_match']['score'] ?? 0));

        return response()->json([
            'success' => true,
            'ranked_loads' => $ranked,
            'total_evaluated' => count($ranked),
        ]);
    }

    // ─── DRIVER MATCH FOR SPECIFIC LOAD ────────────────────────────────

    public function matchDriver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_id' => ['required', 'integer'],
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer'],
        ]);

        $load = UrbanGoodzLoadBoardLoad::findOrFail($data['load_id']);

        $drivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved');

        if (!empty($data['driver_ids'])) {
            $drivers->whereIn('id', $data['driver_ids']);
        }

        $drivers = $drivers->get();

        if ($drivers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No available drivers found',
            ], 404);
        }

        $matchResult = $this->nlpService->matchLoadToDriver(
            $load->toArray(),
            $drivers->toArray()
        );

        return response()->json([
            'success' => true,
            'load_id' => $load->id,
            'load_number' => $load->load_number,
            'recommendations' => $matchResult['rankings'] ?? [],
            'recommended_driver_id' => $matchResult['recommended_driver_id'] ?? null,
            'notes' => $matchResult['notes'] ?? null,
        ]);
    }

    // ─── FAIR RATE ESTIMATE ────────────────────────────────────────────

    public function estimateRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_city' => ['required', 'string'],
            'origin_state' => ['required', 'string', 'size:2'],
            'destination_city' => ['required', 'string'],
            'destination_state' => ['required', 'string', 'size:2'],
            'equipment_type' => ['required', 'string'],
            'weight_lbs' => ['nullable', 'numeric'],
            'load_type' => ['nullable', 'string'],
            'is_hazmat' => ['nullable', 'boolean'],
            'is_expedited' => ['nullable', 'boolean'],
        ]);

        $result = $this->nlpService->estimateFairRate($data);

        return response()->json([
            'success' => true,
            'estimate' => $result,
        ]);
    }

    // ─── DUPLICATE DETECTION ───────────────────────────────────────────

    public function checkDuplicates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_data' => ['required', 'array'],
        ]);

        $result = $this->nlpService->detectDuplicates($data['load_data']);

        return response()->json([
            'success' => true,
            'duplicates' => $result,
        ]);
    }

    // ─── DAILY OPS SUMMARY ─────────────────────────────────────────────

    public function opsSummary(Request $request): JsonResponse
    {
        $summary = $this->ai->generateOpsSummary();

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'generated_at' => now()->toISO8601String(),
        ]);
    }

    // ─── LOAD PARSING FROM TEXT/EMAIL ──────────────────────────────────

    public function parseLoad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
            'source' => ['nullable', 'string', 'in:manual,email,api'],
        ]);

        $result = $this->nlpService->parseLoadFromText($data['text']);

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    public function parseEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email_body' => ['required', 'string'],
            'from_address' => ['nullable', 'string', 'email'],
            'subject' => ['nullable', 'string'],
        ]);

        $result = $this->nlpService->parseLoadFromEmail($data['email_body'], $data['from_address'] ?? '');

        // If multiple loads, normalize to array
        if (isset($result['loads']) && !isset($result['equipment_type'])) {
            $result = [
                'loads' => $result['loads'],
                'sender' => $result['sender'] ?? null,
                'booking_instructions' => $result['booking_instructions'] ?? null,
                'confidence' => $result['confidence'] ?? 0,
                'parse_notes' => $result['parse_notes'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    public function parseBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $result = $this->nlpService->parseBatchLoads($data['text']);

        return response()->json([
            'success' => true,
            'parsed' => $result,
        ]);
    }

    // ─── SOURCE SYNC STATUS ────────────────────────────────────────────

    public function sourceStatus(Request $request): JsonResponse
    {
        $sources = [
            'internal' => ['name' => 'Internal', 'adapter' => 'InternalLoadAdapter', 'status' => 'active'],
            'manual' => ['name' => 'Manual Entry', 'adapter' => null, 'status' => 'active'],
            'email' => ['name' => 'Email Ingestion', 'adapter' => 'EmailLoadAdapter', 'status' => 'active'],
            'dat' => ['name' => 'DAT', 'adapter' => 'DatAdapter', 'status' => 'configured'],
            'truckstop' => ['name' => 'Truckstop', 'adapter' => 'TruckstopAdapter', 'status' => 'configured'],
            'trulos' => ['name' => 'Trulos', 'adapter' => 'TrulosAdapter', 'status' => 'pending'],
            'tb_load' => ['name' => 'TB Load', 'adapter' => 'TbLoadAdapter', 'status' => 'pending'],
            'direct_freight' => ['name' => 'Direct Freight', 'adapter' => 'DirectFreightAdapter', 'status' => 'pending'],
            'trucker_path' => ['name' => 'Trucker Path', 'adapter' => 'TruckerPathAdapter', 'status' => 'pending'],
            'truck_smarter' => ['name' => 'TruckSmarter', 'adapter' => 'TruckSmarterAdapter', 'status' => 'pending'],
        ];

        foreach ($sources as $key => &$source) {
            if ($source['adapter']) {
                $source['last_sync'] = $this->loadBoardService->getLastSync($key);
                $source['sync_status'] = $source['last_sync'] ? 'ok' : 'never';
                $source['rate_limit'] = $this->loadBoardService->getRateLimit($key);
            }
        }

        return response()->json([
            'success' => true,
            'sources' => $sources,
        ]);
    }

    public function syncSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string'],
            'full_sync' => ['nullable', 'boolean'],
        ]);

        if (!in_array($data['source'], ['dat', 'truckstop', 'email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Source not yet implemented for auto-sync',
            ], 422);
        }

        $result = $this->loadBoardService->syncFromProvider($data['source'], $data['full_sync'] ?? false);

        return response()->json([
            'success' => true,
            'source' => $data['source'],
            'result' => $result,
        ]);
    }
}