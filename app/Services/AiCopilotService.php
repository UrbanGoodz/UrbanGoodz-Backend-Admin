<?php

namespace App\Services;

use App\Models\AiActionLog;
use App\Models\AiCopilotRecommendation;
use App\Models\AiCopilotSetting;
use App\Models\AiModuleAutomationSetting;
use App\Models\AiRiskRule;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzAgeVerification;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzRoutePackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiCopilotService
{
    const STUCK_THRESHOLD_HOURS = 4;

    private array $settings = [];

    public function __construct()
    {
        try {
            $this->settings = AiCopilotSetting::pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            $this->settings = [];
        }
    }

    private function getSetting(string $key, $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getMode(): string
    {
        return $this->getSetting('ai_ops_enabled', 'recommend_only');
    }

    public function getAllSettings(): array
    {
        return $this->settings;
    }

    public function isAutoEnabled(string $featureKey): bool
    {
        $mode = $this->getMode();
        if ($mode === 'off' || $mode === 'restricted_human_locked') return false;
        if ($mode === 'full_low_risk_automation') return true;
        if ($mode === 'supervised_automation') return (bool) $this->getSetting($featureKey, false);
        return false;
    }

    public function getModuleSetting(string $module): ?AiModuleAutomationSetting
    {
        return AiModuleAutomationSetting::where('module', $module)->first();
    }

    public function checkRiskRules(string $triggerType, mixed $value = null): ?array
    {
        $rules = AiRiskRule::enabled()->byTriggerType($triggerType)->get();

        $matched = null;
        $highestRisk = 0;
        $riskOrder = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        foreach ($rules as $rule) {
            if ($rule->trigger_value === null) {
                $matched = $rule;
                $highestRisk = $riskOrder[$rule->risk_level] ?? 0;
                continue;
            }

            $matchedValue = false;
            switch ($rule->trigger_operator) {
                case '>':
                    $matchedValue = (float) $value > (float) $rule->trigger_value;
                    break;
                case '<':
                    $matchedValue = (float) $value < (float) $rule->trigger_value;
                    break;
                case '>=':
                    $matchedValue = (float) $value >= (float) $rule->trigger_value;
                    break;
                case '<=':
                    $matchedValue = (float) $value <= (float) $rule->trigger_value;
                    break;
                case '=':
                case '==':
                    $matchedValue = (string) $value === (string) $rule->trigger_value;
                    break;
                default:
                    $matchedValue = true;
            }

            if ($matchedValue && ($riskOrder[$rule->risk_level] ?? 0) >= $highestRisk) {
                $matched = $rule;
                $highestRisk = $riskOrder[$rule->risk_level] ?? 0;
            }
        }

        if ($matched) {
            return [
                'rule' => $matched,
                'risk_level' => $matched->risk_level,
                'requires_approval' => $matched->requires_approval,
                'escalation_action' => $matched->escalation_action,
                'blocked' => in_array($matched->escalation_action, ['block_action', 'notify_admin']),
            ];
        }

        return null;
    }

    public function logAction(
        string $actionTaken,
        ?string $module = null,
        ?string $affectedUserType = null,
        ?int $affectedUserId = null,
        mixed $beforeValue = null,
        mixed $afterValue = null,
        ?string $reason = null,
        ?int $recommendationId = null,
        ?int $approvedBy = null,
        bool $rollbackAvailable = false
    ): AiActionLog {
        return AiActionLog::create([
            'action_taken' => $actionTaken,
            'module' => $module,
            'affected_user_type' => $affectedUserType,
            'affected_user_id' => $affectedUserId,
            'before_value' => $beforeValue ? (is_string($beforeValue) ? $beforeValue : json_encode($beforeValue)) : null,
            'after_value' => $afterValue ? (is_string($afterValue) ? $afterValue : json_encode($afterValue)) : null,
            'reason' => $reason,
            'automation_mode' => $this->getMode(),
            'recommendation_id' => $recommendationId,
            'approved_by' => $approvedBy,
            'rollback_available' => $rollbackAvailable,
        ]);
    }

    public function generateRecommendations(): array
    {
        $mode = $this->getMode();
        if ($mode === 'off') {
            return [];
        }

        $results = [];

        $results['dispatch'] = $this->suggestDispatch();
        $results['stuck_orders'] = $this->detectStuckOrders();
        $results['order_anywhere'] = $this->triageOrderAnywhere();
        $results['package_monitoring'] = $this->monitorPackages();
        $results['age_verification'] = $this->alertAgeVerification();
        $results['load_board'] = $this->monitorLoadBoard();
        $results['load_acceptance'] = $this->suggestLoadAcceptance();
        $results['load_pricing'] = $this->alertPricingAnomalies();

        return $results;
    }

    public function suggestDispatch(): array
    {
        $count = 0;

        $unassignedOrders = Order::whereNull('delivery_man_id')
            ->whereIn('order_status', ['pending', 'confirmed', 'processing'])
            ->where('order_type', '!=', 'pos')
            ->limit(10)
            ->get();

        $availableDrivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?? 1))
            ->get()
            ->keyBy('id');

        foreach ($unassignedOrders as $order) {
            $suggested = $this->findBestDriverForOrder($order, $availableDrivers);
            if ($suggested) {
                $rec = $this->createRecommendation(
                    'dispatch_suggestion',
                    'order',
                    $order,
                    $suggested['action'],
                    "Order #{$order->id} waiting for driver. Suggested: {$suggested['driver_name']} (zone match, {$suggested['current_orders']} current orders)",
                    $suggested['confidence'],
                    ['driver_id' => $suggested['driver_id'], 'driver_name' => $suggested['driver_name'], 'order_id' => $order->id]
                );
                $this->autoExecute($rec, 'ai_auto_dispatch_enabled', $suggested['confidence'], fn() => $this->autoDispatchOrder($order, $suggested), 'dispatch', 'dispatch_suggestion', $order->order_amount ?? 0);
                $count++;
            }
        }

        $unassignedRoutes = UrbanGoodzDedicatedRoute::whereNull('assigned_driver_id')
            ->whereIn('status', ['pending', 'active'])
            ->limit(10)
            ->get();

        foreach ($unassignedRoutes as $route) {
            $suggested = $this->findBestDriverForRoute($route, $availableDrivers);
            if ($suggested) {
                $rec = $this->createRecommendation(
                    'dispatch_suggestion',
                    'route',
                    $route,
                    $suggested['action'],
                    "Route '{$route->route_name}' ({$route->total_packages} packages) needs driver. Suggested: {$suggested['driver_name']}",
                    $suggested['confidence'],
                    ['driver_id' => $suggested['driver_id'], 'driver_name' => $suggested['driver_name'], 'route_id' => $route->id]
                );
                $this->autoExecute($rec, 'ai_auto_dispatch_enabled', $suggested['confidence'], fn() => $this->autoDispatchRoute($route, $suggested), 'dispatch', 'dispatch_suggestion', $route->total_packages ?? 0);
                $count++;
            }
        }

        return ['type' => 'dispatch_suggestion', 'count' => $count, 'label' => 'Dispatch Suggestions'];
    }

    public function detectStuckOrders(): array
    {
        $count = 0;
        $threshold = now()->subHours(self::STUCK_THRESHOLD_HOURS);

        $waitingOrders = Order::whereNull('delivery_man_id')
            ->whereIn('order_status', ['pending', 'confirmed'])
            ->where('created_at', '<', $threshold)
            ->where('order_type', '!=', 'pos')
            ->limit(10)
            ->get();

        foreach ($waitingOrders as $order) {
            $rec = $this->createRecommendation(
                'stuck_order',
                'unassigned',
                $order,
                'Assign driver or cancel',
                "Order #{$order->id} was created {$order->created_at->diffForHumans()} with no driver assigned",
                0.7,
                ['hours_waiting' => $order->created_at->diffInHours(now()), 'order_status' => $order->order_status]
            );
            $this->autoExecute($rec, 'ai_auto_dispatch_enabled', 0.7, function() use ($order) {
                $availableDrivers = DeliveryMan::where('active', 1)
                    ->where('application_status', 'approved')
                    ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?? 1))
                    ->get()
                    ->keyBy('id');
                $suggested = $this->findBestDriverForOrder($order, $availableDrivers);
                if ($suggested) {
                    return $this->autoDispatchOrder($order, $suggested);
                }
                return false;
            }, 'dispatch', 'dispatch_suggestion', $order->order_amount ?? 0);
            $count++;
        }

        $blockedPackages = UrbanGoodzRoutePackage::whereIn('status', ['failed', 'admin_review'])
            ->where('updated_at', '<', $threshold)
            ->limit(10)
            ->get();

        foreach ($blockedPackages as $pkg) {
            $this->createRecommendation(
                'stuck_order',
                'blocked_delivery',
                $pkg,
                'Review package status and resolve',
                "Package {$pkg->tracking_id} stuck in '{$pkg->status}' for {$pkg->updated_at->diffForHumans()}",
                0.8,
                ['package_id' => $pkg->id, 'current_status' => $pkg->status, 'tracking_id' => $pkg->tracking_id]
            );
            $count++;
        }

        $unassignedJobs = UrbanGoodzBusinessClientJob::whereNull('assigned_delivery_man_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->where('created_at', '<', $threshold)
            ->limit(5)
            ->get();

        foreach ($unassignedJobs as $job) {
            $this->createRecommendation(
                'stuck_order',
                'unassigned_job',
                $job,
                'Assign driver or contact client',
                "Job #{$job->job_number} for {$job->pickup_name} has no driver for {$job->created_at->diffForHumans()}",
                0.65,
                ['job_id' => $job->id, 'client_id' => $job->business_client_id]
            );
            $count++;
        }

        return ['type' => 'stuck_order', 'count' => $count, 'label' => 'Stuck Orders'];
    }

    public function triageOrderAnywhere(): array
    {
        $count = 0;

        $pendingRequests = OrderAnywhereRequest::whereIn('status', ['pending', 'pending_review', 'quote_needed'])
            ->where('created_at', '<', now()->subHours(2))
            ->limit(10)
            ->get();

        foreach ($pendingRequests as $req) {
            $action = 'Review and respond';
            $reason = "Request #{$req->id} ({$req->status}) from {$req->created_at->diffForHumans()}";

            if ($req->status === 'quote_needed') {
                $action = 'Provide quote to customer';
                $reason = "Request #{$req->id} needs pricing quote, waiting {$req->created_at->diffForHumans()}";
            } elseif (empty($req->description) && $req->status === 'pending_review') {
                $action = 'Contact customer for details';
                $reason = "Request #{$req->id} has unclear or missing description";
            }

            $this->createRecommendation(
                'order_anywhere_triage',
                $req->status,
                $req,
                $action,
                $reason,
                0.75,
                ['request_id' => $req->id, 'status' => $req->status, 'customer_name' => $req->customer_name ?? null]
            );
            $count++;
        }

        return ['type' => 'order_anywhere_triage', 'count' => $count, 'label' => 'Order Anywhere Triage'];
    }

    public function monitorPackages(): array
    {
        $count = 0;

        $poolPackages = UrbanGoodzRoutePackage::whereNull('dedicated_route_id')
            ->whereNull('manifest_id')
            ->where('status', 'pending_review')
            ->where('created_at', '<', now()->subDay())
            ->limit(10)
            ->get();

        foreach ($poolPackages as $pkg) {
            $this->createRecommendation(
                'package_monitoring',
                'unassigned',
                $pkg,
                'Assign to a route or manifest',
                "Package {$pkg->tracking_id} unassigned for {$pkg->created_at->diffForHumans()}",
                0.7,
                ['package_id' => $pkg->id, 'tracking_id' => $pkg->tracking_id, 'days_unassigned' => $pkg->created_at->diffInDays(now())]
            );
            $count++;
        }

        $exceptionPackages = UrbanGoodzRoutePackage::whereIn('status', ['unable_to_deliver', 'return_required', 'exception'])
            ->where('updated_at', '<', now()->subHours(6))
            ->limit(10)
            ->get();

        foreach ($exceptionPackages as $pkg) {
            $this->createRecommendation(
                'package_monitoring',
                'exception',
                $pkg,
                'Review exception and determine next action',
                "Package {$pkg->tracking_id} has exception status '{$pkg->status}' for {$pkg->updated_at->diffForHumans()}",
                0.85,
                ['package_id' => $pkg->id, 'tracking_id' => $pkg->tracking_id, 'exception_status' => $pkg->status]
            );
            $count++;
        }

        $pendingUnscaned = UrbanGoodzRoutePackage::whereNull('dropoff_scanned_at')
            ->where('status', 'in_transit')
            ->where('updated_at', '<', now()->subHours(8))
            ->limit(5)
            ->get();

        foreach ($pendingUnscaned as $pkg) {
            $this->createRecommendation(
                'package_monitoring',
                'in_transit_stalled',
                $pkg,
                'Contact driver for status update',
                "Package {$pkg->tracking_id} has been in transit for {$pkg->updated_at->diffForHumans()} without dropoff scan",
                0.6,
                ['package_id' => $pkg->id, 'tracking_id' => $pkg->tracking_id, 'driver_id' => $pkg->dropoff_scanned_by]
            );
            $count++;
        }

        return ['type' => 'package_monitoring', 'count' => $count, 'label' => 'Package Monitoring'];
    }

    public function alertAgeVerification(): array
    {
        $count = 0;

        $pendingVerifications = UrbanGoodzAgeVerification::where('verification_status', 'pending')
            ->where('verification_attempted_at', '<', now()->subHours(2))
            ->limit(10)
            ->get();

        foreach ($pendingVerifications as $v) {
            $this->createRecommendation(
                'age_verification_alert',
                'pending_verification',
                $v,
                'Follow up with driver or resolve manually',
                "Age verification #{$v->id} for package #{$v->package_id} pending for {$v->verification_attempted_at->diffForHumans()}",
                0.7,
                ['verification_id' => $v->id, 'package_id' => $v->package_id, 'driver_id' => $v->driver_id]
            );
            $count++;
        }

        $needsAdminReview = UrbanGoodzAgeVerification::needsAdminReview()
            ->where('created_at', '<', now()->subHours(4))
            ->limit(10)
            ->get();

        foreach ($needsAdminReview as $v) {
            $this->createRecommendation(
                'age_verification_alert',
                'admin_review_needed',
                $v,
                'Review refused verification and approve or escalate',
                "Age verification #{$v->id} refused ({$v->refusal_reason}) needs admin review for {$v->created_at->diffForHumans()}",
                0.9,
                ['verification_id' => $v->id, 'package_id' => $v->package_id, 'refusal_reason' => $v->refusal_reason]
            );
            $count++;
        }

        return ['type' => 'age_verification_alert', 'count' => $count, 'label' => 'Age Verification Alerts'];
    }

    // =========================================================================
    // Load Board AI Integration
    // =========================================================================

    public function monitorLoadBoard(): array
    {
        $count = 0;

        $availableLoads = UrbanGoodzLoadBoardLoad::available()->get();

        if ($availableLoads->isEmpty()) {
            return ['type' => 'load_board', 'count' => 0, 'label' => 'Load Board Monitor'];
        }

        $staleLoads = $availableLoads->filter(function ($load) {
            return $load->created_at->isBefore(now()->subDays(3));
        });

        foreach ($staleLoads as $load) {
            $this->createRecommendation(
                'load_board_stale',
                'aging_load',
                $load,
                'Consider repricing or removing this load',
                "Load {$load->load_number} ({$load->origin_city}, {$load->origin_state} → {$load->destination_city}, {$load->destination_state}) has been available for {$load->created_at->diffForHumans()}",
                0.65,
                [
                    'load_id' => $load->id,
                    'load_number' => $load->load_number,
                    'days_available' => $load->created_at->diffInDays(now()),
                    'payout_amount' => $load->payout_amount,
                    'rate_per_mile' => $load->rate_per_mile,
                    'origin_state' => $load->origin_state,
                    'destination_state' => $load->destination_state,
                ]
            );
            $count++;
        }

        $laneStats = $availableLoads->groupBy(fn($l) => ($l->origin_state ?? '?') . '→' . ($l->destination_state ?? '?'))
            ->map(fn($loads) => [
                'count' => $loads->count(),
                'avg_payout' => $loads->avg('payout_amount'),
                'avg_rate_per_mile' => $loads->avg('rate_per_mile'),
            ])
            ->filter(fn($stats) => $stats['count'] >= 3);

        foreach ($laneStats as $lane => $stats) {
            if ($stats['avg_rate_per_mile'] < 2.50) {
                $this->createRecommendation(
                    'load_board_lane',
                    'low_rate_lane',
                    null,
                    "Lane {$lane} averaging \${$stats['avg_rate_per_mile']}/mi — below $2.50 threshold",
                    "{$stats['count']} loads on {$lane} with avg rate/mile of \${$stats['avg_rate_per_mile']}. Consider increasing pay or reducing volume.",
                    0.7,
                    [
                        'lane' => $lane,
                        'load_count' => $stats['count'],
                        'avg_payout' => round($stats['avg_payout'], 2),
                        'avg_rate_per_mile' => round($stats['avg_rate_per_mile'], 2),
                    ]
                );
                $count++;
            }
        }

        $thisWeek = UrbanGoodzLoadBoardLoad::where('created_at', '>=', now()->startOfWeek())->count();
        $lastWeek = UrbanGoodzLoadBoardLoad::where('created_at', '>=', now()->subWeek()->startOfWeek())
            ->where('created_at', '<', now()->startOfWeek())->count();

        if ($lastWeek > 0) {
            $pctChange = (($thisWeek - $lastWeek) / $lastWeek) * 100;

            if ($pctChange < -30) {
                $this->createRecommendation(
                    'load_board_demand',
                    'volume_drop',
                    null,
                    "Load volume dropped " . number_format(abs($pctChange), 0) . "% week-over-week ({$thisWeek} this week vs {$lastWeek} last week)",
                    "Incoming load volume has dropped significantly. This may indicate a provider sync issue, seasonal slowdown, or pricing problem. Check DAT/Truckstop sync status.",
                    0.75,
                    [
                        'this_week_count' => $thisWeek,
                        'last_week_count' => $lastWeek,
                        'pct_change' => round($pctChange, 1),
                        'trend' => 'declining',
                    ]
                );
                $count++;
            } elseif ($pctChange > 50) {
                $this->createRecommendation(
                    'load_board_demand',
                    'volume_surge',
                    null,
                    "Load volume up " . number_format($pctChange, 0) . "% week-over-week ({$thisWeek} this week vs {$lastWeek} last week)",
                    "Incoming load volume has surged. Ensure enough drivers are available and consider temporary rate adjustments to maximize throughput.",
                    0.7,
                    [
                        'this_week_count' => $thisWeek,
                        'last_week_count' => $lastWeek,
                        'pct_change' => round($pctChange, 1),
                        'trend' => 'surging',
                    ]
                );
                $count++;
            }
        }

        $thisMonthByState = UrbanGoodzLoadBoardLoad::where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('origin_state, COUNT(*) as count, SUM(payout_amount) as total_payout, AVG(rate_per_mile) as avg_rate')
            ->groupBy('origin_state')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($thisMonthByState->count() >= 3) {
            $topLane = $thisMonthByState->first();
            $secondLane = $thisMonthByState->skip(1)->first();
            if ($topLane && $secondLane && $topLane->count > $secondLane->count * 2) {
                $this->createRecommendation(
                    'load_board_demand',
                    'concentrated_demand',
                    null,
                    "Demand concentrated in {$topLane->origin_state} — {$topLane->count} loads (2x+ more than {$secondLane->origin_state} with {$secondLane->count})",
                    "{$topLane->origin_state} dominates this month's load volume with avg rate \${$topLane->avg_rate}/mi. Consider diversifying origin states or targeting backhaul from {$secondLane->origin_state}.",
                    0.6,
                    [
                        'top_state' => $topLane->origin_state,
                        'top_count' => $topLane->count,
                        'second_state' => $secondLane->origin_state,
                        'second_count' => $secondLane->count,
                        'top_avg_rate' => round($topLane->avg_rate ?? 0, 2),
                    ]
                );
                $count++;
            }
        }

        return ['type' => 'load_board', 'count' => $count, 'label' => 'Load Board Monitor'];
    }

    public function suggestLoadAcceptance(): array
    {
        $count = 0;

        $availableLoads = UrbanGoodzLoadBoardLoad::available()
            ->where('payout_amount', '>', 0)
            ->orderBy('rate_per_mile', 'desc')
            ->limit(20)
            ->get();

        if ($availableLoads->isEmpty()) {
            return ['type' => 'load_board_accept', 'count' => 0, 'label' => 'Load Acceptance Suggestions'];
        }

        $availableDrivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?? 1))
            ->get();

        if ($availableDrivers->isEmpty()) {
            return ['type' => 'load_board_accept', 'count' => 0, 'label' => 'Load Acceptance Suggestions'];
        }

        foreach ($availableLoads as $load) {
            $bestDriver = $this->findBestDriverForLoad($load, $availableDrivers);
            if (!$bestDriver) {
                continue;
            }

            $confidence = $bestDriver['confidence'];
            $action = "Assign {$bestDriver['driver_name']} to Load {$load->load_number} (\${$load->payout_amount})";

            $rec = $this->createRecommendation(
                'load_board_accept',
                'driver_match',
                $load,
                $action,
                "Load {$load->load_number} ({$load->origin_city}, {$load->origin_state} → {$load->destination_city}, {$load->destination_state}, {$load->equipment_type}) paying \${$load->payout_amount} (\${$load->rate_per_mile}/mi) matched to {$bestDriver['driver_name']} ({$bestDriver['reason']})",
                $confidence,
                [
                    'load_id' => $load->id,
                    'load_number' => $load->load_number,
                    'driver_id' => $bestDriver['driver_id'],
                    'driver_name' => $bestDriver['driver_name'],
                    'payout_amount' => $load->payout_amount,
                    'rate_per_mile' => $load->rate_per_mile,
                    'equipment_type' => $load->equipment_type,
                    'distance_miles' => $load->distance_miles,
                    'origin_state' => $load->origin_state,
                    'destination_state' => $load->destination_state,
                ]
            );

            $this->autoExecute($rec, 'ai_auto_load_board_enabled', $confidence, function () use ($load, $bestDriver) {
                return $this->autoAcceptLoad($load, $bestDriver['driver_id']);
            }, 'load_board', 'load_acceptance', $load->payout_amount ?? 0);

            $count++;
        }

        return ['type' => 'load_board_accept', 'count' => $count, 'label' => 'Load Acceptance Suggestions'];
    }

    public function alertPricingAnomalies(): array
    {
        $count = 0;

        $recentLoads = UrbanGoodzLoadBoardLoad::where('created_at', '>=', now()->subDays(7))
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($recentLoads->count() < 5) {
            return ['type' => 'load_board_repricing', 'count' => 0, 'label' => 'Load Pricing Alerts'];
        }

        $lanePricing = $recentLoads->groupBy(fn($l) => ($l->origin_state ?? '?') . '→' . ($l->destination_state ?? '?'))
            ->map(fn($loads) => [
                'avg_rate' => $loads->avg('rate_per_mile'),
                'std_dev' => $this->standardDeviation($loads->pluck('rate_per_mile')->filter()->values()->toArray()),
                'count' => $loads->count(),
            ])
            ->filter(fn($s) => $s['count'] >= 3);

        $currentAvailable = UrbanGoodzLoadBoardLoad::available()->get();

        foreach ($currentAvailable as $load) {
            $lane = ($load->origin_state ?? '?') . '→' . ($load->destination_state ?? '?');
            if (!isset($lanePricing[$lane]) || !$load->rate_per_mile) {
                continue;
            }

            $stats = $lanePricing[$lane];
            $zScore = $stats['std_dev'] > 0
                ? ($load->rate_per_mile - $stats['avg_rate']) / $stats['std_dev']
                : 0;

            if ($zScore < -1.5) {
                $rec = $this->createRecommendation(
                    'load_board_repricing',
                    'underpriced',
                    $load,
                    "Reprice Load {$load->load_number} — rate \${$load->rate_per_mile}/mi is well below lane avg \${$stats['avg_rate']}/mi",
                    "Load {$load->load_number} (\${$load->rate_per_mile}/mi) is {$this->formatPercent($stats['avg_rate'], $load->rate_per_mile)} below the {$lane} 7-day average (\${$stats['avg_rate']}/mi). Raising the rate may attract more drivers.",
                    min(0.95, abs($zScore) / 3),
                    [
                        'load_id' => $load->id,
                        'load_number' => $load->load_number,
                        'current_rate' => $load->rate_per_mile,
                        'lane_avg_rate' => round($stats['avg_rate'], 2),
                        'z_score' => round($zScore, 2),
                        'suggested_rate' => round($stats['avg_rate'] * 0.95, 2),
                        'lane' => $lane,
                    ]
                );
                $count++;
            } elseif ($zScore > 2.0) {
                $rec = $this->createRecommendation(
                    'load_board_repricing',
                    'overpriced',
                    $load,
                    "Load {$load->load_number} — rate \${$load->rate_per_mile}/mi is significantly above lane avg \${$stats['avg_rate']}/mi",
                    "Load {$load->load_number} (\${$load->rate_per_mile}/mi) is {$this->formatPercent($load->rate_per_mile, $stats['avg_rate'])} above the {$lane} 7-day average (\${$stats['avg_rate']}/mi). Consider if this rate is sustainable.",
                    0.75,
                    [
                        'load_id' => $load->id,
                        'load_number' => $load->load_number,
                        'current_rate' => $load->rate_per_mile,
                        'lane_avg_rate' => round($stats['avg_rate'], 2),
                        'z_score' => round($zScore, 2),
                        'lane' => $lane,
                    ]
                );
                $count++;
            }
        }

        return ['type' => 'load_board_repricing', 'count' => $count, 'label' => 'Load Pricing Alerts'];
    }

    private function findBestDriverForLoad(UrbanGoodzLoadBoardLoad $load, $availableDrivers): ?array
    {
        if ($availableDrivers->isEmpty()) {
            return null;
        }

        $equipmentMap = [
            'van' => ['van', 'box'],
            'reefer' => ['reefer', 'refrigerated'],
            'flatbed' => ['flatbed', 'flat'],
            'step_deck' => ['step_deck', 'stepdeck', 'lowboy'],
            'tanker' => ['tanker', 'tank'],
            'car_hauler' => ['car_hauler', 'auto'],
            'container' => ['container', 'chassis'],
        ];
        $compatibleEquipment = $equipmentMap[$load->equipment_type] ?? [$load->equipment_type];

        $candidates = $availableDrivers->filter(function ($driver) use ($compatibleEquipment, $load) {
            $driverEquipment = strtolower($driver->vehicle_type ?? 'van');
            $equipmentMatch = in_array($driverEquipment, $compatibleEquipment);

            $trainingOk = !$load->is_hazmat || !empty($driver->has_hazmat);

            return $equipmentMatch && $trainingOk;
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        $best = $candidates->sortBy('current_orders')->first();

        $confidence = 0.65;
        if ($best->vehicle_type === $load->equipment_type) $confidence += 0.15;
        if ($load->payout_amount > 500) $confidence += 0.05;
        if ($load->rate_per_mile > 3.50) $confidence += 0.05;
        if ($load->is_expedited) $confidence += 0.05;
        if ($load->is_hazmat && !empty($best->has_hazmat)) $confidence += 0.05;

        $confidence = min(0.95, $confidence);

        return [
            'driver_id' => $best->id,
            'driver_name' => trim(($best->f_name ?? '') . ' ' . ($best->l_name ?? '')),
            'current_orders' => $best->current_orders,
            'confidence' => $confidence,
            'reason' => "equipment match ({$best->vehicle_type}), {$best->current_orders} current orders",
        ];
    }

    private function autoAcceptLoad(UrbanGoodzLoadBoardLoad $load, int $driverId): bool
    {
        $service = app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class);
        $adminId = auth('admin')->id();
        $result = $service->acceptLoad($load->id, $driverId, $adminId);
        return $result !== null;
    }

    private function executeLoadBoardAcceptAction(AiCopilotRecommendation $rec, array $meta): bool
    {
        $loadId = $meta['load_id'] ?? null;
        $driverId = $meta['driver_id'] ?? null;

        if (!$loadId || !$driverId) {
            return false;
        }

        $load = UrbanGoodzLoadBoardLoad::find($loadId);
        if (!$load || $load->status !== 'available') {
            return false;
        }

        $beforeSnapshot = json_encode([
            'status' => $load->status,
            'assigned_driver_id' => $load->assigned_driver_id,
        ]);

        $service = app(\App\Services\UrbanGoodz\UrbanGoodzLoadBoardService::class);
        $result = $service->acceptLoad($loadId, $driverId, auth('admin')->id());

        if ($result) {
            $afterSnapshot = json_encode([
                'status' => $result->status,
                'assigned_driver_id' => $result->assigned_driver_id,
            ]);

            $this->logAction(
                actionTaken: $rec->suggested_action,
                module: 'load_board',
                affectedUserType: 'App\Models\DeliveryMan',
                affectedUserId: $driverId,
                beforeValue: $beforeSnapshot,
                afterValue: $afterSnapshot,
                reason: 'Admin accepted: ' . $rec->reason,
                recommendationId: $rec->id,
                approvedBy: auth('admin')->id(),
                rollbackAvailable: true,
            );

            return true;
        }

        return false;
    }

    private function executeLoadBoardRepricingAction(AiCopilotRecommendation $rec, array $meta): bool
    {
        $loadId = $meta['load_id'] ?? null;
        $suggestedRate = $meta['suggested_rate'] ?? null;

        if (!$loadId || !$suggestedRate) {
            return false;
        }

        $load = UrbanGoodzLoadBoardLoad::find($loadId);
        if (!$load || $load->status !== 'available') {
            return false;
        }

        $beforeSnapshot = json_encode([
            'rate_per_mile' => $load->rate_per_mile,
            'payout_amount' => $load->payout_amount,
        ]);

        $newPayout = round($suggestedRate * ($load->distance_miles ?? 1), 2);
        $load->update([
            'rate_per_mile' => $suggestedRate,
            'payout_amount' => $newPayout,
        ]);

        $afterSnapshot = json_encode([
            'rate_per_mile' => $suggestedRate,
            'payout_amount' => $newPayout,
        ]);

        $this->logAction(
            actionTaken: $rec->suggested_action,
            module: 'load_board',
            affectedUserType: 'App\Models\UrbanGoodzLoadBoardLoad',
            affectedUserId: $loadId,
            beforeValue: $beforeSnapshot,
            afterValue: $afterSnapshot,
            reason: 'Admin accepted: ' . $rec->reason,
            recommendationId: $rec->id,
            approvedBy: auth('admin')->id(),
            rollbackAvailable: true,
        );

        return true;
    }

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0;
        $mean = array_sum($values) / $n;
        $sumSquaredDiff = 0;
        foreach ($values as $v) {
            $sumSquaredDiff += ($v - $mean) ** 2;
        }
        return sqrt($sumSquaredDiff / ($n - 1));
    }

    private function formatPercent(float $baseline, float $value): string
    {
        if ($baseline == 0) return '0%';
        $pct = abs(($value - $baseline) / $baseline) * 100;
        return number_format($pct, 0) . '%';
    }

    public function accept(int $id, int $adminId, ?string $notes = null): ?AiCopilotRecommendation
    {
        $rec = AiCopilotRecommendation::find($id);
        if (!$rec || $rec->status !== 'pending') {
            return null;
        }

        $executed = $this->executeRecommendationAction($rec);

        $rec->status = 'accepted';
        $rec->reviewed_by = $adminId;
        $rec->reviewed_at = now();
        $rec->admin_notes = $notes;

        $meta = $rec->metadata ?? [];
        $meta['admin_executed'] = $executed;
        $meta['admin_executed_at'] = now()->toDateTimeString();
        $rec->metadata = $meta;

        $rec->save();

        return $rec;
    }

    public function rollback(int $logId, int $adminId, ?string $notes = null): ?AiActionLog
    {
        $log = AiActionLog::find($logId);
        if (!$log || !$log->rollback_available) {
            return null;
        }

        $before = json_decode($log->before_value, true);
        if (!$before) {
            return null;
        }

        $success = false;

        if ($log->module === 'dispatch' && isset($before['delivery_man_id'])) {
            $success = $this->rollbackDispatch($log, $before);
        }

        if ($success) {
            $log->rollback_available = false;
            $log->save();

            if ($log->recommendation_id) {
                $rec = AiCopilotRecommendation::find($log->recommendation_id);
                if ($rec) {
                    $rec->status = 'dismissed';
                    $rec->admin_notes = ($rec->admin_notes ? $rec->admin_notes . ' | ' : '') . 'Rolled back by admin';
                    $rec->save();
                }
            }
        }

        return $success ? $log : null;
    }

    private function executeRecommendationAction(AiCopilotRecommendation $rec): bool
    {
        $meta = $rec->metadata ?? [];

        try {
            switch ($rec->recommendation_type) {
                case 'dispatch_suggestion':
                    return $this->executeDispatchAction($rec, $meta);
                case 'stuck_order':
                    return $this->executeStuckOrderAction($rec, $meta);
                case 'order_anywhere_triage':
                    return $this->executeOrderAnywhereAction($rec, $meta);
                case 'load_board_accept':
                    return $this->executeLoadBoardAcceptAction($rec, $meta);
                case 'load_board_repricing':
                    return $this->executeLoadBoardRepricingAction($rec, $meta);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            $meta['execute_error'] = $e->getMessage();
            $rec->metadata = $meta;
            $rec->save();
            return false;
        }
    }

    private function executeDispatchAction(AiCopilotRecommendation $rec, array $meta): bool
    {
        $driverId = $meta['driver_id'] ?? null;
        if (!$driverId) {
            return false;
        }

        $driver = DeliveryMan::where('id', $driverId)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->first();
        if (!$driver) {
            return false;
        }

        $driverInfo = [
            'driver_id' => $driver->id,
            'driver_name' => trim(($driver->f_name ?? '') . ' ' . ($driver->l_name ?? '')),
        ];

        $success = false;

        if ($rec->order_id) {
            $order = Order::find($rec->order_id);
            if ($order && !$order->delivery_man_id) {
                $beforeSnapshot = json_encode([
                    'delivery_man_id' => $order->delivery_man_id,
                    'order_status' => $order->order_status,
                ]);

                $success = $this->autoDispatchOrder($order, $driverInfo);

                if ($success) {
                    $afterSnapshot = json_encode([
                        'delivery_man_id' => $order->fresh()->delivery_man_id,
                        'order_status' => $order->fresh()->order_status,
                    ]);
                    $this->logAction(
                        actionTaken: $rec->suggested_action,
                        module: 'dispatch',
                        affectedUserType: 'App\Models\DeliveryMan',
                        affectedUserId: $driverId,
                        beforeValue: $beforeSnapshot,
                        afterValue: $afterSnapshot,
                        reason: 'Admin accepted: ' . $rec->reason,
                        recommendationId: $rec->id,
                        approvedBy: auth('admin')->id(),
                        rollbackAvailable: true,
                    );
                }
            }
        } elseif ($rec->route_id) {
            $route = UrbanGoodzDedicatedRoute::find($rec->route_id);
            if ($route && !$route->assigned_driver_id) {
                $success = $this->autoDispatchRoute($route, $driverInfo);

                if ($success) {
                    $this->logAction(
                        actionTaken: $rec->suggested_action,
                        module: 'dispatch',
                        affectedUserType: 'App\Models\DeliveryMan',
                        affectedUserId: $driverId,
                        beforeValue: json_encode(['assigned_driver_id' => null]),
                        afterValue: json_encode(['assigned_driver_id' => $driverId]),
                        reason: 'Admin accepted: ' . $rec->reason,
                        recommendationId: $rec->id,
                        approvedBy: auth('admin')->id(),
                        rollbackAvailable: true,
                    );
                }
            }
        }

        return $success;
    }

    private function executeStuckOrderAction(AiCopilotRecommendation $rec, array $meta): bool
    {
        if ($rec->order_id) {
            $order = Order::find($rec->order_id);
            if ($order && !$order->delivery_man_id) {
                $availableDrivers = DeliveryMan::where('active', 1)
                    ->where('application_status', 'approved')
                    ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?? 1))
                    ->get()
                    ->keyBy('id');
                $suggested = $this->findBestDriverForOrder($order, $availableDrivers);
                if ($suggested) {
                    $meta['driver_id'] = $suggested['driver_id'];
                    $meta['driver_name'] = $suggested['driver_name'];
                    $rec->metadata = $meta;
                    $rec->save();
                    return $this->executeDispatchAction($rec, $meta);
                }
            }
        }
        return false;
    }

    private function executeOrderAnywhereAction(AiCopilotRecommendation $rec, array $meta): bool
    {
        $requestId = $meta['request_id'] ?? $rec->request_id;
        if (!$requestId) {
            return false;
        }

        $req = OrderAnywhereRequest::find($requestId);
        if (!$req || $req->status === 'completed') {
            return false;
        }

        $newStatus = match ($meta['status'] ?? $req->status) {
            'pending' => 'pending_review',
            'pending_review' => 'in_progress',
            'quote_needed' => 'pending_review',
            default => 'in_progress',
        };

        $before = json_encode(['status' => $req->status]);
        $req->status = $newStatus;
        $req->save();
        $after = json_encode(['status' => $newStatus]);

        $this->logAction(
            actionTaken: $rec->suggested_action,
            module: 'order_anywhere',
            affectedUserType: 'App\Models\OrderAnywhereRequest',
            affectedUserId: $requestId,
            beforeValue: $before,
            afterValue: $after,
            reason: 'Admin accepted: ' . $rec->reason,
            recommendationId: $rec->id,
            approvedBy: auth('admin')->id(),
            rollbackAvailable: true,
        );

        return true;
    }

    private function rollbackDispatch(AiActionLog $log, array $before): bool
    {
        try {
            DB::beginTransaction();

            $meta = $log->recommendation?->metadata ?? [];
            $driverId = $meta['driver_id'] ?? null;
            $orderId = $log->recommendation?->order_id;

            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->delivery_man_id = $before['delivery_man_id'] ?? null;
                    if (isset($before['order_status'])) {
                        $order->order_status = $before['order_status'];
                    }
                    $order->save();

                    if ($driverId) {
                        $driver = DeliveryMan::find($driverId);
                        if ($driver) {
                            $driver->decrement('current_orders');
                            $driver->decrement('assigned_order_count');
                        }
                    }
                }
            } elseif ($log->module === 'dispatch' && isset($before['assigned_driver_id'])) {
                $routeId = $log->recommendation?->route_id;
                if ($routeId) {
                    $route = UrbanGoodzDedicatedRoute::find($routeId);
                    if ($route) {
                        $route->assigned_driver_id = $before['assigned_driver_id'];
                        if (isset($before['status'])) {
                            $route->status = $before['status'];
                        }
                        $route->save();
                    }
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function dismiss(int $id, int $adminId, ?string $notes = null): ?AiCopilotRecommendation
    {
        $rec = AiCopilotRecommendation::find($id);
        if (!$rec || $rec->status !== 'pending') {
            return null;
        }

        $rec->status = 'dismissed';
        $rec->reviewed_by = $adminId;
        $rec->reviewed_at = now();
        $rec->admin_notes = $notes;
        $rec->save();

        return $rec;
    }

    private function isLowRiskOrder(Order $order): bool
    {
        if ($order->age_restricted_order) return false;
        if ($order->callback !== 'default') return false;
        return true;
    }

    private function isLowRiskRoute(UrbanGoodzDedicatedRoute $route): bool
    {
        if ($route->contains_age_restricted_items) return false;
        return true;
    }

    private function autoDispatchOrder(Order $order, array $driverInfo): bool
    {
        if (!$this->isLowRiskOrder($order)) {
            return false;
        }

        try {
            DB::beginTransaction();

            $order->delivery_man_id = $driverInfo['driver_id'];

            if (in_array($order->order_status, ['pending', 'confirmed'])) {
                $order->order_status = 'accepted';
            }
            $order->save();

            $driver = DeliveryMan::find($driverInfo['driver_id']);
            if ($driver) {
                $driver->increment('current_orders');
                $driver->increment('assigned_order_count');
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    private function autoDispatchRoute(UrbanGoodzDedicatedRoute $route, array $driverInfo): bool
    {
        if (!$this->isLowRiskRoute($route)) {
            return false;
        }

        try {
            DB::beginTransaction();

            $route->assigned_driver_id = $driverInfo['driver_id'];

            if ($route->status === 'pending') {
                $route->status = 'active';
            }
            $route->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    private function autoExecute(AiCopilotRecommendation $rec, string $autoFeatureKey, float $confidence, callable $fn, ?string $module = null, ?string $triggerType = null, mixed $triggerValue = null): void
    {
        $mode = $this->getMode();

        if ($mode === 'recommend_only' || $mode === 'restricted_human_locked') {
            $meta = $rec->metadata ?? [];
            $meta['auto_execute_skipped'] = true;
            $meta['auto_execute_skip_reason'] = "Mode '{$mode}' does not permit auto-execution";
            $rec->metadata = $meta;
            $rec->save();
            return;
        }

        $canAutoExecute = false;
        if ($mode === 'full_low_risk_automation') {
            $canAutoExecute = true;
        } elseif ($mode === 'supervised_automation' && $this->isAutoEnabled($autoFeatureKey) && $confidence >= 0.7) {
            $canAutoExecute = true;
        }

        if (!$canAutoExecute) {
            return;
        }

        if ($module) {
            $moduleSetting = $this->getModuleSetting($module);
            if (!$moduleSetting || !$moduleSetting->enabled) {
                $meta = $rec->metadata ?? [];
                $meta['auto_execute_skipped'] = true;
                $meta['auto_execute_skip_reason'] = "Module '{$module}' automation disabled";
                $rec->metadata = $meta;
                $rec->save();
                return;
            }

            if ($confidence < $moduleSetting->min_confidence_score) {
                $meta = $rec->metadata ?? [];
                $meta['auto_execute_skipped'] = true;
                $meta['auto_execute_skip_reason'] = "Confidence {$confidence} below module threshold {$moduleSetting->min_confidence_score}";
                $rec->metadata = $meta;
                $rec->save();
                return;
            }

            if ($moduleSetting->max_auto_action_amount !== null && (float) $triggerValue > (float) $moduleSetting->max_auto_action_amount) {
                $meta = $rec->metadata ?? [];
                $meta['auto_execute_skipped'] = true;
                $meta['auto_execute_skip_reason'] = "Amount {$triggerValue} exceeds module max {$moduleSetting->max_auto_action_amount}";
                $rec->metadata = $meta;
                $rec->save();
                return;
            }
        }

        if ($triggerType) {
            $riskCheck = $this->checkRiskRules($triggerType, $triggerValue);
            if ($riskCheck && $riskCheck['blocked']) {
                $meta = $rec->metadata ?? [];
                $meta['auto_execute_skipped'] = true;
                $meta['auto_execute_skip_reason'] = "Risk rule '{$riskCheck['rule']->rule_name}' blocked action";
                $meta['risk_rule_id'] = $riskCheck['rule']->id;
                $meta['risk_level'] = $riskCheck['risk_level'];
                $rec->metadata = $meta;
                $rec->save();

                if ($riskCheck['escalation_action'] === 'notify_admin') {
                    $this->logAction(
                        actionTaken: $riskCheck['rule']->rule_name,
                        module: $module,
                        reason: "Risk rule triggered: {$riskCheck['rule']->rule_name} (level: {$riskCheck['risk_level']})",
                        recommendationId: $rec->id,
                    );
                }
                return;
            }
        }

        try {
            $beforeSnapshot = null;
            if ($module === 'dispatch' && $rec->order_id) {
                $order = Order::find($rec->order_id);
                if ($order) {
                    $beforeSnapshot = json_encode([
                        'delivery_man_id' => $order->delivery_man_id,
                        'order_status' => $order->order_status,
                    ]);
                }
            }

            $success = $fn();
            $rec->status = $success ? 'accepted' : 'pending';
            $meta = $rec->metadata ?? [];
            $meta['auto_executed'] = $success;
            $meta['auto_executed_at'] = $success ? now()->toDateTimeString() : null;
            $meta['automation_mode'] = $mode;
            $rec->metadata = $meta;

            if ($success) {
                $rec->reviewed_at = now();
            }
            $rec->save();

            $afterSnapshot = null;
            if ($success && $module === 'dispatch' && $rec->order_id) {
                $order = Order::find($rec->order_id);
                if ($order) {
                    $afterSnapshot = json_encode([
                        'delivery_man_id' => $order->delivery_man_id,
                        'order_status' => $order->order_status,
                    ]);
                }
            }

            if ($success) {
                $this->logAction(
                    actionTaken: $rec->suggested_action,
                    module: $module,
                    affectedUserType: 'App\Models\DeliveryMan',
                    affectedUserId: $rec->metadata['driver_id'] ?? null,
                    beforeValue: $beforeSnapshot,
                    afterValue: $afterSnapshot,
                    reason: $rec->reason,
                    recommendationId: $rec->id,
                    rollbackAvailable: true,
                );
            }
        } catch (\Exception $e) {
            $meta = $rec->metadata ?? [];
            $meta['auto_execute_error'] = $e->getMessage();
            $rec->metadata = $meta;
            $rec->save();
        }
    }

    private function findBestDriverForOrder(Order $order, $availableDrivers): ?array
    {
        if ($availableDrivers->isEmpty()) {
            return null;
        }

        $zoneId = $order->zone_id;
        $preferred = $availableDrivers->filter(fn($d) => $d->zone_id == $zoneId);

        if ($preferred->isNotEmpty()) {
            $best = $preferred->sortBy('current_orders')->first();
        } else {
            $best = $availableDrivers->sortBy('current_orders')->first();
        }

        if (!$best) {
            return null;
        }

        return [
            'driver_id' => $best->id,
            'driver_name' => ($best->f_name ?? '') . ' ' . ($best->l_name ?? ''),
            'current_orders' => $best->current_orders,
            'zone_match' => $best->zone_id == $zoneId,
            'confidence' => $best->zone_id == $zoneId ? 0.85 : 0.6,
            'action' => "Assign {$best->f_name} {$best->l_name} to Order #{$order->id}",
        ];
    }

    private function findBestDriverForRoute(UrbanGoodzDedicatedRoute $route, $availableDrivers): ?array
    {
        if ($availableDrivers->isEmpty()) {
            return null;
        }

        $clientId = $route->business_client_id;
        $best = $availableDrivers->sortBy('current_orders')->first();

        if (!$best) {
            return null;
        }

        return [
            'driver_id' => $best->id,
            'driver_name' => ($best->f_name ?? '') . ' ' . ($best->l_name ?? ''),
            'current_orders' => $best->current_orders,
            'zone_match' => true,
            'confidence' => 0.7,
            'action' => "Assign {$best->f_name} {$best->l_name} to route '{$route->route_name}'",
        ];
    }

    public function notifyHighConfidenceRecommendations(array $results): void
    {
        $total = collect($results)->sum('count');
        if ($total === 0) {
            return;
        }

        $highConfidenceCount = AiCopilotRecommendation::where('status', 'pending')
            ->where('confidence_score', '>=', 0.8)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($highConfidenceCount === 0) {
            return;
        }

        $adminIds = \App\Models\Admin::where('is_active', 1)->pluck('id');
        foreach ($adminIds as $adminId) {
            \App\Models\UserNotification::create([
                'admin_id' => $adminId,
                'data' => json_encode([
                    'type' => 'ai_copilot_high_confidence',
                    'title' => 'AI Copilot: ' . $highConfidenceCount . ' high-confidence recommendations',
                    'description' => "{$total} new recommendations generated. {$highConfidenceCount} are high-confidence and ready for review.",
                    'priority' => 'normal',
                    'requires_action' => true,
                ]),
            ]);
        }
    }

    private function createRecommendation(string $type, string $subtype, $relatable, string $action, string $reason, float $confidence, array $meta = []): AiCopilotRecommendation
    {
        $existing = AiCopilotRecommendation::where('recommendation_type', $type)
            ->where('recommendation_subtype', $subtype)
            ->where('suggested_action', $action)
            ->where('status', 'pending');

        if ($relatable) {
            $existing->where('relatable_type', get_class($relatable))
                     ->where('relatable_id', $relatable->id);
        }

        $existing = $existing->first();
        if ($existing) {
            return $existing;
        }

        $data = [
            'recommendation_type' => $type,
            'recommendation_subtype' => $subtype,
            'suggested_action' => $action,
            'reason' => $reason,
            'confidence_score' => $confidence,
            'status' => 'pending',
            'metadata' => $meta,
        ];

        if ($relatable) {
            $data['relatable_type'] = get_class($relatable);
            $data['relatable_id'] = $relatable->id;

            if ($relatable instanceof Order) {
                $data['order_id'] = $relatable->id;
            } elseif ($relatable instanceof UrbanGoodzRoutePackage) {
                $data['package_id'] = $relatable->id;
                $data['route_id'] = $relatable->dedicated_route_id;
            } elseif ($relatable instanceof UrbanGoodzDedicatedRoute) {
                $data['route_id'] = $relatable->id;
            } elseif ($relatable instanceof OrderAnywhereRequest) {
                $data['request_id'] = $relatable->id;
            } elseif ($relatable instanceof UrbanGoodzAgeVerification) {
                $data['package_id'] = $relatable->package_id;
            } elseif ($relatable instanceof UrbanGoodzBusinessClientJob) {
                $data['order_id'] = $relatable->id;
            } elseif ($relatable instanceof UrbanGoodzLoadBoardLoad) {
                $data['metadata'] = array_merge($data['metadata'] ?? [], ['load_id' => $relatable->id]);
            } elseif ($relatable instanceof UrbanGoodzMedicalCourierJob) {
                $data['metadata'] = array_merge($data['metadata'] ?? [], ['medical_job_id' => $relatable->id]);
            }
        }

        return AiCopilotRecommendation::create($data);
    }

    public function generateAIOpsSummary(): string
    {
        $ai = app(UrbanGoodz\UrbanGoodzAIService::class);
        if (!$ai->isConfigured()) {
            return $this->generateRuleBasedSummary();
        }

        $data = [
            'date' => now()->format('Y-m-d H:i'),
            'unassigned_orders' => Order::whereNull('delivery_man_id')->whereIn('order_status', ['pending', 'confirmed'])->count(),
            'active_drivers' => DeliveryMan::where('active', 1)->where('application_status', 'approved')->count(),
            'stuck_orders' => Order::whereIn('order_status', ['confirmed', 'processing'])->where('created_at', '<', now()->subHours(self::STUCK_THRESHOLD_HOURS))->count(),
            'today_orders' => Order::whereDate('created_at', today())->count(),
            'today_revenue' => (float) Order::whereDate('created_at', today())->sum('order_amount'),
            'pending_refunds' => \App\Models\UrbanGoodzPaymentLedger::where('event_type', 'refund')->where('payment_status', 'pending')->count(),
            'available_loads' => UrbanGoodzLoadBoardLoad::where('status', 'available')->count(),
            'pending_order_anywhere' => OrderAnywhereRequest::where('status', 'pending')->count(),
            'pending_medical_jobs' => UrbanGoodzMedicalCourierJob::where('status', 'pending')->count(),
        ];

        return $ai->generateOpsSummary($data);
    }

    public function aiAnalyzeLoadBoard(): array
    {
        $ai = app(UrbanGoodz\UrbanGoodzAIService::class);
        if (!$ai->isConfigured()) {
            return ['summary' => 'AI not configured', 'recommendations' => []];
        }

        $loads = UrbanGoodzLoadBoardLoad::where('status', 'available')
            ->orderByDesc('payout_amount')
            ->limit(20)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'origin' => $l->origin_city . ', ' . $l->origin_state,
                'destination' => $l->destination_city . ', ' . $l->destination_state,
                'payout' => $l->payout_amount,
                'distance' => $l->distance_miles,
                'type' => $l->load_type,
                'equipment' => $l->equipment_type,
                'posted' => $l->created_at?->diffForHumans(),
            ])->toArray();

        $drivers = DeliveryMan::where('active', 1)
            ->where('application_status', 'approved')
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->f_name . ' ' . $d->l_name,
                'current_orders' => $d->current_orders ?? 0,
            ])->toArray();

        $prompt = "You are Urban Goodz AI Load Board Analyst. Analyze the available loads and driver availability.
Provide:
1. Top load opportunities (best value per mile)
2. Driver assignment suggestions
3. Pricing intelligence (any underpriced or overpriced loads)
4. Route optimization suggestions (loads that can be combined)
Return JSON: {\"summary\": string, \"top_opportunities\": [{\"load_id\": number, \"reason\": string, \"priority\": \"high\"|\"medium\"|\"low\"}], \"driver_suggestions\": [{\"driver_id\": number, \"load_id\": number, \"reason\": string}], \"market_insights\": string}";

        $result = $ai->chat($prompt, "Analyze current load board and driver availability.", ['loads' => $loads, 'drivers' => $drivers]);
        return json_decode(trim($result), true) ?? ['summary' => $result, 'recommendations' => []];
    }

    public function aiAnalyzeOrder(any $order): array
    {
        $ai = app(UrbanGoodz\UrbanGoodzAIService::class);
        if (!$ai->isConfigured()) {
            return ['risk_level' => 'unknown', 'recommendation' => 'AI not configured'];
        }

        $orderData = [
            'id' => $order->id,
            'status' => $order->order_status,
            'amount' => $order->order_amount,
            'payment_status' => $order->payment_status ?? 'unknown',
            'created' => $order->created_at?->format('Y-m-d H:i'),
            'age_hours' => $order->created_at ? now()->diffInHours($order->created_at) : 0,
            'delivery_type' => $order->order_type ?? 'standard',
        ];

        $prompt = "Analyze this order for operational risks and suggest actions. Return JSON:
{
  \"risk_level\": \"low\"|\"medium\"|\"high\",
  \"is_stuck\": boolean,
  \"suggested_action\": string,
  \"auto_action_possible\": boolean,
  \"notes\": string
}";

        $result = $ai->chat($prompt, "Analyze this order for issues.", ['order' => $orderData]);
        return json_decode(trim($result), true) ?? ['risk_level' => 'unknown', 'recommendation' => $result];
    }

    private function generateRuleBasedSummary(): string
    {
        $unassigned = Order::whereNull('delivery_man_id')->whereIn('order_status', ['pending', 'confirmed'])->count();
        $stuck = Order::whereIn('order_status', ['confirmed', 'processing'])->where('created_at', '<', now()->subHours(self::STUCK_THRESHOLD_HOURS))->count();
        $todayOrders = Order::whereDate('created_at', today())->count();
        $todayRevenue = Order::whereDate('created_at', today())->sum('order_amount');
        $availableLoads = UrbanGoodzLoadBoardLoad::where('status', 'available')->count();

        return "Daily Ops Briefing:\n"
            . "- Today's orders: {$todayOrders} (\$" . number_format($todayRevenue, 2) . ")\n"
            . "- Unassigned orders: {$unassigned}\n"
            . "- Stuck orders (>4h): {$stuck}\n"
            . "- Available loads: {$availableLoads}\n"
            . "- Active drivers: " . DeliveryMan::where('active', 1)->count();
    }
}
