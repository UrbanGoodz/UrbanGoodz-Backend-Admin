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
        return [
            'completed' => AiTask::where('status', 'completed')->count(),
            'in_progress' => AiTask::where('status', 'running')->count(),
            'planned' => AiTask::whereIn('status', ['pending', 'scheduled'])->count(),
            'business_needs' => BusinessNeed::where('status', 'open')->count(),
            'human_actions_required' => HumanActionItem::where('status', 'pending')->count(),
            'blocked' => AiTask::where('status', 'failed')->count()
                + HumanActionItem::where('status', 'escalated')->count(),
            'approvals' => AiApproval::where('decision', 'pending')->count(),
            'results' => [
                'prospects_qualified' => MerchantProspect::where('prospect_status', 'qualified')->count(),
                'prospects_contacted' => MerchantProspect::where('prospect_status', 'contacted')->count(),
                'revenue_influenced' => (float) MerchantProspect::sum('attributed_revenue'),
            ],
        ];
    }

    public function generateExecutiveDailyBrief(): array
    {
        return [
            'title' => 'Executive Daily Brief',
            'date' => today()->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'data_source' => 'live_database',
            'metrics' => $this->getCommandCenterSummary(),
            'operational_alerts' => $this->getOperationalAlerts(),
            'completed_tasks' => AiTask::with('agent')->where('status', 'completed')->latest()->take(5)->get(),
            'business_needs' => BusinessNeed::where('status', 'open')->orderBy('severity', 'desc')->take(5)->get(),
            'urgent_actions' => HumanActionItem::where('status', 'pending')
                ->where('priority', 'urgent')
                ->take(5)
                ->get(),
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
                '/admin/vendor/withdraw_list'
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
            'unassigned' => $this->sectionItem('Unassigned active orders', $unassigned, '/admin/order/list/all'),
            'delayed' => $this->sectionItem('Orders open > 2 hours', $delayed, '/admin/order/list/all'),
            'failed_payments' => $this->sectionItem('Failed payment orders', $failedPayments, '/admin/order/list/all'),
            'pending_refunds' => $this->sectionItem('Pending refund requests', $pendingRefunds, '/admin/refund/pending'),
        ];
    }

    /**
     * Route status summary and exception counts from dedicated route tables.
     */
    public function getRouteAndExceptionSummary(): array
    {
        $activeRoutes = $this->countWhen(
            'urban_goodz_dedicated_routes',
            fn() => DB::table('urban_goodz_dedicated_routes')
                ->whereIn('status', ['active', 'in_progress'])
                ->count(),
            ['status']
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

        return [
            'active_routes' => $this->sectionItem('Active / in-progress routes', $activeRoutes, '/admin/urban-goodz/routes'),
            'completed_today' => $this->sectionItem('Routes completed today', $completedToday, '/admin/urban-goodz/routes'),
            'pending_review' => $this->sectionItem('Routes awaiting review', $pendingReview, '/admin/urban-goodz/routes'),
            'failed_packages' => $this->sectionItem('Failed or undeliverable packages', $failedPackages, '/admin/urban-goodz/routes'),
            'package_exceptions' => $this->sectionItem('Packages with exception reasons', $packagesWithExceptions, '/admin/urban-goodz/routes'),
            'pending_medical_jobs' => $this->sectionItem('Active medical courier jobs', $pendingMedicalJobs, '/admin/urban-goodz/medical-courier'),
        ];
    }

    /**
     * Driver health: active drivers, certification gaps, payout requests.
     */
    public function getDriverIssueSummary(): array
    {
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

        $pendingApplications = $this->countWhen(
            'delivery_men',
            fn() => DB::table('delivery_men')
                ->where('application_status', 'pending')
                ->count(),
            ['application_status']
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

        return [
            'total_drivers' => $this->sectionItem('Total registered drivers', $totalDrivers, '/admin/delivery-man/list'),
            'active_drivers' => $this->sectionItem('Active drivers', $activeDrivers, '/admin/delivery-man/list'),
            'pending_applications' => $this->sectionItem('Pending driver applications', $pendingApplications, '/admin/delivery-man/list'),
            'expiring_certs' => $this->sectionItem('Certifications expiring within 30 days', $expiringCerts, '/admin/delivery-man/list'),
            'expired_certs' => $this->sectionItem('Expired certifications', $expiredCerts, '/admin/delivery-man/list'),
            'uninsured_vehicles' => $this->sectionItem('Active vehicles without valid insurance', $uninsuredVehicles, '/admin/delivery-man/list'),
            'pending_payouts' => $this->sectionItem('Pending driver payout requests', $pendingPayouts, '/admin/vendor/withdraw_list'),
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
            'total_vendors' => $this->sectionItem('Registered vendors', $totalVendors, '/admin/vendor/list'),
            'total_stores' => $this->sectionItem('Active stores', $totalStores, '/admin/store/list'),
            'pending_withdrawals' => $this->sectionItem('Pending vendor withdrawals', $pendingWithdrawals, '/admin/vendor/withdraw_list'),
            'business_clients' => $this->sectionItem('Urban Goodz business clients', $totalBusinessClients, '/admin/urban-goodz/clients'),
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
            'captured' => $this->sectionItem('Captured payments', $captured, '/admin/urban-goodz/payments'),
            'pending' => $this->sectionItem('Pending payments', $pending, '/admin/urban-goodz/payments'),
            'failed' => $this->sectionItem('Failed / declined payments', $failed, '/admin/urban-goodz/payments'),
            'pending_splits' => $this->sectionItem('Pending payment splits', $pendingSplits, '/admin/urban-goodz/payments'),
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
            'available_loads' => $this->sectionItem('Available loads', $available, '/admin/urban-goodz/load-sourcing'),
            'assigned_loads' => $this->sectionItem('Assigned loads', $assigned, '/admin/urban-goodz/load-sourcing'),
            'in_transit' => $this->sectionItem('Loads in transit', $inTransit, '/admin/urban-goodz/load-sourcing'),
            'unassigned' => $this->sectionItem('Available but unassigned loads', $unassigned, '/admin/urban-goodz/load-sourcing'),
            'unresolved_errors' => $this->sectionItem('Unresolved source errors', $unresolvedErrors, '/admin/urban-goodz/load-sourcing/errors'),
        ];
    }

    /**
     * Sanitized AI provider health. Never exposes credentials.
     */
    public function getProviderHealth(): array
    {
        if ($this->aiService === null) {
            return [
                'configured' => false,
                'healthy' => false,
                'provider' => null,
                'model' => null,
                'error_code' => 'service_unavailable',
                'checked_at' => now()->toIso8601String(),
                'available' => false,
                'reason' => 'AI service not injected',
            ];
        }

        if (!$this->aiService->isConfigured()) {
            return [
                'configured' => false,
                'healthy' => false,
                'provider' => $this->aiService->providerName(),
                'model' => null,
                'error_code' => 'not_configured',
                'checked_at' => now()->toIso8601String(),
                'available' => true,
                'reason' => 'No AI provider credentials configured',
            ];
        }

        try {
            $health = $this->aiService->healthCheck();

            return [
                'configured' => true,
                'healthy' => $health['healthy'] ?? false,
                'provider' => $health['provider'] ?? $this->aiService->providerName(),
                'model' => $health['model'] ?? null,
                'error_code' => $health['error_code'] ?? null,
                'checked_at' => $health['checked_at'] ?? now()->toIso8601String(),
                'available' => true,
                'reason' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'healthy' => false,
                'provider' => $this->aiService->providerName(),
                'model' => null,
                'error_code' => 'health_check_exception',
                'checked_at' => now()->toIso8601String(),
                'available' => true,
                'reason' => 'Health check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Deterministic recommendations from grounded data, with optional AI-generated
     * analysis when the provider is healthy.
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        $alerts = collect($this->getOperationalAlerts());
        $criticalAlerts = $alerts->filter(fn(array $a) => ($a['count'] ?? 0) > 0 && $a['severity'] === 'high');

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

        $routeIssues = $this->getRouteAndExceptionSummary();
        $failedPkgs = $routeIssues['failed_packages']['count'] ?? null;
        if ($failedPkgs !== null && $failedPkgs > 0) {
            $recommendations[] = [
                'type' => 'deterministic',
                'source' => 'route_exceptions',
                'title' => 'Review failed package deliveries',
                'detail' => "{$failedPkgs} package(s) failed or are undeliverable.",
                'url' => '/admin/urban-goodz/routes',
                'priority' => 'medium',
            ];
        }

        $driverIssues = $this->getDriverIssueSummary();
        $expiredCerts = $driverIssues['expired_certs']['count'] ?? null;
        if ($expiredCerts !== null && $expiredCerts > 0) {
            $recommendations[] = [
                'type' => 'deterministic',
                'source' => 'driver_certifications',
                'title' => 'Renew expired driver certifications',
                'detail' => "{$expiredCerts} certification(s) have expired.",
                'url' => '/admin/delivery-man/list',
                'priority' => 'high',
            ];
        }

        $aiAnalysis = null;
        if ($this->aiService !== null && $this->aiService->isConfigured()) {
            try {
                $context = [
                    'operational_alerts' => $this->getOperationalAlerts(),
                    'route_summary' => $routeIssues,
                    'driver_summary' => $driverIssues,
                ];

                $aiResult = $this->aiService->chatResult(
                    "You are an AI chief of staff for a logistics platform. Given the operational data below, provide a prioritized list of 3-5 recommended actions. Be concise and specific. Return JSON array: [{\"title\": string, \"detail\": string, \"priority\": \"high\"|\"medium\"|\"low\"}]",
                    'Analyze the current operational state and recommend actions.',
                    $context
                );

                if ($aiResult['success'] ?? false) {
                    $parsed = json_decode($aiResult['response'] ?? '[]', true);
                    if (is_array($parsed)) {
                        $aiAnalysis = [
                            'type' => 'ai_generated',
                            'source' => 'ai_provider',
                            'provider' => $aiResult['provider'] ?? 'unknown',
                            'model' => $aiResult['model'] ?? 'unknown',
                            'items' => array_map(fn(array $item) => [
                                'type' => 'ai_generated',
                                'title' => $item['title'] ?? 'AI recommendation',
                                'detail' => $item['detail'] ?? '',
                                'priority' => $item['priority'] ?? 'low',
                            ], $parsed),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $aiAnalysis = [
                    'type' => 'ai_generated',
                    'source' => 'ai_provider',
                    'available' => false,
                    'error' => 'AI analysis failed: ' . $e->getMessage(),
                ];
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
        $summary = $this->getCommandCenterSummary();
        $diagnostics = $this->runDiagnosticScan();

        return [
            'brief' => $brief,
            'summary' => $summary,
            'diagnostics' => $diagnostics,
            'orders_fulfillment' => $this->getOrdersAndFulfillment(),
            'routes_exceptions' => $this->getRouteAndExceptionSummary(),
            'driver_issues' => $this->getDriverIssueSummary(),
            'vendor_business' => $this->getVendorAndBusinessSummary(),
            'payments_ledger' => $this->getPaymentsAndLedger(),
            'load_sourcing' => $this->getLoadSourcingStatus(),
            'provider_health' => $this->getProviderHealth(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    private function countWhen(string $table, callable $query, array $requiredColumns = []): ?int
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return null;
            }
        }

        return (int) $query();
    }

    private function alert(string $key, string $label, ?int $count, string $severity, string $url): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'severity' => $severity,
            'url' => $url,
        ];
    }

    private function sectionItem(string $label, ?int $count, string $url): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'url' => $url,
        ];
    }

    private function terminalOrderStatuses(): array
    {
        return ['delivered', 'failed', 'canceled', 'cancelled', 'refunded', 'refund_request_canceled'];
    }
}
