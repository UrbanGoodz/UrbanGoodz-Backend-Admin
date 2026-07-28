<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiApproval;
use App\Models\AiTask;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiChiefOfStaffService
{
    public function __construct(
        protected ?UrbanGoodzAIService $aiService = null,
    ) {}

    public function getCommandCenterSummary(): array
    {
        $completed = $this->countWhen(
            'ai_tasks',
            fn() => AiTask::where('status', 'completed')->count(),
            ['status']
        );
        $inProgress = $this->countWhen(
            'ai_tasks',
            fn() => AiTask::where('status', 'running')->count(),
            ['status']
        );
        $planned = $this->countWhen(
            'ai_tasks',
            fn() => AiTask::whereIn('status', ['pending', 'scheduled'])->count(),
            ['status']
        );
        $businessNeeds = $this->countWhen(
            'business_needs',
            fn() => BusinessNeed::where('status', 'open')->count(),
            ['status']
        );
        $humanActions = $this->countWhen(
            'human_action_items',
            fn() => HumanActionItem::where('status', 'pending')->count(),
            ['status']
        );
        $failedTasks = $this->countWhen(
            'ai_tasks',
            fn() => AiTask::where('status', 'failed')->count(),
            ['status']
        );
        $escalatedActions = $this->countWhen(
            'human_action_items',
            fn() => HumanActionItem::where('status', 'escalated')->count(),
            ['status']
        );
        $approvals = $this->countWhen(
            'ai_approvals',
            fn() => AiApproval::where('decision', 'pending')->count(),
            ['decision']
        );
        $qualifiedProspects = $this->countWhen(
            'merchant_prospects',
            fn() => MerchantProspect::where('prospect_status', 'qualified')->count(),
            ['prospect_status']
        );
        $contactedProspects = $this->countWhen(
            'merchant_prospects',
            fn() => MerchantProspect::where('prospect_status', 'contacted')->count(),
            ['prospect_status']
        );
        $revenueInfluenced = $this->valueWhen(
            'merchant_prospects',
            fn() => (float) MerchantProspect::sum('attributed_revenue'),
            ['attributed_revenue']
        );

        return [
            'completed' => $completed,
            'in_progress' => $inProgress,
            'planned' => $planned,
            'business_needs' => $businessNeeds,
            'human_actions_required' => $humanActions,
            'blocked' => $this->sumAvailable([$failedTasks, $escalatedActions]),
            'approvals' => $approvals,
            'results' => [
                'prospects_qualified' => $qualifiedProspects,
                'prospects_contacted' => $contactedProspects,
                'revenue_influenced' => $revenueInfluenced,
            ],
        ];
    }

    public function generateExecutiveDailyBrief(): array
    {
        $completedTasks = $this->valueWhen(
            'ai_tasks',
            fn() => AiTask::with('agent')->where('status', 'completed')->latest()->take(5)->get(),
            ['status', 'created_at']
        );
        $businessNeeds = $this->valueWhen(
            'business_needs',
            fn() => BusinessNeed::where('status', 'open')->orderBy('severity', 'desc')->take(5)->get(),
            ['status', 'severity']
        );
        $urgentActions = $this->valueWhen(
            'human_action_items',
            fn() => HumanActionItem::where('status', 'pending')
                ->where('priority', 'urgent')
                ->take(5)
                ->get(),
            ['status', 'priority']
        );

        return [
            'title' => 'Executive Daily Brief',
            'date' => today()->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'data_source' => 'live_database',
            'metrics' => $this->getCommandCenterSummary(),
            'operational_alerts' => $this->getOperationalAlerts(),
            'completed_tasks' => $completedTasks,
            'business_needs' => $businessNeeds,
            'urgent_actions' => $urgentActions,
            'source_availability' => [
                'completed_tasks' => $completedTasks !== null,
                'business_needs' => $businessNeeds !== null,
                'urgent_actions' => $urgentActions !== null,
            ],
        ];
    }

    public function generateRoleBrief(string $role): array
    {
        $role = ucwords(str_replace('_', ' ', $role));

        return [
            'role' => $role,
            'date' => today()->toDateString(),
            'actions_required' => HumanActionItem::where('status', 'pending')
                ->where('assigned_role', $role)
                ->get(),
            'business_needs' => BusinessNeed::where('status', 'open')
                ->where('assigned_human_role', $role)
                ->get(),
            'completed_today' => AiTask::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    /**
     * The dashboard scan is intentionally read-only. A GET request must not
     * create operational records or turn a guessed condition into a fact.
     */
    public function runDiagnosticScan(): array
    {
        $alerts = $this->getOperationalAlerts();

        return [
            'alerts_detected' => collect($alerts)->where('count', '>', 0)->count(),
            'records_affected' => collect($alerts)->sum(fn(array $alert) => $alert['count'] ?? 0),
            'read_only' => true,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Grounded operational counts. A missing optional module table is exposed
     * as unavailable rather than represented by a made-up zero.
     */
    public function getOperationalAlerts(): array
    {
        return [
            $this->alert(
                'unassigned_orders',
                'Unassigned active orders',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')
                        ->whereNull('delivery_man_id')
                        ->whereNotIn('order_status', $this->terminalOrderStatuses())
                        ->count(),
                    ['delivery_man_id', 'order_status']
                ),
                'high',
                '/admin/order/list/all'
            ),
            $this->alert(
                'delayed_orders',
                'Orders open longer than two hours',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')
                        ->whereNotIn('order_status', $this->terminalOrderStatuses())
                        ->where('created_at', '<', now()->subHours(2))
                        ->count(),
                    ['order_status', 'created_at']
                ),
                'high',
                '/admin/order/list/all'
            ),
            $this->alert(
                'failed_payments',
                'Failed payment ledger events',
                $this->countWhen(
                    'urban_goodz_payment_ledgers',
                    fn() => DB::table('urban_goodz_payment_ledgers')
                        ->whereIn('payment_status', ['failed', 'declined', 'error'])
                        ->count(),
                    ['payment_status']
                ),
                'high',
                '/admin/urban-goodz/payments'
            ),
            $this->alert(
                'pending_refunds',
                'Refunds awaiting review',
                $this->countWhen(
                    'orders',
                    fn() => DB::table('orders')->where('order_status', 'refund_requested')->count(),
                    ['order_status']
                ),
                'high',
                '/admin/refund/pending'
            ),
            $this->alert(
                'pending_withdrawals',
                'Withdrawal requests awaiting approval',
                $this->countWhen(
                    'withdraw_requests',
                    fn() => DB::table('withdraw_requests')->where('approved', 0)->count(),
                    ['approved']
                ),
                'medium',
                '/admin/store/withdraw_list'
            ),
            $this->alert(
                'failed_queue_jobs',
                'Failed queue jobs',
                $this->countWhen('failed_jobs', fn() => DB::table('failed_jobs')->count()),
                'high',
                '/admin/urban-goodz/ai-operations/logs'
            ),
            $this->alert(
                'load_sourcing_errors',
                'Unresolved load-sourcing errors',
                $this->countWhen(
                    'load_source_errors',
                    fn() => DB::table('load_source_errors')->where('resolved', false)->count(),
                    ['resolved']
                ),
                'high',
                '/admin/urban-goodz/load-sourcing/errors'
            ),
            $this->alert(
                'out_of_stock_items',
                'Active items currently out of stock',
                $this->countWhen(
                    'items',
                    fn() => DB::table('items')->where('status', 1)->where('stock', '<=', 0)->count(),
                    ['stock', 'status']
                ),
                'medium',
                '/admin/item/list/all'
            ),
        ];
    }

    public function escalateOverdueActions(): void
    {
        $overdue = HumanActionItem::where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdue as $item) {
            if ($item->assigned_role !== 'Owner') {
                $item->update([
                    'assigned_role' => 'Owner',
                    'priority' => 'urgent',
                    'escalation_path' => 'Escalated to Owner due to SLA breach',
                    'status' => 'escalated',
                ]);
            }
        }
    }

    /**
     * Orders and fulfillment counts from the orders table.
     */
    public function getOrdersAndFulfillment(): array
    {
        $unassigned = $this->countWhen(
            'orders',
            fn() => DB::table('orders')
                ->whereNull('delivery_man_id')
                ->whereNotIn('order_status', $this->terminalOrderStatuses())
                ->count(),
            ['delivery_man_id', 'order_status']
        );

        $delayed = $this->countWhen(
            'orders',
            fn() => DB::table('orders')
                ->whereNotIn('order_status', $this->terminalOrderStatuses())
                ->where('created_at', '<', now()->subHours(2))
                ->count(),
            ['order_status', 'created_at']
        );

        $failedPayments = $this->countWhen(
            'orders',
            fn() => DB::table('orders')
                ->whereIn('order_status', ['payment_failed', 'payment_error'])
                ->count(),
            ['order_status']
        );

        $pendingRefunds = $this->countWhen(
            'orders',
            fn() => DB::table('orders')
                ->where('order_status', 'refund_requested')
                ->count(),
            ['order_status']
        );

        return [
            'unassigned' => $this->sectionItem('Unassigned active orders', $unassigned, '/admin/order/list/all', 'critical'),
            'delayed' => $this->sectionItem('Orders open > 2 hours', $delayed, '/admin/order/list/all', 'critical'),
            'failed_payments' => $this->sectionItem('Failed payment orders', $failedPayments, '/admin/order/list/all', 'critical'),
            'pending_refunds' => $this->sectionItem('Pending refund requests', $pendingRefunds, '/admin/refund/pending', 'warning'),
        ];
    }

    /**
     * Route status summary and exception counts from dedicated route tables.
     */
    public function getRouteAndExceptionSummary(): array
    {
        $routeUrl = '/admin/urban-goodz/dedicated-routes';
        $openRouteStatuses = [
            'draft', 'pending', 'pending_review', 'approved', 'active',
            'in_progress', 'pickup_pending', 'admin_review',
        ];

        $activeRoutes = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', ['active', 'in_progress'])
                ->count(),
            ['status']
        );

        $scheduledRoutes = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', $openRouteStatuses)
                ->whereDate('scheduled_date', '>=', today())
                ->count(),
            ['status', 'scheduled_date']
        );

        $unassignedRoutes = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', $openRouteStatuses)
                ->whereNull('assigned_driver_id')
                ->count(),
            ['status', 'assigned_driver_id']
        );

        $lateRoutes = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', $openRouteStatuses)
                ->whereDate('scheduled_date', '<', today())
                ->count(),
            ['status', 'scheduled_date']
        );

        $completedToday = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->where('status', 'completed')
                ->whereDate('route_completed_at', today())
                ->count(),
            ['status', 'route_completed_at']
        );

        $pendingReview = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', ['pending_review', 'admin_review'])
                ->count(),
            ['status']
        );

        $failedPackages = $this->countWhen(
            'urban_goodz_route_packages',
            fn() => DB::table('urban_goodz_route_packages')
                ->whereIn('status', ['failed', 'unable_to_deliver'])
                ->count(),
            ['status']
        );

        $returnedPackages = $this->countWhen(
            'urban_goodz_route_packages',
            fn() => DB::table('urban_goodz_route_packages')
                ->where(function ($query) {
                    $query->whereIn('status', [
                        'returned_to_pickup', 'returned_to_hub', 'returned_to_business',
                    ])->orWhereNotNull('returned_at');
                })
                ->count(),
            ['status', 'returned_at']
        );

        $redeliveryRequirements = $this->countWhen(
            'urban_goodz_route_packages',
            fn() => DB::table('urban_goodz_route_packages')
                ->where('status', 'unable_to_deliver')
                ->where('return_required', false)
                ->count(),
            ['status', 'return_required']
        );

        $packagesWithExceptions = $this->countWhen(
            'urban_goodz_route_packages',
            fn() => DB::table('urban_goodz_route_packages')
                ->whereNotNull('exception_reason')
                ->count(),
            ['exception_reason']
        );

        $pendingMedicalJobs = $this->countWhen(
            'urban_goodz_medical_courier_jobs',
            fn() => DB::table('urban_goodz_medical_courier_jobs')
                ->whereIn('status', ['pending', 'assigned', 'picked_up'])
                ->count(),
            ['status']
        );

        $medicalHandoffExceptions = $this->countWhen(
            'urban_goodz_medical_custody_logs',
            fn() => DB::table('urban_goodz_medical_custody_logs')
                ->where('custody_event', 'exception')
                ->count(),
            ['custody_event']
        );

        $logisticsExceptions = $this->countWhen(
            'urban_goodz_business_client_jobs',
            fn() => DB::table('urban_goodz_business_client_jobs')
                ->whereNotNull('exception_reason')
                ->count(),
            ['exception_reason']
        );

        return [
            'active_routes' => $this->sectionItem('Active / in-progress routes', $activeRoutes, $routeUrl, 'healthy'),
            'scheduled_routes' => $this->sectionItem('Scheduled routes', $scheduledRoutes, $routeUrl, 'healthy'),
            'unassigned_routes' => $this->sectionItem('Unassigned routes', $unassignedRoutes, $routeUrl, 'warning'),
            'late_routes' => $this->sectionItem('Late routes', $lateRoutes, $routeUrl, 'critical'),
            'completed_today' => $this->sectionItem('Routes completed today', $completedToday, $routeUrl, 'healthy'),
            'pending_review' => $this->sectionItem('Routes awaiting review', $pendingReview, $routeUrl, 'warning'),
            'failed_packages' => $this->sectionItem('Failed stops / undeliverable packages', $failedPackages, $routeUrl, 'critical'),
            'returned_packages' => $this->sectionItem('Returned packages', $returnedPackages, $routeUrl, 'warning'),
            'redelivery_requirements' => $this->sectionItem('Packages requiring a redelivery decision', $redeliveryRequirements, $routeUrl, 'warning'),
            'package_exceptions' => $this->sectionItem('Route package exceptions', $packagesWithExceptions, $routeUrl, 'critical'),
            'courier_incidents' => $this->unavailableItem(
                'Courier incidents',
                $routeUrl,
                'No supported courier incident source is deployed.'
            ),
            'pending_medical_jobs' => $this->sectionItem('Active medical courier jobs', $pendingMedicalJobs, '/admin/urban-goodz/medical-courier', 'healthy'),
            'medical_handoff_exceptions' => $this->sectionItem('Medical custody handoff exceptions', $medicalHandoffExceptions, '/admin/urban-goodz/medical-courier', 'critical'),
            'logistics_exceptions' => $this->sectionItem('Business logistics exceptions', $logisticsExceptions, '/admin/urban-goodz/business-clients', 'critical'),
        ];
    }

    /**
     * Driver health: active drivers, certification gaps, payout requests.
     */
    public function getDriverIssueSummary(): array
    {
        $driverUrl = '/admin/delivery-man';
        $routeUrl = '/admin/urban-goodz/dedicated-routes';

        $totalDrivers = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')->count()
        );

        $activeDrivers = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 1)
                ->where('status', 1)
                ->count(),
            ['active', 'status']
        );

        $inactiveDrivers = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 0)
                ->where('application_status', 'approved')
                ->count(),
            ['active', 'application_status']
        );

        $suspendedDrivers = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('status', 0)
                ->where('application_status', 'approved')
                ->count(),
            ['status', 'application_status']
        );

        $incompleteOnboarding = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where(function ($query) {
                    $query->whereNull('application_status')
                        ->orWhere('application_status', '!=', 'approved');
                })
                ->count(),
            ['application_status']
        );

        $missingVehicleData = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 1)
                ->where('application_status', 'approved')
                ->where(function ($query) {
                    $query->whereNull('vehicle_type')
                        ->orWhere('vehicle_type', '');
                })
                ->count(),
            ['active', 'application_status', 'vehicle_type']
        );

        $expiringCerts = $this->countWhen(
            'driver_certifications',
            fn() => DB::table('driver_certifications')
                ->where('status', 'active')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>=', now())
                ->count(),
            ['status', 'expiry_date']
        );

        $expiredCerts = $this->countWhen(
            'driver_certifications',
            fn() => DB::table('driver_certifications')
                ->where('expiry_date', '<', now())
                ->count(),
            ['expiry_date']
        );

        $expiredComplianceDocuments = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 1)
                ->where(function ($query) {
                    foreach ([
                        'insurance_expiration',
                        'registration_expiration',
                        'inspection_expiration',
                        'cdl_expiration',
                    ] as $column) {
                        $query->orWhere(function ($dateQuery) use ($column) {
                            $dateQuery->whereNotNull($column)
                                ->whereDate($column, '<', today());
                        });
                    }
                })
                ->count(),
            [
                'active',
                'insurance_expiration',
                'registration_expiration',
                'inspection_expiration',
                'cdl_expiration',
            ]
        );

        $uninsuredVehicles = $this->countWhen(
            'delivery_man_vehicles',
            fn() => DB::table('delivery_man_vehicles')
                ->where('is_active', 1)
                ->where(function ($q) {
                    $q->where('is_insured', 0)
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('insurance_expiry')
                                ->where('insurance_expiry', '<', now());
                        });
                })
                ->count(),
            ['is_active', 'is_insured', 'insurance_expiry']
        );

        $pendingPayouts = $this->countWhen(
            'urban_goodz_driver_payout_requests',
            fn() => DB::table('urban_goodz_driver_payout_requests')
                ->where('status', 'pending')
                ->count(),
            ['status']
        );

        $payoutIssues = $this->countWhen(
            'urban_goodz_driver_payout_requests',
            fn() => DB::table('urban_goodz_driver_payout_requests')
                ->whereIn('status', ['rejected', 'held'])
                ->count(),
            ['status']
        );

        $failedAssignments = $this->countWhen(
            'urban_goodz_route_assignments',
            fn() => DB::table('urban_goodz_route_assignments')
                ->where('status', 'canceled')
                ->count(),
            ['status']
        );

        $lateDeliveries = $this->countWhen(
            'urban_goodz_route_packages',
            fn() => DB::table('urban_goodz_route_packages')
                ->whereNotNull('delivery_window_end')
                ->where('delivery_window_end', '<', now())
                ->whereNotIn('status', [
                    'delivered', 'completed', 'returned_to_pickup',
                    'returned_to_hub', 'returned_to_business',
                ])
                ->count(),
            ['delivery_window_end', 'status']
        );

        $unassignedWork = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereNull('assigned_driver_id')
                ->whereIn('status', [
                    'pending', 'pending_review', 'approved', 'active',
                    'in_progress', 'pickup_pending', 'admin_review',
                ])
                ->count(),
            ['assigned_driver_id', 'status']
        );

        $repeatedCancellations = $this->countWhen(
            'urban_goodz_route_assignments',
            fn() => DB::table('urban_goodz_route_assignments')
                ->where('status', 'canceled')
                ->groupBy('delivery_man_id')
                ->havingRaw('COUNT(*) >= 2')
                ->get()
                ->count(),
            ['status', 'delivery_man_id']
        );

        $medicalEligibilityGaps = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 1)
                ->where('available_for_medical_courier', 1)
                ->where('has_medical_courier_training', 0)
                ->count(),
            ['active', 'available_for_medical_courier', 'has_medical_courier_training']
        );

        $logisticsCapabilityGaps = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('active', 1)
                ->where('load_board_eligible', 1)
                ->where(function ($query) {
                    $query->whereNull('vehicle_type')
                        ->orWhere('vehicle_type', '')
                        ->orWhereNull('max_payload_lbs')
                        ->orWhere('max_payload_lbs', '<=', 0);
                })
                ->count(),
            ['active', 'load_board_eligible', 'vehicle_type', 'max_payload_lbs']
        );

        return [
            'total_drivers' => $this->sectionItem('Total registered drivers', $totalDrivers, $driverUrl, 'healthy'),
            'active_drivers' => $this->sectionItem('Active drivers', $activeDrivers, $driverUrl, 'healthy'),
            'inactive_drivers' => $this->sectionItem('Inactive approved drivers', $inactiveDrivers, $driverUrl, 'warning'),
            'suspended_drivers' => $this->sectionItem('Suspended / disabled drivers', $suspendedDrivers, $driverUrl, 'critical'),
            'incomplete_onboarding' => $this->sectionItem('Incomplete driver onboarding', $incompleteOnboarding, $driverUrl, 'warning'),
            'missing_vehicle_data' => $this->sectionItem('Approved drivers missing vehicle data', $missingVehicleData, $driverUrl, 'warning'),
            'expiring_certs' => $this->sectionItem('Certifications expiring within 30 days', $expiringCerts, $driverUrl, 'warning'),
            'expired_certs' => $this->sectionItem('Expired certifications', $expiredCerts, $driverUrl, 'critical'),
            'expired_documents' => $this->sectionItem('Drivers with expired compliance documents', $expiredComplianceDocuments, $driverUrl, 'critical'),
            'uninsured_vehicles' => $this->sectionItem('Active vehicles without valid insurance', $uninsuredVehicles, $driverUrl, 'critical'),
            'pending_payouts' => $this->sectionItem('Pending driver payout requests', $pendingPayouts, '/admin/urban-goodz/driver-payouts', 'warning'),
            'payout_issues' => $this->sectionItem('Rejected or held driver payouts', $payoutIssues, '/admin/urban-goodz/driver-payouts', 'critical'),
            'failed_assignments' => $this->sectionItem('Canceled route assignments', $failedAssignments, $routeUrl, 'warning'),
            'late_deliveries' => $this->sectionItem('Late delivery stops', $lateDeliveries, $routeUrl, 'critical'),
            'unassigned_work' => $this->sectionItem('Unassigned route work', $unassignedWork, $routeUrl, 'warning'),
            'repeated_cancellations' => $this->sectionItem('Drivers with repeated assignment cancellations', $repeatedCancellations, $routeUrl, 'critical'),
            'medical_eligibility_gaps' => $this->sectionItem('Medical courier eligibility gaps', $medicalEligibilityGaps, $driverUrl, 'critical'),
            'logistics_capability_gaps' => $this->sectionItem('Load-board drivers missing logistics capability data', $logisticsCapabilityGaps, $driverUrl, 'warning'),
            'unresolved_incidents' => $this->unavailableItem(
                'Unresolved driver incidents',
                $driverUrl,
                'No supported driver incident source is deployed.'
            ),
        ];
    }

    /**
     * Vendor and business summary counts.
     */
    public function getVendorAndBusinessSummary(): array
    {
        $totalVendors = $this->countWhen(
            'vendors',
            fn() => DB::table('vendors')->count()
        );

        $totalStores = $this->countWhen(
            'stores',
            fn() => DB::table('stores')->count()
        );

        $pendingWithdrawals = $this->countWhen(
            'withdraw_requests',
            fn() => DB::table('withdraw_requests')->where('approved', 0)->count(),
            ['approved']
        );

        $totalBusinessClients = $this->countWhen(
            'urban_goodz_business_clients',
            fn() => DB::table('urban_goodz_business_clients')->count()
        );

        return [
            'total_vendors' => $this->sectionItem('Registered vendors', $totalVendors, '/admin/store/list', 'healthy'),
            'total_stores' => $this->sectionItem('Stores', $totalStores, '/admin/store/list', 'healthy'),
            'pending_withdrawals' => $this->sectionItem('Pending vendor withdrawals', $pendingWithdrawals, '/admin/store/withdraw_list', 'warning'),
            'business_clients' => $this->sectionItem('Urban Goodz business clients', $totalBusinessClients, '/admin/urban-goodz/business-clients', 'healthy'),
        ];
    }

    /**
     * Payment and ledger summary.
     */
    public function getPaymentsAndLedger(): array
    {
        $captured = $this->countWhen(
            'urban_goodz_payment_ledgers',
            fn() => DB::table('urban_goodz_payment_ledgers')
                ->where('payment_status', 'captured')
                ->count(),
            ['payment_status']
        );

        $pending = $this->countWhen(
            'urban_goodz_payment_ledgers',
            fn() => DB::table('urban_goodz_payment_ledgers')
                ->where('payment_status', 'pending')
                ->count(),
            ['payment_status']
        );

        $failed = $this->countWhen(
            'urban_goodz_payment_ledgers',
            fn() => DB::table('urban_goodz_payment_ledgers')
                ->whereIn('payment_status', ['failed', 'declined', 'error'])
                ->count(),
            ['payment_status']
        );

        $pendingSplits = $this->countWhen(
            'urban_goodz_payment_splits',
            fn() => DB::table('urban_goodz_payment_splits')
                ->where('status', 'pending')
                ->count(),
            ['status']
        );

        return [
            'captured' => $this->sectionItem('Captured payments', $captured, '/admin/urban-goodz/payments', 'healthy'),
            'pending' => $this->sectionItem('Pending payments', $pending, '/admin/urban-goodz/payments', 'warning'),
            'failed' => $this->sectionItem('Failed / declined payments', $failed, '/admin/urban-goodz/payments', 'critical'),
            'pending_splits' => $this->sectionItem('Pending payment splits', $pendingSplits, '/admin/urban-goodz/payments', 'warning'),
        ];
    }

    /**
     * Load sourcing status from the load board.
     */
    public function getLoadSourcingStatus(): array
    {
        $available = $this->countWhen(
            'urban_goodz_load_board_loads',
            fn() => DB::table('urban_goodz_load_board_loads')
                ->where('status', 'available')
                ->count(),
            ['status']
        );

        $assigned = $this->countWhen(
            'urban_goodz_load_board_loads',
            fn() => DB::table('urban_goodz_load_board_loads')
                ->where('status', 'assigned')
                ->count(),
            ['status']
        );

        $inTransit = $this->countWhen(
            'urban_goodz_load_board_loads',
            fn() => DB::table('urban_goodz_load_board_loads')
                ->whereIn('status', ['in_transit', 'picked_up'])
                ->count(),
            ['status']
        );

        $unassigned = $this->countWhen(
            'urban_goodz_load_board_loads',
            fn() => DB::table('urban_goodz_load_board_loads')
                ->whereNull('assigned_driver_id')
                ->where('status', 'available')
                ->count(),
            ['assigned_driver_id', 'status']
        );

        $unresolvedErrors = $this->countWhen(
            'load_source_errors',
            fn() => DB::table('load_source_errors')
                ->where('resolved', false)
                ->count(),
            ['resolved']
        );

        return [
            'available_loads' => $this->sectionItem('Available loads', $available, '/admin/urban-goodz/load-sourcing', 'healthy'),
            'assigned_loads' => $this->sectionItem('Assigned loads', $assigned, '/admin/urban-goodz/load-sourcing', 'healthy'),
            'in_transit' => $this->sectionItem('Loads in transit', $inTransit, '/admin/urban-goodz/load-sourcing', 'healthy'),
            'unassigned' => $this->sectionItem('Available but unassigned loads', $unassigned, '/admin/urban-goodz/load-sourcing', 'warning'),
            'unresolved_errors' => $this->sectionItem('Unresolved source errors', $unresolvedErrors, '/admin/urban-goodz/load-sourcing/errors', 'critical'),
        ];
    }

    /**
     * Sanitized AI provider health. Never exposes credentials, tokens, keys,
     * or raw provider responses. Shows only operational status fields.
     */
    public function getProviderHealth(): array
    {
        if ($this->aiService === null) {
            return [
                'provider' => null,
                'enabled' => false,
                'configured' => false,
                'credentials_present' => 'NO',
                'connectivity_state' => 'unavailable',
                'model' => null,
                'healthy' => false,
                'last_success' => null,
                'last_failure_category' => 'service_unavailable',
                'fallback_state' => 'deterministic_only',
                'checked_at' => now()->toIso8601String(),
                'error_code' => 'service_unavailable',
                'available' => false,
                'reason' => 'AI provider health is unavailable.',
            ];
        }

        try {
            $provider = $this->sanitizeOperationalValue($this->aiService->providerName());
            $configured = $this->aiService->isConfigured();
        } catch (\Throwable) {
            return [
                'provider' => null,
                'enabled' => false,
                'configured' => false,
                'credentials_present' => 'NO',
                'connectivity_state' => 'unavailable',
                'model' => null,
                'healthy' => false,
                'last_success' => null,
                'last_failure_category' => 'configuration_check_failed',
                'fallback_state' => 'deterministic_only',
                'checked_at' => now()->toIso8601String(),
                'error_code' => 'configuration_check_failed',
                'available' => false,
                'reason' => 'AI provider configuration could not be checked.',
            ];
        }

        if (!$configured) {
            $health = [];

            try {
                // Provider implementations return locally when not configured,
                // which exposes the selected model without a network request.
                $health = $this->aiService->healthCheck();
            } catch (\Throwable) {
                $health = [];
            }

            $errorCode = $this->sanitizeFailureCategory(
                $health['error_code'] ?? ($provider === 'disabled' ? 'provider_disabled' : 'provider_not_configured')
            );
            $enabled = $provider !== 'disabled' && $errorCode !== 'unsupported_provider';
            $checkedAt = $this->sanitizeTimestamp($health['checked_at'] ?? null);

            return [
                'provider' => $provider,
                'enabled' => $enabled,
                'configured' => false,
                'credentials_present' => 'NO',
                'connectivity_state' => $enabled ? 'not_configured' : 'disabled',
                'model' => $this->sanitizeOperationalValue($health['model'] ?? null),
                'healthy' => false,
                'last_success' => null,
                'last_failure_category' => $errorCode,
                'fallback_state' => 'deterministic_only',
                'checked_at' => $checkedAt,
                'error_code' => $errorCode,
                'available' => true,
                'reason' => $enabled
                    ? 'AI provider credentials are not configured.'
                    : 'AI generation is disabled.',
            ];
        }

        try {
            $health = $this->aiService->healthCheck();
            $healthy = (bool) ($health['healthy'] ?? false);
            $errorCode = $healthy
                ? null
                : $this->sanitizeFailureCategory($health['error_code'] ?? 'connectivity_failed');
            $checkedAt = $this->sanitizeTimestamp($health['checked_at'] ?? null);

            return [
                'provider' => $this->sanitizeOperationalValue($health['provider'] ?? $provider),
                'enabled' => true,
                'configured' => true,
                'credentials_present' => 'YES',
                'connectivity_state' => $healthy ? 'connected' : 'disconnected',
                'model' => $this->sanitizeOperationalValue($health['model'] ?? null),
                'healthy' => $healthy,
                'last_success' => $healthy ? $checkedAt : null,
                'last_failure_category' => $errorCode,
                'fallback_state' => $healthy ? 'primary_healthy' : 'deterministic_fallback',
                'checked_at' => $checkedAt,
                'error_code' => $errorCode,
                'available' => true,
                'reason' => $healthy ? null : 'Provider connectivity check failed.',
            ];
        } catch (\Throwable) {
            return [
                'provider' => $provider,
                'enabled' => true,
                'configured' => true,
                'credentials_present' => 'YES',
                'connectivity_state' => 'error',
                'model' => null,
                'healthy' => false,
                'last_success' => null,
                'last_failure_category' => 'health_check_exception',
                'fallback_state' => 'deterministic_fallback',
                'checked_at' => now()->toIso8601String(),
                'error_code' => 'health_check_exception',
                'available' => true,
                'reason' => 'Provider health check failed.',
            ];
        }
    }

    /**
     * Deterministic recommendations from grounded data, with optional AI-generated
     * analysis when the provider is healthy.
     */
    public function getRecommendations(
        ?array $operationalAlerts = null,
        ?array $routeIssues = null,
        ?array $driverIssues = null,
    ): array
    {
        $recommendations = [];

        $alerts = collect($operationalAlerts ?? $this->getOperationalAlerts());
        $criticalAlerts = $alerts->filter(
            fn(array $alert) => ($alert['available'] ?? false)
                && ($alert['count'] ?? 0) > 0
                && $alert['severity'] === 'high'
        );

        foreach ($criticalAlerts as $alert) {
            $recommendations[] = [
                'type' => 'deterministic',
                'source' => 'operational_alerts',
                'title' => "Address {$alert['label']}",
                'detail' => "{$alert['count']} record(s) require attention.",
                'url' => $alert['url'],
                'priority' => 'high',
            ];
        }

        $routeIssues ??= $this->getRouteAndExceptionSummary();
        $failedPkgs = $routeIssues['failed_packages']['count'] ?? null;
        if ($failedPkgs !== null && $failedPkgs > 0) {
            $recommendations[] = [
                'type' => 'deterministic',
                'source' => 'route_exceptions',
                'title' => 'Review failed package deliveries',
                'detail' => "{$failedPkgs} package(s) failed or are undeliverable.",
                'url' => '/admin/urban-goodz/dedicated-routes',
                'priority' => 'medium',
            ];
        }

        $driverIssues ??= $this->getDriverIssueSummary();
        $expiredCerts = $driverIssues['expired_certs']['count'] ?? null;
        if ($expiredCerts !== null && $expiredCerts > 0) {
            $recommendations[] = [
                'type' => 'deterministic',
                'source' => 'driver_certifications',
                'title' => 'Renew expired driver certifications',
                'detail' => "{$expiredCerts} certification(s) have expired.",
                'url' => '/admin/delivery-man',
                'priority' => 'high',
            ];
        }

        $aiAnalysis = $this->unavailableAiAnalysis(
            'service_unavailable',
            'AI analysis service is unavailable.'
        );

        if ($this->aiService !== null) {
            try {
                $provider = $this->sanitizeOperationalValue($this->aiService->providerName());

                if (!$this->aiService->isConfigured()) {
                    $aiAnalysis = $this->unavailableAiAnalysis(
                        'provider_not_configured',
                        'AI analysis is not configured.',
                        $provider,
                        'not_configured'
                    );
                } else {
                    // Only aggregated counts and fixed labels enter the prompt.
                    // No customer, driver, vendor, address, or credential fields
                    // are included in this context.
                    $context = [
                        'operational_alerts' => $operationalAlerts ?? $this->getOperationalAlerts(),
                        'route_summary' => $routeIssues,
                        'driver_summary' => $driverIssues,
                    ];

                    $aiResult = $this->aiService->chatResult(
                        "You are an AI chief of staff for a logistics platform. Given the aggregated operational facts below, provide a prioritized list of 3-5 recommended actions. Be concise and specific. Return JSON array: [{\"title\": string, \"detail\": string, \"priority\": \"high\"|\"medium\"|\"low\"}]",
                        'Analyze the current operational state and recommend actions.',
                        $context
                    );

                    $resultProvider = $this->sanitizeOperationalValue($aiResult['provider'] ?? $provider);
                    $model = $this->sanitizeOperationalValue($aiResult['model'] ?? null);

                    if (!($aiResult['success'] ?? false)) {
                        $aiAnalysis = $this->unavailableAiAnalysis(
                            $this->sanitizeFailureCategory($aiResult['error_code'] ?? 'generation_failed'),
                            'AI analysis generation failed; deterministic recommendations remain active.',
                            $resultProvider,
                            'failed',
                            $model
                        );
                    } else {
                        $items = $this->parseAiRecommendations($aiResult['response'] ?? '');

                        if ($items === null) {
                            $aiAnalysis = $this->unavailableAiAnalysis(
                                'invalid_provider_response',
                                'AI analysis returned an invalid format; deterministic recommendations remain active.',
                                $resultProvider,
                                'failed',
                                $model
                            );
                        } else {
                            $aiAnalysis = [
                                'type' => 'ai_generated',
                                'source' => 'ai_provider',
                                'status' => 'available',
                                'available' => true,
                                'provider' => $resultProvider,
                                'model' => $model,
                                'generated_at' => now()->toIso8601String(),
                                'failure_category' => null,
                                'reason' => null,
                                'items' => $items,
                            ];
                        }
                    }
                }
            } catch (\Throwable) {
                $aiAnalysis = $this->unavailableAiAnalysis(
                    'generation_exception',
                    'AI analysis generation failed; deterministic recommendations remain active.',
                    null,
                    'failed'
                );
            }
        }

        return [
            'deterministic' => $recommendations,
            'ai_analysis' => $aiAnalysis,
        ];
    }

    /**
     * Full chief-of-staff page data assembling all 10 sections.
     */
    public function getChiefOfStaffDashboard(): array
    {
        $brief = $this->generateExecutiveDailyBrief();
        $summary = $brief['metrics'];
        $diagnostics = $this->runDiagnosticScan();
        $routes = $this->getRouteAndExceptionSummary();
        $drivers = $this->getDriverIssueSummary();

        return [
            'brief' => $brief,
            'summary' => $summary,
            'diagnostics' => $diagnostics,
            'orders_fulfillment' => $this->getOrdersAndFulfillment(),
            'routes_exceptions' => $routes,
            'driver_issues' => $drivers,
            'vendor_business' => $this->getVendorAndBusinessSummary(),
            'payments_ledger' => $this->getPaymentsAndLedger(),
            'load_sourcing' => $this->getLoadSourcingStatus(),
            'provider_health' => $this->getProviderHealth(),
            'recommendations' => $this->getRecommendations(
                $brief['operational_alerts'],
                $routes,
                $drivers
            ),
        ];
    }

    private function countWhen(string $table, callable $query, array $requiredColumns = []): ?int
    {
        $value = $this->valueWhen($table, $query, $requiredColumns);

        return $value === null ? null : (int) $value;
    }

    private function valueWhen(string $table, callable $query, array $requiredColumns = []): mixed
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return null;
            }
        }

        try {
            return $query();
        } catch (\Throwable) {
            // Optional module failures are represented as unavailable. Raw
            // database errors are never rendered into the owner dashboard.
            return null;
        }
    }

    private function sumAvailable(array $values): ?int
    {
        if (collect($values)->contains(fn($value) => $value === null)) {
            return null;
        }

        return (int) array_sum($values);
    }

    private function alert(string $key, string $label, ?int $count, string $severity, string $url): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'severity' => $severity,
            'state' => $count === null
                ? 'unavailable'
                : ($count > 0 ? ($severity === 'high' ? 'critical' : 'warning') : 'healthy'),
            'reason' => $count === null ? 'Source table or required fields are unavailable.' : null,
            'url' => $url,
        ];
    }

    private function sectionItem(
        string $label,
        ?int $count,
        string $url,
        string $positiveState = 'warning',
        string $zeroState = 'healthy',
    ): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'state' => $count === null
                ? 'unavailable'
                : ($count > 0 ? $positiveState : $zeroState),
            'reason' => $count === null ? 'Source table or required fields are unavailable.' : null,
            'url' => $url,
        ];
    }

    private function unavailableItem(string $label, string $url, string $reason): array
    {
        return [
            'label' => $label,
            'count' => null,
            'available' => false,
            'state' => 'unavailable',
            'reason' => $reason,
            'url' => $url,
        ];
    }

    private function unavailableAiAnalysis(
        string $failureCategory,
        string $reason,
        ?string $provider = null,
        string $status = 'unavailable',
        ?string $model = null,
    ): array {
        return [
            'type' => 'ai_generated',
            'source' => 'ai_provider',
            'status' => $status,
            'available' => false,
            'provider' => $this->sanitizeOperationalValue($provider),
            'model' => $this->sanitizeOperationalValue($model),
            'generated_at' => now()->toIso8601String(),
            'failure_category' => $this->sanitizeFailureCategory($failureCategory),
            'reason' => $reason,
            'items' => [],
        ];
    }

    private function parseAiRecommendations(mixed $response): ?array
    {
        if (!is_string($response)) {
            return null;
        }

        $json = trim($response);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json) ?? $json;
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        $items = [];

        foreach (array_slice($decoded, 0, 5) as $item) {
            if (!is_array($item)) {
                return null;
            }

            $title = $this->sanitizeGeneratedText($item['title'] ?? '', 160);
            if ($title === '') {
                continue;
            }

            $priority = strtolower((string) ($item['priority'] ?? 'low'));
            if (!in_array($priority, ['high', 'medium', 'low'], true)) {
                $priority = 'low';
            }

            $items[] = [
                'type' => 'ai_generated',
                'title' => $title,
                'detail' => $this->sanitizeGeneratedText($item['detail'] ?? '', 800),
                'priority' => $priority,
            ];
        }

        return $items;
    }

    private function sanitizeGeneratedText(mixed $value, int $limit): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
        $text = preg_replace(
            '/(?:sk-[A-Za-z0-9_-]{6,}|Bearer\s+\S+|base64:[A-Za-z0-9+\/=]+|(?:api[_-]?key|token|secret)\s*[:=]\s*\S+)/i',
            '[redacted]',
            $text
        ) ?? '';

        return mb_substr($text, 0, $limit);
    }

    private function sanitizeOperationalValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/sk-|bearer|base64:|api[_-]?key|token|secret/i', $value)) {
            return '[redacted]';
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags($value)) ?? '';

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function sanitizeFailureCategory(mixed $value): string
    {
        $category = strtolower(trim((string) $value));
        $allowed = [
            'provider_disabled',
            'unsupported_provider',
            'provider_not_configured',
            'provider_error',
            'empty_provider_response',
            'provider_unavailable',
            'connectivity_failed',
            'connection_refused',
            'service_unavailable',
            'configuration_check_failed',
            'health_check_exception',
            'generation_failed',
            'invalid_provider_response',
            'generation_exception',
        ];

        return in_array($category, $allowed, true) ? $category : 'unknown_failure';
    }

    private function sanitizeTimestamp(mixed $value): string
    {
        if (is_string($value) && preg_match('/^[0-9T:+.\-Z]{10,40}$/', $value)) {
            return $value;
        }

        return now()->toIso8601String();
    }

    private function terminalOrderStatuses(): array
    {
        return ['delivered', 'failed', 'canceled', 'cancelled', 'refunded', 'refund_request_canceled'];
    }
}
