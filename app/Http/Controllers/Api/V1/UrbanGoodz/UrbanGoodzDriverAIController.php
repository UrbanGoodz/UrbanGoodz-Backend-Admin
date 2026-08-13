<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\UrbanGoodz\PackageScanAIService;
use App\Services\UrbanGoodz\VendorAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrbanGoodzDriverAIController extends Controller
{
    public function __construct(
        private VendorAIService $vendorAI,
        private PackageScanAIService $packageScanAI
    ) {}

    // ─── DAILY SUMMARY ──────────────────────────────────────────────────

    public function dailySummary(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);

        // Get today's assigned routes
        $routes = \App\Models\UrbanGoodzRouteBatch::where('delivery_man_id', $driver->id)
            ->whereDate('created_at', today())
            ->with('packages')
            ->get();

        $totalStops = $routes->sum(fn($r) => $r->packages->count());
        $totalDistance = $routes->sum('total_distance_miles');
        $estimatedEarnings = $routes->sum('estimated_earnings');

        $summary = "Good morning! You have {$routes->count()} route(s) today with {$totalStops} stops "
            . "covering ~{$totalDistance} miles. Estimated earnings: \${$estimatedEarnings}. ";

        if ($routes->count() > 0) {
            $firstRoute = $routes->first();
            $firstStop = $firstRoute->packages->first();
            if ($firstStop) {
                $summary .= "First pickup: {$firstStop->pickup_address} at {$firstStop->pickup_window_start}.";
            }
        }

        // Check for expiring documents
        $expiringSoon = $driver->certifications()
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>=', now())
            ->count();
        if ($expiringSoon) {
            $summary .= " ⚠️ {$expiringSoon} certification(s) expiring within 30 days.";
        }

        // Fatigue check
        $activeHours = $this->calculateActiveHoursToday($driver);
        if ($activeHours > 10) {
            $summary .= " ⚠️ You've been active {$activeHours}h today. Consider a break.";
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'routes_count' => $routes->count(),
            'total_stops' => $totalStops,
            'total_distance_miles' => $totalDistance,
            'estimated_earnings' => $estimatedEarnings,
            'active_hours_today' => $activeHours,
            'expiring_certifications' => $expiringSoon,
            'warnings' => $this->getDriverWarnings($driver),
        ]);
    }

    // ─── ROUTE OPTIMIZATION ─────────────────────────────────────────────

    public function optimizeRoute(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $data = $request->validate([
            'route_id' => ['required', 'integer'],
            'preference' => ['nullable', 'string', 'in:distance,time,earnings'],
        ]);

        $route = \App\Models\UrbanGoodzRouteBatch::where('id', $data['route_id'])
            ->where('delivery_man_id', $driver->id)
            ->with('packages')
            ->firstOrFail();

        // Use deterministic solver first
        $optimized = $this->solveRoute($route->packages, $data['preference'] ?? 'time');

        // AI ranks options and explains
        $aiRanking = $this->vendorAI->matchLoadToDriver(
            [
                'stops' => $optimized['stops'],
                'total_distance' => $optimized['distance'],
                'total_time' => $optimized['time'],
            ],
            [$driver->toArray()]
        );

        return response()->json([
            'success' => true,
            'original_order' => $route->packages->pluck('id')->toArray(),
            'optimized_order' => $optimized['stops'],
            'distance_miles' => $optimized['distance'],
            'estimated_time_minutes' => $optimized['time'],
            'ai_ranking' => $aiRanking['rankings'][0] ?? null,
            'explanation' => $aiRanking['notes'] ?? 'Route optimized for ' . ($data['preference'] ?? 'time'),
        ]);
    }

    // ─── LOAD RECOMMENDATION ────────────────────────────────────────────

    public function loadRecommendations(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);

        $loads = UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->where(function ($q) use ($driver) {
                $q->where('equipment_type', $driver->vehicle_type ?? 'cargo_van')
                  ->orWhere('equipment_type', 'any');
            })
            ->where('weight_lbs', '<=', $driver->max_capacity_lbs ?? 10000)
            ->orderByDesc('payout_amount')
            ->limit(10)
            ->get();

        $driverData = [
            'id' => $driver->id,
            'name' => $driver->name,
            'current_lat' => $driver->current_lat,
            'current_lng' => $driver->current_lng,
            'vehicle_type' => $driver->vehicle_type,
            'max_capacity' => $driver->max_capacity_lbs,
            'preferred_regions' => $driver->preferred_regions ?? [],
            'current_route' => $driver->current_route_id,
        ];

        $matchResult = $this->vendorAI->matchLoadToDriver(
            ['loads' => $loads->toArray()],
            [$driverData]
        );

        return response()->json([
            'success' => true,
            'loads' => $loads->map(fn($l) => [
                'id' => $l->id,
                'load_number' => $l->load_number,
                'origin' => $l->origin_full,
                'destination' => $l->destination_full,
                'payout' => '$' . number_format($l->payout_amount, 2),
                'rate_per_mile' => $l->rate_per_mile ? '$' . number_format($l->rate_per_mile, 2) : null,
                'equipment' => $l->equipment_type,
                'weight' => $l->weight_lbs,
                'distance' => $l->distance_miles,
            ]),
            'ai_matches' => $matchResult['rankings'] ?? [],
        ]);
    }

    // ─── EARNINGS COMPARISON ────────────────────────────────────────────

    public function earningsComparison(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $period = $request->input('period', 'week'); // week, month, year

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->when($period === 'week', fn($q) => $q->where('created_at', '>=', now()->subWeek()))
            ->when($period === 'month', fn($q) => $q->where('created_at', '>=', now()->subMonth()))
            ->when($period === 'year', fn($q) => $q->where('created_at', '>=', now()->subYear()))
            ->get();

        $totalEarnings = $earnings->sum('amount');
        $totalTips = $earnings->sum('tips');
        $totalOrders = $earnings->count();
        $avgPerOrder = $totalOrders > 0 ? $totalEarnings / $totalOrders : 0;
        $activeHours = $this->calculateActiveHours($driver, $period);
        $earningsPerHour = $activeHours > 0 ? $totalEarnings / $activeHours : 0;

        // Compare to platform average
        $platformAvg = \App\Models\UrbanGoodzDriverEarning::where('created_at', '>=', now()->subDays($period === 'week' ? 7 : ($period === 'month' ? 30 : 365)))
            ->avg('amount') ?? 0;

        return response()->json([
            'success' => true,
            'period' => $period,
            'total_earnings' => $totalEarnings,
            'total_tips' => $totalTips,
            'total_orders' => $totalOrders,
            'avg_per_order' => round($avgPerOrder, 2),
            'active_hours' => round($activeHours, 1),
            'earnings_per_hour' => round($earningsPerHour, 2),
            'platform_avg_per_order' => round($platformAvg, 2),
            'vs_platform' => $avgPerOrder > $platformAvg ? 'above' : 'below',
            'percentile' => $this->calculatePercentile($driver->id, $period),
        ]);
    }

    // ─── DRIVER WARNINGS ───────────────────────────────────────────────

    public function getWarnings(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);

        return response()->json([
            'success' => true,
            'warnings' => $this->getDriverWarnings($driver),
        ]);
    }

    // ─── EARNINGS PER HOUR ─────────────────────────────────────────────

    public function earningsPerHour(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $period = $request->input('period', 'week'); // week, month, year

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->when($period === 'week', fn($q) => $q->where('created_at', '>=', now()->subWeek()))
            ->when($period === 'month', fn($q) => $q->where('created_at', '>=', now()->subMonth()))
            ->when($period === 'year', fn($q) => $q->where('created_at', '>=', now()->subYear()))
            ->get();

        $totalEarnings = $earnings->sum('amount');
        $totalOrders = $earnings->count();
        $activeHours = $this->calculateActiveHours($driver, $period);
        $perHour = $activeHours > 0 ? $totalEarnings / $activeHours : 0;

        return response()->json([
            'success' => true,
            'period' => $period,
            'total_earnings' => $totalEarnings,
            'active_hours' => round($activeHours, 1),
            'earnings_per_hour' => round($perHour, 2),
            'total_orders' => $totalOrders,
            'avg_per_order' => $totalOrders > 0 ? round($totalEarnings / $totalOrders, 2) : 0,
        ]);
    }

    // ─── PACKAGE VERIFICATION ──────────────────────────────────────────

    public function verifyPickup(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'photo' => ['required', 'string'], // base64
            'gps_lat' => ['required', 'numeric'],
            'gps_lng' => ['required', 'numeric'],
        ]);

        $package = UrbanGoodzRoutePackage::where('id', $data['package_id'])
            ->whereHas('routeBatch', fn($q) => $q->where('delivery_man_id', $driver->id))
            ->firstOrFail();

        $orderData = [
            'id' => $package->order_id ?? $package->id,
            'items_summary' => $package->description,
            'package_description' => $package->package_type,
            'weight' => $package->weight,
            'pickup_address' => $package->pickup_address,
            'special_instructions' => $package->special_instructions,
        ];

        $result = $this->packageScanAI->verifyPickup($data['photo'], $orderData);
        $result['package_id'] = $package->id;
        $result['gps'] = ['lat' => $data['gps_lat'], 'lng' => $data['gps_lng']];

        // Barcode detection
        $barcodeResult = $this->packageScanAI->detectBarcodeOrLabel($data['photo']);
        $result['barcode'] = $barcodeResult;

        // Log
        $package->logActivity('driver_pickup_verification', 'AI pickup verification', [], [
            'verified' => $result['verified'] ?? false,
            'confidence' => $result['confidence'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'verification' => $result,
        ]);
    }

    public function verifyDelivery(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'photo' => ['required', 'string'],
            'gps_lat' => ['required', 'numeric'],
            'gps_lng' => ['required', 'numeric'],
            'recipient_name' => ['nullable', 'string'],
            'dropoff_instructions' => ['nullable', 'string'],
        ]);

        $package = UrbanGoodzRoutePackage::where('id', $data['package_id'])
            ->whereHas('routeBatch', fn($q) => $q->where('delivery_man_id', $driver->id))
            ->firstOrFail();

        $deliveryContext = [
            'order_id' => $package->order_id ?? $package->id,
            'delivery_address' => $package->delivery_address,
            'recipient_name' => $data['recipient_name'] ?? 'Customer',
            'dropoff_instructions' => $data['dropoff_instructions'] ?? $package->delivery_instructions,
            'package_description' => $package->description,
            'weather' => $this->getWeather($data['gps_lat'], $data['gps_lng']),
        ];

        $result = $this->packageScanAI->verifyDelivery($data['photo'], $deliveryContext);
        $result['package_id'] = $package->id;
        $result['gps'] = ['lat' => $data['gps_lat'], 'lng' => $data['gps_lng']];

        // Generate delivery proof
        if ($result['delivery_verified'] ?? false) {
            $proof = $this->packageScanAI->generateDeliveryProof([
                'order_id' => $package->order_id ?? $package->id,
                'delivery_man_id' => $driver->id,
                'photos' => [$data['photo']],
                'latitude' => $data['gps_lat'],
                'longitude' => $data['gps_lng'],
                'gps_accuracy' => $request->input('gps_accuracy'),
                'customer_signature' => $request->input('signature'),
                'signature_timestamp' => $request->input('signature_timestamp'),
                'pickup_address' => $package->pickup_address,
                'delivery_address' => $package->delivery_address,
                'instructions_followed' => true,
                'condition_assessment' => $result,
                'verification_result' => $result,
            ]);
            $result['delivery_proof'] = $proof;
        }

        return response()->json([
            'success' => true,
            'verification' => $result,
        ]);
    }

    // ─── EXCEPTION ASSISTANT ────────────────────────────────────────────

    public function handleException(Request $request): JsonResponse
    {
        $driver = $this->getDriver($request);
        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'exception_type' => ['required', 'string', 'in:unable_to_pickup,unable_to_deliver,damaged,wrong_address,recipient_unavailable,access_denied,other'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'string'],
        ]);

        $package = UrbanGoodzRoutePackage::where('id', $data['package_id'])
            ->whereHas('routeBatch', fn($q) => $q->where('delivery_man_id', $driver->id))
            ->firstOrFail();

        $guidance = $this->getExceptionGuidance($data['exception_type'], $package);

        $package->update([
            'status' => 'exception',
            'exception_type' => $data['exception_type'],
            'exception_notes' => $data['notes'],
            'exception_at' => now(),
        ]);

        $package->logActivity('driver_exception', 'Exception reported: ' . $data['exception_type'], [], [
            'type' => $data['exception_type'],
            'notes' => $data['notes'],
        ]);

        return response()->json([
            'success' => true,
            'guidance' => $guidance,
            'next_steps' => $this->getExceptionNextSteps($data['exception_type']),
        ]);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    private function getDriver(Request $request): DeliveryMan
    {
        $driverId = auth('delivery_men')->id() ?? $request->header('X-Driver-ID');
        abort_unless($driverId, 401, 'Driver authentication required.');

        return DeliveryMan::findOrFail($driverId);
    }

    private function solveRoute($packages, string $preference): array
    {
        // Simplified greedy solver - in production use OR-Tools or similar
        $stops = $packages->map(fn($p) => [
            'id' => $p->id,
            'address' => $p->pickup_address,
            'lat' => $p->pickup_lat,
            'lng' => $p->pickup_lng,
            'type' => 'pickup',
            'window_start' => $p->pickup_window_start,
            'window_end' => $p->pickup_window_end,
        ])->toArray();

        // Add delivery stops
        foreach ($packages as $p) {
            $stops[] = [
                'id' => 'd_' . $p->id,
                'address' => $p->delivery_address,
                'lat' => $p->delivery_lat,
                'lng' => $p->delivery_lng,
                'type' => 'delivery',
                'window_start' => $p->delivery_window_start,
                'window_end' => $p->delivery_window_end,
            ];
        }

        // Sort by time window then distance
        usort($stops, fn($a, $b) => ($a['window_start'] ?? 'zzz') <=> ($b['window_start'] ?? 'zzz'));

        // Calculate distance (simplified)
        $distance = 0;
        $time = 0;
        for ($i = 1; $i < count($stops); $i++) {
            if (isset($stops[$i-1]['lat'], $stops[$i]['lat'])) {
                $dist = $this->haversine($stops[$i-1], $stops[$i]);
                $distance += $dist;
                $time += $dist / 30 * 60 + 10; // 30mph avg + 10min service
            }
        }

        return [
            'stops' => array_column($stops, 'id'),
            'distance' => round($distance, 1),
            'time' => round($time),
        ];
    }

    private function haversine(array $a, array $b): float
    {
        $lat1 = deg2rad($a['lat']);
        $lon1 = deg2rad($a['lng']);
        $lat2 = deg2rad($b['lat']);
        $lon2 = deg2rad($b['lng']);
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        $h = sin($dlat/2)**2 + cos($lat1)*cos($lat2)*sin($dlon/2)**2;
        return 3959 * 2 * asin(sqrt($h)); // miles
    }

    private function getDriverWarnings(DeliveryMan $driver): array
    {
        $warnings = [];

        // Expiring docs
        $expiring = $driver->certifications()
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>=', now())
            ->count();
        if ($expiring) {
            $warnings[] = "{$expiring} certification(s) expiring within 30 days";
        }

        // Fatigue
        $activeHours = $this->calculateActiveHoursToday($driver);
        if ($activeHours > 10) {
            $warnings[] = "Active {$activeHours}h today — consider mandatory break";
        } elseif ($activeHours > 8) {
            $warnings[] = "Active {$activeHours}h today — approaching limit";
        }

        // Vehicle issues
        if ($driver->vehicle && $driver->vehicle->insurance_expiry && $driver->vehicle->insurance_expiry <= now()->addDays(30)) {
            $warnings[] = 'Vehicle insurance expiring soon';
        }

        return $warnings;
    }

    private function calculateActiveHoursToday(DeliveryMan $driver): float
    {
        $today = now()->startOfDay();
        $earnings = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->where('created_at', '>=', $today)
            ->get();

        if ($earnings->isEmpty()) return 0;

        $first = $earnings->min('created_at');
        $last = $earnings->max('created_at');
        return max(0, $first->diffInHours($last));
    }

    private function calculateActiveHours(DeliveryMan $driver, string $period): float
    {
        $start = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subYear(),
        };

        $earnings = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driver->id)
            ->where('created_at', '>=', $start)
            ->get();

        if ($earnings->isEmpty()) return 0;

        $first = $earnings->min('created_at');
        $last = $earnings->max('created_at');
        return max(0, $first->diffInHours($last));
    }

    private function calculatePercentile(int $driverId, string $period): int
    {
        $start = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subYear(),
        };

        $driverAvg = \App\Models\UrbanGoodzDriverEarning::where('delivery_man_id', $driverId)
            ->where('created_at', '>=', $start)
            ->avg('amount') ?? 0;

        $allAvgs = \App\Models\UrbanGoodzDriverEarning::where('created_at', '>=', $start)
            ->groupBy('delivery_man_id')
            ->selectRaw('delivery_man_id, AVG(amount) as avg_earning')
            ->pluck('avg_earning')
            ->toArray();

        if (empty($allAvgs)) return 50;

        $below = count(array_filter($allAvgs, fn($a) => $a < $driverAvg));
        return round(($below / count($allAvgs)) * 100);
    }

    private function getWeather(float $lat, float $lng): string
    {
        // Stub - integrate with weather API
        return 'Clear, 72°F';
    }

    private function getExceptionGuidance(string $type, $package): array
    {
        return match ($type) {
            'unable_to_pickup' => [
                'message' => 'Contact the merchant to confirm item availability and pickup readiness.',
                'actions' => ['Call merchant', 'Request reschedule', 'Mark as unavailable'],
            ],
            'unable_to_deliver' => [
                'message' => 'Attempt contact with recipient. If unavailable, follow dropoff instructions or return to merchant.',
                'actions' => ['Call recipient', 'Leave at safe location', 'Return to merchant', 'Schedule redelivery'],
            ],
            'damaged' => [
                'message' => 'Take clear photos of damage. Do not deliver damaged items without recipient acknowledgment.',
                'actions' => ['Photo damage', 'Contact support', 'Return to merchant'],
            ],
            'wrong_address' => [
                'message' => 'Verify address with recipient and dispatch. Update address if correction is minor.',
                'actions' => ['Contact recipient', 'Contact dispatch', 'Update address', 'Return to merchant'],
            ],
            'recipient_unavailable' => [
                'message' => 'Follow dropoff instructions. If none, attempt contact then leave at safe location with photo proof.',
                'actions' => ['Call recipient', 'Leave at door', 'Photo proof', 'Schedule redelivery'],
            ],
            'access_denied' => [
                'message' => 'Secure location requires authorization. Contact recipient for access code or escort.',
                'actions' => ['Contact recipient', 'Contact dispatch', 'Wait for access', 'Return item'],
            ],
            default => [
                'message' => 'Report the issue to dispatch with details.',
                'actions' => ['Contact dispatch', 'Document with photos'],
            ],
        };
    }

    private function getExceptionNextSteps(string $type): array
    {
        return match ($type) {
            'unable_to_pickup' => ['Dispatcher will contact merchant', 'You may be reassigned'],
            'unable_to_deliver' => ['Attempt redelivery per schedule', 'Item returns to merchant if failed'],
            'damaged' => ['Support will review photos', 'Insurance claim may be filed'],
            'wrong_address' => ['Address corrected in system', 'Redelivery scheduled'],
            'recipient_unavailable' => ['Redelivery attempt scheduled', 'Customer notified'],
            'access_denied' => ['Recipient contacted for access', 'Wait or return per dispatch'],
            default => ['Dispatch will follow up'],
        };
    }
}