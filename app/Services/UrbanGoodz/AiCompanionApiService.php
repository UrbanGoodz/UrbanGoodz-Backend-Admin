<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiAgent;
use App\Models\AiCompanionContext;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use App\Models\Order;
use App\Models\Store;
use App\Models\DeliveryMan;
use App\Models\OrderAnywhereRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiCompanionApiService
{
    private AiWorkforceAutonomyService $autonomyService;

    public function __construct(AiWorkforceAutonomyService $autonomyService)
    {
        $this->autonomyService = $autonomyService;
    }

    // ─── CUSTOMER COMPANION API ──────────────────────────────────────────

    public function getCustomerCompanionContext(?int $customerId, ?string $sessionId, array $params): array
    {
        if (Config::get('urban_goodz.ai_workforce.global_kill_switch', false)) {
            return ['status' => 'inactive', 'message' => 'Companion assistant is temporarily offline.'];
        }

        $zoneId = $params['zone_id'] ?? null;
        $currentPage = $params['current_page'] ?? 'home';

        // Retrieve or create session context
        $context = AiCompanionContext::firstOrCreate(
            ['user_id' => $customerId, 'session_id' => $sessionId],
            [
                'zone_id' => $zoneId,
                'current_page' => $currentPage,
                'conversation_context' => [],
                'allowed_actions' => ['product_discovery', 'vendor_discovery', 'order_anywhere'],
                'dismissal_history' => [],
                'promotion_preferences' => ['opt_in' => true],
            ]
        );

        // Check snooze
        if ($context->snooze_until && $context->snooze_until->isFuture()) {
            return ['status' => 'snoozed', 'message' => 'Assistant is snoozed.'];
        }

        // Available actions and recommendations
        $suggestedActions = [
            [
                'label' => 'Explore Popular Vendors',
                'action' => 'vendor_discovery',
                'deep_link' => 'urbangoodz://vendors/popular',
            ],
            [
                'label' => 'Submit Custom Request (Order Anywhere)',
                'action' => 'order_anywhere',
                'deep_link' => 'urbangoodz://order-anywhere/create',
            ],
        ];

        return [
            'status' => 'active',
            'suggested_actions' => $suggestedActions,
            'page' => $currentPage,
            'zone_id' => $zoneId,
            'conversation_context' => $context->conversation_context,
            'capabilities' => [
                'discover_products' => true,
                'submit_order_anywhere' => true,
                'creator_shop' => true,
            ],
        ];
    }

    // ─── DRIVER ASSISTANT API ────────────────────────────────────────────

    public function getDriverAssistantRecommendations(int $driverId, array $location): array
    {
        if (Config::get('urban_goodz.ai_workforce.global_kill_switch', false)) {
            return ['status' => 'inactive', 'recommendations' => []];
        }

        $driver = DeliveryMan::find($driverId);
        if (!$driver) {
            return ['status' => 'error', 'message' => 'Driver not found.'];
        }

        // Basic vehicle eligibility & capacity calculations
        $vehicleType = $driver->vehicle_type ?? 'car';
        $radius = 15; // default 15 miles service radius

        // Recommendations based on simulated/active data
        $recommendations = [
            [
                'type' => 'busy_zone',
                'title' => 'High Demand Zone Detected',
                'description' => 'Downtown logistics cluster is experiencing high volume. Drivers in this area are seeing increased orders.',
                'fit_score' => 95,
                'confidence' => 0.92,
                'estimated_pay' => 24.50,
                'mileage' => 4.2,
                'duration_minutes' => 20,
                'deep_link' => 'urbangoodz://driver/map?zone=downtown',
            ],
            [
                'type' => 'unassigned_route',
                'title' => 'Unassigned Parcel Route Available',
                'description' => 'A parcel delivery route matching your capacity is available.',
                'fit_score' => 88,
                'confidence' => 0.89,
                'estimated_pay' => 45.00,
                'mileage' => 12.0,
                'duration_minutes' => 50,
                'deep_link' => 'urbangoodz://driver/routes/offer/12',
            ]
        ];

        return [
            'status' => 'active',
            'recommendations' => $recommendations,
            'driver_metadata' => [
                'vehicle_type' => $vehicleType,
                'service_radius' => $radius,
            ],
        ];
    }

    // ─── VENDOR ASSISTANT API ────────────────────────────────────────────

    public function getVendorAssistantMetrics(int $vendorId): array
    {
        $vendor = Store::find($vendorId);
        if (!$vendor) {
            return ['status' => 'error', 'message' => 'Vendor store not found.'];
        }

        // Onboarding gaps, missing documents, low inventory, delayed orders
        $onboardingGaps = [];
        if (empty($vendor->logo)) {
            $onboardingGaps[] = 'Upload Store Logo';
        }
        if (empty($vendor->banner)) {
            $onboardingGaps[] = 'Upload Store Banner';
        }

        $lowInventoryAlerts = [];
        // Simulated low inventory check
        $lowStockCount = 0; // count of items with stock <= 5
        if ($lowStockCount > 0) {
            $lowInventoryAlerts[] = [
                'type' => 'low_stock',
                'message' => "{$lowStockCount} items are low on stock.",
                'recommended_action' => 'Update item inventories.',
            ];
        }

        return [
            'vendor_id' => $vendorId,
            'onboarding_gaps' => $onboardingGaps,
            'low_inventory_alerts' => $lowInventoryAlerts,
            'financial_metrics' => [
                'fees_percentage' => $vendor->comission ?? 15.0,
                'settlement_timing' => 'Weekly (Every Monday)',
                'payout_holds' => 0.00,
            ],
            'performance' => [
                'average_preparation_time_minutes' => 18,
                'order_fulfillment_rate' => 98.4,
            ]
        ];
    }

    // ─── BUSINESS ASSISTANT API ──────────────────────────────────────────

    public function getBusinessAssistantDetails(int $businessClientId): array
    {
        // Enforce strict tenant isolation by matching client id
        $activeManifests = DB::table('urban_goodz_manifests')
            ->where('business_client_id', $businessClientId)
            ->latest()
            ->take(5)
            ->get();

        $shortageRisk = DB::table('urban_goodz_dedicated_routes')
            ->where('business_client_id', $businessClientId)
            ->whereNull('assigned_driver_id')
            ->exists();

        return [
            'business_client_id' => $businessClientId,
            'delivery_workflow' => 'standard_parcel',
            'manifests_count' => count($activeManifests),
            'late_route_risk' => $shortageRisk,
            'driver_shortage' => $shortageRisk,
            'route_coverage' => $shortageRisk ? 'partial' : 'full',
            'invoices' => [
                'billing_period' => 'Monthly',
                'unpaid_balance' => 0.00,
            ]
        ];
    }
}
