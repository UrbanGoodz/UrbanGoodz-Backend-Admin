<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzRouteBatch;
use App\Models\UrbanGoodzRouteOptimizationStop;
use App\Services\UrbanGoodz\BusinessClientAIService;
use App\Services\UrbanGoodz\PackageScanAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessAIController extends Controller
{
    public function __construct(
        private BusinessClientAIService $businessAI,
        private PackageScanAIService $packageScanAI
    ) {}

    // ─── MANIFEST IMPORT ────────────────────────────────────────────────

    public function importManifest(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,pdf,eml,msg', 'max:10240'],
            'source_type' => ['required', 'string', 'in:csv,excel,pdf,email'],
            'auto_create_packages' => ['nullable', 'boolean'],
            'duplicate_check' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $content = $this->extractFileContent($file, $data['source_type']);

        $result = $this->businessAI->parseManifest($content, [
            'client_id' => $client->id,
            'source_type' => $data['source_type'],
            'auto_create' => $data['auto_create_packages'] ?? false,
            'duplicate_check' => $data['duplicate_check'] ?? true,
        ]);

        if (($result['success'] ?? false) && ($result['auto_created'] ?? false)) {
            $created = $this->createPackagesFromParsed($client, $result['packages']);
            $result['created_packages'] = $created;
        }

        return response()->json($result);
    }

    public function previewManifest(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,pdf,eml,msg', 'max:10240'],
            'source_type' => ['required', 'string', 'in:csv,excel,pdf,email'],
        ]);

        $file = $request->file('file');
        $content = $this->extractFileContent($file, $data['source_type']);

        $result = $this->businessAI->parseManifest($content, [
            'client_id' => $client->id,
            'source_type' => $data['source_type'],
            'auto_create' => false,
            'preview_only' => true,
        ]);

        return response()->json($result);
    }

    // ─── PACKAGE POOL & GROUPING ────────────────────────────────────────

    public function packagePool(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $query = UrbanGoodzRoutePackage::where('business_client_id', $client->id)
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('region')) {
            $query->whereJsonContains('delivery_zone', $request->region);
        }
        if ($request->has('date_from')) {
            $query->whereDate('pickup_window_start', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('pickup_window_start', '<=', $request->date_to);
        }

        $packages = $query->with(['routeBatch', 'pickupLocation', 'deliveryLocation'])->get();

        $result = $this->businessAI->groupPackagesForRoutes($packages->toArray(), [
            'client_id' => $client->id,
            'max_route_distance' => $request->input('max_route_distance', 100),
            'max_stops_per_route' => $request->input('max_stops_per_route', 25),
            'vehicle_types' => $request->input('vehicle_types', ['sprinter', 'box_truck', 'cargo_van']),
        ]);

        return response()->json([
            'success' => true,
            'total_packages' => $packages->count(),
            'groups' => $result['groups'] ?? [],
            'unassigned' => $result['unassigned'] ?? [],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    /**
     * packages/group predates package-pool/packages/pool as the route name
     * for this exact grouping logic - same real, working implementation,
     * never removed after the rename.
     */
    public function groupPackages(Request $request): JsonResponse
    {
        return $this->packagePool($request);
    }

    // ─── ROUTE CREATION ─────────────────────────────────────────────────

    public function createRoute(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'package_ids' => ['required', 'array', 'min:1'],
            'package_ids.*' => ['integer'],
            'vehicle_type' => ['required', 'string', 'in:sprinter,box_truck,cargo_van,step_deck,flatbed'],
            'driver_id' => ['nullable', 'integer'],
            'route_name' => ['nullable', 'string', 'max:100'],
            'dedicated' => ['nullable', 'boolean'],
            'recurring' => ['nullable', 'boolean'],
            'recurrence_pattern' => ['nullable', 'string', 'in:daily,weekdays,weekly,custom'],
        ]);

        // Verify packages belong to client
        $packages = UrbanGoodzRoutePackage::whereIn('id', $data['package_ids'])
            ->where('business_client_id', $client->id)
            ->whereIn('status', ['pending', 'queued', 'awaiting_assignment'])
            ->get();

        if ($packages->count() !== count($data['package_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some packages not found or not available for routing',
            ], 422);
        }

        $result = $this->businessAI->optimizeRoute($packages->toArray(), [
            'vehicle_type' => $data['vehicle_type'],
            'driver_id' => $data['driver_id'],
            'dedicated' => $data['dedicated'] ?? false,
            'recurring' => $data['recurring'] ?? false,
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Route optimization failed',
                'details' => $result,
            ], 422);
        }

        // Create route batch
        $routeBatch = UrbanGoodzRouteBatch::create([
            'business_client_id' => $client->id,
            'batch_number' => 'RB-' . now()->format('YmdHis') . '-' . random_int(1000, 9999),
            'route_name' => $data['route_name'] ?? 'Route ' . now()->format('M j, g:i A'),
            'vehicle_type' => $data['vehicle_type'],
            'delivery_man_id' => $data['driver_id'],
            'status' => $data['driver_id'] ? 'assigned' : 'open',
            'dedicated' => $data['dedicated'] ?? false,
            'recurring' => $data['recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'],
            'total_distance_miles' => $result['distance_miles'] ?? 0,
            'estimated_duration_minutes' => $result['estimated_time_minutes'] ?? 0,
            'package_count' => $packages->count(),
            'optimized_stops' => $result['optimized_stops'] ?? [],
            'ai_confidence' => $result['confidence'] ?? 0,
            'ai_explanation' => $result['explanation'] ?? null,
            'created_by' => auth('business')->id(),
        ]);

        // Update packages
        $packages->each(function ($pkg) use ($routeBatch, $result) {
            $stop = collect($result['optimized_stops'] ?? [])->firstWhere('package_id', $pkg->id);
            $pkg->update([
                'route_batch_id' => $routeBatch->id,
                'status' => 'assigned',
                'sequence_number' => $stop['sequence'] ?? null,
                'assigned_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'route' => $routeBatch->fresh(['packages', 'deliveryMan']),
            'optimization' => $result,
        ]);
    }

    /**
     * route/dedicated predates createRoute's own 'dedicated' flag as the
     * route name for this - createRoute already does the real optimization
     * and route-batch creation; this just forces that flag on.
     */
    public function recommendDedicatedRoute(Request $request): JsonResponse
    {
        $request->merge(['dedicated' => true]);
        return $this->createRoute($request);
    }

    public function optimizeRoute(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'package_ids' => ['required', 'array', 'min:1'],
            'package_ids.*' => ['integer'],
            'vehicle_type' => ['required', 'string'],
            'driver_id' => ['nullable', 'integer'],
            'constraints' => ['nullable', 'array'],
        ]);

        $packages = UrbanGoodzRoutePackage::whereIn('id', $data['package_ids'])
            ->where('business_client_id', $client->id)
            ->get();

        $result = $this->businessAI->optimizeRoute($packages->toArray(), [
            'vehicle_type' => $data['vehicle_type'],
            'driver_id' => $data['driver_id'],
            'constraints' => $data['constraints'] ?? [],
        ]);

        return response()->json($result);
    }

    // ─── DRIVER MATCHING ────────────────────────────────────────────────

    public function matchDriver(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
            'auto_assign' => ['nullable', 'boolean'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::where('id', $data['route_batch_id'])
            ->where('business_client_id', $client->id)
            ->with('packages')
            ->firstOrFail();

        $availableDrivers = \App\Models\DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('business_client_id', $client->id)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'vehicle_type' => $d->vehicle_type,
                'max_capacity' => $d->max_capacity_lbs,
                'certifications' => $d->certifications->pluck('type')->toArray(),
                'current_lat' => $d->current_lat,
                'current_lng' => $d->current_lng,
                'home_zone' => $d->zone_id,
                'preferred_regions' => $d->preferred_regions ?? [],
                'shift_start' => $d->shift_start,
                'shift_end' => $d->shift_end,
                'current_route_id' => $d->current_route_id,
                'hos_remaining' => $d->hos_remaining_hours ?? 11,
            ])->toArray();

        $routeData = [
            'packages' => $routeBatch->packages->map(fn($p) => [
                'id' => $p->id,
                'pickup_address' => $p->pickup_address,
                'delivery_address' => $p->delivery_address,
                'weight' => $p->weight,
                'dimensions' => $p->dimensions,
                'pickup_window' => $p->pickup_window_start . ' - ' . $p->pickup_window_end,
                'delivery_window' => $p->delivery_window_start . ' - ' . $p->delivery_window_end,
                'service_time' => $p->service_time_minutes ?? 15,
                'priority' => $p->priority ?? 'normal',
                'requires_liftgate' => $p->requires_liftgate,
                'requires_refrigeration' => $p->requires_refrigeration,
            ])->toArray(),
            'vehicle_type' => $routeBatch->vehicle_type,
            'total_distance' => $routeBatch->total_distance_miles,
            'estimated_time' => $routeBatch->estimated_duration_minutes,
        ];

        $matchResult = $this->businessAI->matchDriverToRoute($routeData, $routeData);

        if (($matchResult['success'] ?? false) && ($data['auto_assign'] ?? false)) {
            $topDriver = $matchResult['recommended_driver_id'] ?? $matchResult['rankings'][0]['driver_id'] ?? null;
            if ($topDriver) {
                $this->assignDriver($routeBatch, $topDriver);
            }
        }

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'recommended_driver' => $matchResult['recommended_driver_id'] ?? null,
            'rankings' => $matchResult['rankings'] ?? [],
            'notes' => $matchResult['notes'] ?? null,
        ]);
    }

    public function assignDriver(UrbanGoodzRouteBatch $routeBatch, int $driverId): void
    {
        $routeBatch->update([
            'delivery_man_id' => $driverId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    // ─── PREDICTIONS ────────────────────────────────────────────────────

    public function predictCompletion(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::where('id', $data['route_batch_id'])
            ->where('business_client_id', $client->id)
            ->with('packages')
            ->firstOrFail();

        $prediction = $this->businessAI->predictRouteCompletion([
            'packages' => $routeBatch->packages->map(fn($p) => [
                'id' => $p->id,
                'pickup_address' => $p->pickup_address,
                'delivery_address' => $p->delivery_address,
                'pickup_window_start' => $p->pickup_window_start,
                'pickup_window_end' => $p->pickup_window_end,
                'delivery_window_start' => $p->delivery_window_start,
                'delivery_window_end' => $p->delivery_window_end,
                'service_time' => $p->service_time_minutes ?? 15,
                'priority' => $p->priority ?? 'normal',
                'status' => $p->status,
                'current_location' => $p->current_location,
            ])->toArray(),
            'driver_id' => $routeBatch->delivery_man_id,
            'current_time' => now(),
            'vehicle_type' => $routeBatch->vehicle_type,
            'current_distance' => $routeBatch->total_distance_miles,
            'completed_stops' => $routeBatch->packages->where('status', 'completed')->count(),
            'total_stops' => $routeBatch->package_count,
        ]);

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'predicted_completion' => $prediction['predicted_completion_time'] ?? null,
            'confidence' => $prediction['confidence'] ?? 0,
            'delay_risk' => $prediction['delay_risk'] ?? 'low',
            'major_delays' => $prediction['major_delays'] ?? [],
            'recommendations' => $prediction['recommendations'] ?? [],
        ]);
    }

    public function exceptionRisk(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::where('id', $data['route_batch_id'])
            ->where('business_client_id', $client->id)
            ->with('packages')
            ->firstOrFail();

        $risk = $this->businessAI->assessExceptionRisk([
            'packages' => $routeBatch->packages->map(fn($p) => [
                'id' => $p->id,
                'pickup_address' => $p->pickup_address,
                'delivery_address' => $p->delivery_address,
                'pickup_window' => $p->pickup_window_start . ' - ' . $p->pickup_window_end,
                'delivery_window' => $p->delivery_window_start . ' - ' . $p->delivery_window_end,
                'requires_liftgate' => $p->requires_liftgate,
                'requires_refrigeration' => $p->requires_refrigeration,
                'requires_signature' => $p->requires_signature,
                'requires_age_verification' => $p->requires_age_verification,
                'hazardous' => $p->is_hazardous,
                'current_status' => $p->status,
            ])->toArray(),
            'driver_id' => $routeBatch->delivery_man_id,
            'current_time' => now(),
            'weather' => $this->getWeatherForRoute($routeBatch),
            'traffic' => $this->getTrafficForRoute($routeBatch),
        ]);

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'overall_risk' => $risk['overall_risk'] ?? 'low',
            'high_risk_stops' => $risk['high_risk_stops'] ?? [],
            'mitigation_actions' => $risk['mitigation_actions'] ?? [],
            'confidence' => $risk['confidence'] ?? 0,
        ]);
    }

    // ─── REPORTING ──────────────────────────────────────────────────────

    public function routePerformance(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'integer'],
        ]);

        $query = UrbanGoodzRouteBatch::where('business_client_id', $client->id);

        if ($data['route_batch_id'] ?? false) {
            $query->where('id', $data['route_batch_id']);
        }
        if ($data['date_from'] ?? false) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if ($data['date_to'] ?? false) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }
        if ($data['driver_id'] ?? false) {
            $query->where('delivery_man_id', $data['driver_id']);
        }

        $routes = $query->with(['packages', 'deliveryMan'])->get();

        $analysis = $this->businessAI->analyzeRoutePerformance($routes->toArray(), [
            'date_from' => $data['date_from'] ?? now()->subDays(30)->toDateString(),
            'date_to' => $data['date_to'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'period' => [
                'from' => $data['date_from'] ?? now()->subDays(30)->toDateString(),
                'to' => $data['date_to'] ?? now()->toDateString(),
            ],
            'total_routes' => $routes->count(),
            'analysis' => $analysis,
        ]);
    }

    public function costAnomalyAlert(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $anomalies = $this->businessAI->detectCostAnomalies($client->id, [
            'lookback_days' => 30,
            'threshold_percent' => 20,
        ]);

        return response()->json([
            'success' => true,
            'anomalies' => $anomalies,
            'checked_at' => now()->toISO8601String(),
        ]);
    }

    // ─── DOCUMENT / INVOICE SUPPORT ────────────────────────────────────

    public function generateInvoiceSupport(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::where('id', $data['route_batch_id'])
            ->where('business_client_id', $client->id)
            ->with('packages')
            ->firstOrFail();

        $support = $this->businessAI->generateInvoiceSupport($routeBatch->toArray());

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'invoice_support' => $support,
            'generated_at' => now()->toISO8601String(),
        ]);
    }

    public function deliveryProofPackage(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        $data = $request->validate([
            'route_batch_id' => ['required', 'integer'],
            'format' => ['nullable', 'string', 'in:pdf,json,csv'],
        ]);

        $routeBatch = UrbanGoodzRouteBatch::where('id', $data['route_batch_id'])
            ->where('business_client_id', $client->id)
            ->with('packages.deliveryProof')
            ->firstOrFail();

        $proofPackage = $this->businessAI->compileDeliveryProofPackage($routeBatch->toArray(), [
            'format' => $data['format'] ?? 'json',
        ]);

        return response()->json([
            'success' => true,
            'route_batch_id' => $routeBatch->id,
            'proof_package' => $proofPackage,
            'format' => $data['format'] ?? 'json',
        ]);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function getClient(Request $request): UrbanGoodzBusinessClient
    {
        $user = $request->user('business');
        abort_unless($user, 401, 'Business authentication required.');

        $client = $user->client;
        abort_unless($client, 403, 'Business client profile not found.');

        return $client;
    }

    private function extractFileContent($file, string $type): string
    {
        switch ($type) {
            case 'csv':
                return $file->get();
            case 'excel':
                // Use PhpSpreadsheet to read
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
                $rows = $spreadsheet->getActiveSheet()->toArray();
                return json_encode($rows);
            case 'pdf':
                // Use PDF parser
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getPathname());
                return $pdf->getText();
            case 'email':
                // Parse .eml/.msg
                return $file->get();
            default:
                return $file->get();
        }
    }

    private function createPackagesFromParsed(UrbanGoodzBusinessClient $client, array $parsed): array
    {
        $created = [];
        foreach ($parsed as $pkg) {
            $package = UrbanGoodzRoutePackage::create([
                'business_client_id' => $client->id,
                'tracking_number' => $pkg['tracking_number'] ?? 'TRK-' . uniqid(),
                'pickup_address' => $pkg['pickup_address'] ?? '',
                'delivery_address' => $pkg['delivery_address'] ?? '',
                'pickup_lat' => $pkg['pickup_lat'] ?? null,
                'pickup_lng' => $pkg['pickup_lng'] ?? null,
                'delivery_lat' => $pkg['delivery_lat'] ?? null,
                'delivery_lng' => $pkg['delivery_lng'] ?? null,
                'pickup_window_start' => $pkg['pickup_window_start'] ?? now(),
                'pickup_window_end' => $pkg['pickup_window_end'] ?? now()->addHours(2),
                'delivery_window_start' => $pkg['delivery_window_start'] ?? now()->addHours(4),
                'delivery_window_end' => $pkg['delivery_window_end'] ?? now()->addHours(6),
                'weight' => $pkg['weight'] ?? 0,
                'dimensions' => $pkg['dimensions'] ?? null,
                'package_type' => $pkg['package_type'] ?? 'parcel',
                'requires_signature' => $pkg['requires_signature'] ?? false,
                'requires_age_verification' => $pkg['requires_age_verification'] ?? false,
                'requires_refrigeration' => $pkg['requires_refrigeration'] ?? false,
                'is_hazardous' => $pkg['is_hazardous'] ?? false,
                'service_time_minutes' => $pkg['service_time_minutes'] ?? 15,
                'priority' => $pkg['priority'] ?? 'normal',
                'description' => $pkg['description'] ?? '',
                'special_instructions' => $pkg['special_instructions'] ?? '',
                'status' => 'pending',
                'metadata' => $pkg,
            ]);
            $created[] = $package;
        }
        return $created;
    }

    private function getWeatherForRoute($routeBatch): array
    {
        // Stub - integrate with weather API
        return ['condition' => 'clear', 'temp_f' => 72, 'precipitation' => 0];
    }

    private function getTrafficForRoute($routeBatch): array
    {
        // Stub - integrate with traffic API
        return ['level' => 'normal', 'delay_minutes' => 0];
    }
}