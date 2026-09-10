<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiMoniqueNotification;
use App\Models\DeliveryMan;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\UrbanGoodz\AiChiefOfStaffService;
use App\Services\UrbanGoodz\VendorAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MoniqueProactiveAttentionService
{
    public function __construct(
        private readonly ExecutionRouter $router,
        private readonly MoniqueTrialValueTracker $valueTracker,
        private readonly ?AiChiefOfStaffService $chiefOfStaff = null,
        private readonly ?VendorAIService $vendorAI = null
    ) {}

    /**
     * Complete Employee Operating Loop:
     * OBSERVE -> UNDERSTAND -> PRIORITIZE -> PLAN -> ACT -> VERIFY -> DOCUMENT -> REPORT
     */
    public function observeAndAct(string $accountType, int $accountId, ?int $storeId = null): array
    {
        $observations = ($accountType === 'vendor')
            ? $this->observeVendor($accountId)
            : $this->observeBusinessPortal($accountId);

        $notificationsCreated = [];
        $autoResolvedCount = 0;

        foreach ($observations as $obs) {
            $category = $obs['category'];

            // Category disabled by policy: do not even flag it.
            if (!$this->categoryEnabled($category)) {
                $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_ISSUES_IDENTIFIED);
                continue;
            }

            // Check if active pending notification already exists to avoid spamming
            $existing = AiMoniqueNotification::forAccount($accountType, $accountId)
                ->where('category', $category)
                ->where('status', AiMoniqueNotification::STATUS_PENDING)
                ->where('created_at', '>=', now()->subHours(6))
                ->first();

            if ($existing) {
                continue;
            }

            $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_ISSUES_IDENTIFIED);

            // Category policy decides whether Monique acts now, asks first, or
            // just flags it for a person. auto_run failures escalate for a
            // human exactly like a plain "ask" notification -- never silence.
            if ($this->actionMode($category) === 'auto_run') {
                $auto = $this->autoRun($obs, $accountType, $accountId);

                if ($auto && $auto['success'] && $auto['verified']) {
                    $autoResolvedCount++;
                    $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_ISSUES_RESOLVED);
                    $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_TASKS_COMPLETED);
                    $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_VERIFIED_ACTIONS);

                    $notificationsCreated[] = AiMoniqueNotification::create([
                        'account_type' => $accountType,
                        'account_id' => $accountId,
                        'store_id' => $storeId,
                        'category' => $category,
                        'priority' => $obs['priority'],
                        'title' => $obs['title'],
                        'message' => $obs['message'] . " [Monique has already resolved this automatically.]",
                        'is_actionable' => false,
                        'can_auto_resolve' => true,
                        'auto_resolved' => true,
                        'status' => AiMoniqueNotification::STATUS_RESOLVED,
                        'resolution_summary' => $auto['message'] ?? 'Resolved automatically by Monique.',
                        'delivered_channels' => ['in_app'],
                    ]);

                    continue;
                }
            }

            // Requires owner decision / approval
            $this->valueTracker->recordMetric($accountType, $accountId, MoniqueTrialValueTracker::METRIC_ISSUES_ESCALATED);

            $notificationsCreated[] = AiMoniqueNotification::create([
                'account_type' => $accountType,
                'account_id' => $accountId,
                'store_id' => $storeId,
                'category' => $category,
                'priority' => $obs['priority'],
                'title' => $obs['title'],
                'message' => $obs['message'],
                'actions' => $obs['actions'] ?? $this->actionButtonsFor($category),
                'is_actionable' => true,
                'can_auto_resolve' => false,
                'auto_resolved' => false,
                'status' => AiMoniqueNotification::STATUS_PENDING,
                'delivered_channels' => ['in_app', 'notification_center'],
            ]);
        }

        return [
            'account_type' => $accountType,
            'account_id' => $accountId,
            'observations_total' => count($observations),
            'auto_resolved_count' => $autoResolvedCount,
            'notifications_created' => count($notificationsCreated),
            'notifications' => $notificationsCreated,
        ];
    }

    /**
     * Handle user action on a proactive notification (e.g. 'Let Monique Handle It').
     */
    public function handleNotificationAction(int $notificationId, string $action, array $context = []): array
    {
        $notif = AiMoniqueNotification::find($notificationId);
        if (!$notif) {
            return ['success' => false, 'message' => 'Notification not found.'];
        }

        if ($action === 'dismiss') {
            $notif->markAsDismissed();
            return ['success' => true, 'message' => 'Notification dismissed.'];
        }

        if ($action === 'let_monique_handle_it') {
            $category = $notif->category;
            $resolutionSummary = '';
            $success = false;
            $verified = false;

            // Execute the appropriate action based on category. Every branch
            // reports honest results: a task is "handled" only when the
            // underlying tool ran and was verified against the database.
            if ($category === 'delayed_orders') {
                // Real work: assign the newest-available courier to the
                // oldest delayed order, then verify the driver write stuck.
                $assign = $this->assignCourierToOldestDelayedOrder($notif->account_type, $notif->account_id);
                $resolutionSummary = $assign['message'];
                $success = $assign['success'];
                $verified = $assign['verified'];
            } elseif ($category === 'out_of_stock' || $category === 'low_inventory') {
                // Generate stock breakdown report
                $exec = $this->router->execute('get_out_of_stock_inventory', [], ['actor_role' => $notif->account_type]);
                $resolutionSummary = $exec['message'] ?? 'Generated inventory breakdown.';
                $success = (bool) ($exec['success'] ?? false);
                $verified = (bool) ($exec['verified'] ?? false);
            } elseif ($category === 'vendor_onboarding') {
                $exec = $this->router->execute('audit_vendor_onboarding', [], ['actor_role' => 'admin']);
                $resolutionSummary = $exec['message'] ?? 'Vendor backlog audited.';
                $success = (bool) ($exec['success'] ?? false);
                $verified = (bool) ($exec['verified'] ?? false);
            } elseif ($category === 'failed_queue_jobs') {
                // Manual "handle it" drains every failed job (owner explicitly
                // asked), retrying the oldest first through the verified router.
                $result = $this->retryFailedQueueJobs(null);
                $resolutionSummary = $result['message'];
                $success = $result['success'];
                $verified = $result['verified'];
            } else {
                // No automated action exists yet. Never claim completion for
                // a category Monique cannot actually resolve.
                return [
                    'success' => false,
                    'verified' => false,
                    'notification_id' => $notif->id,
                    'resolution_summary' => "There is no automated action for '{$category}' yet, so this still needs a person.",
                    'message' => "There is no automated action for '{$category}' yet, so Monique left it for you to handle.",
                ];
            }

            if ($success) {
                $notif->markAsResolved($resolutionSummary);
                $this->valueTracker->recordMetric($notif->account_type, $notif->account_id, MoniqueTrialValueTracker::METRIC_ISSUES_RESOLVED);
                $this->valueTracker->recordMetric($notif->account_type, $notif->account_id, MoniqueTrialValueTracker::METRIC_TASKS_COMPLETED);
                if ($verified) {
                    $this->valueTracker->recordMetric($notif->account_type, $notif->account_id, MoniqueTrialValueTracker::METRIC_VERIFIED_ACTIONS);
                }
            }

            return [
                'success' => $success,
                'verified' => $verified,
                'notification_id' => $notif->id,
                'resolution_summary' => $resolutionSummary,
                'message' => "Monique has handled this task: {$resolutionSummary}",
            ];
        }

        return ['success' => false, 'message' => "Unknown action '{$action}'."];
    }

    /**
     * Config-backed operating policy for a category. Merged over sensible
     * defaults so a partial config still resolves to 'ask' + enabled.
     */
    private function categoryPolicy(string $category): array
    {
        $defaults = [
            'enabled' => true,
            'action' => 'ask',
            'max_auto_assign_per_cycle' => 1,
            'max_auto_retry_per_cycle' => 1,
        ];

        $stored = (array) config('urban_goodz_ai.monique_proactive.categories.' . $category, []);

        return array_merge($defaults, $stored);
    }

    private function categoryEnabled(string $category): bool
    {
        return (bool) ($this->categoryPolicy($category)['enabled'] ?? true);
    }

    private function actionMode(string $category): string
    {
        return (string) ($this->categoryPolicy($category)['action'] ?? 'ask');
    }

    /**
     * Action buttons offered on an escalated notification. observe_only
     * categories have no "Let Monique Handle It" button because there is no
     * write Monique is allowed to make for them.
     */
    private function actionButtonsFor(string $category): array
    {
        $buttons = [];

        if ($this->actionMode($category) !== 'observe_only') {
            $buttons[] = ['label' => 'Let Monique Handle It', 'action' => 'let_monique_handle_it'];
        }

        $buttons[] = ['label' => 'Review', 'action' => 'review'];
        $buttons[] = ['label' => 'Dismiss', 'action' => 'dismiss'];

        return $buttons;
    }

    /**
     * Run the real, verifiable action for a category whose policy is auto_run.
     * Returns null when no autonomous action exists (the caller then escalates).
     *
     * @return array{success: bool, verified: bool, message: string}|null
     */
    private function autoRun(array $obs, string $accountType, int $accountId): ?array
    {
        $category = $obs['category'];
        $policy = $this->categoryPolicy($category);

        return match ($category) {
            'delayed_orders' => $this->autoAssignDelayedOrders(
                (int) ($policy['max_auto_assign_per_cycle'] ?? 1),
                $accountType,
                $accountId
            ),
            'failed_queue_jobs' => $this->retryFailedQueueJobs(
                (int) ($policy['max_auto_retry_per_cycle'] ?? 1)
            ),
            default => null,
        };
    }

    /**
     * auto_run branch for delayed orders: assign up to $max orders per cycle,
     * each assignment verified against the database before Monique claims it.
     */
    private function autoAssignDelayedOrders(int $max, string $actorRole, int $adminId): array
    {
        if ($max < 1) {
            return ['success' => false, 'verified' => false, 'message' => 'Auto-assignment is disabled by policy.'];
        }

        $assigned = [];
        $failed = false;
        $lastMessage = null;

        for ($i = 0; $i < $max; $i++) {
            $result = $this->assignCourierToOldestDelayedOrder($actorRole, $adminId);
            $lastMessage = $result['message'];

            if (!$result['success'] || !$result['verified']) {
                if ($i === 0) {
                    return $result;
                }
                $failed = true;
                break;
            }

            $assigned[] = $result;
        }

        if (empty($assigned)) {
            return ['success' => false, 'verified' => false, 'message' => $lastMessage ?? 'No delayed order could be auto-assigned.'];
        }

        $count = count($assigned);
        $detail = $count > 1 ? "{$count} orders assigned to couriers." : ($assigned[0]['message'] ?? "1 order assigned to a courier.");

        return [
            'success' => !$failed,
            'verified' => true,
            'message' => $failed ? "{$detail} Further assignment stopped: {$lastMessage}" : $detail,
        ];
    }

    /**
     * retry_failed_queue_job for the oldest $max failed jobs (or all when null,
     * used for a manual "handle it"). Every retry goes through the verified
     * router and is independently confirmed against the job store.
     */
    private function retryFailedQueueJobs(?int $max): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['success' => false, 'verified' => false, 'message' => 'No failed-jobs table is deployed.'];
        }

        $query = DB::table('failed_jobs')->orderBy('id');
        if ($max !== null) {
            $query->limit($max);
        }

        $jobs = $query->get(['id', 'uuid']);

        if ($jobs->isEmpty()) {
            return ['success' => true, 'verified' => true, 'message' => 'No failed queue jobs found - all clear.'];
        }

        $retried = 0;
        $firstError = null;

        foreach ($jobs as $job) {
            $exec = $this->router->execute('retry_failed_queue_job', ['job_uuid' => $job->uuid], [
                'role_check' => true,
                'actor_role' => 'admin',
                'confirmed' => true,
            ]);

            if (($exec['success'] ?? false) && ($exec['verified'] ?? false)) {
                $retried++;
            } else {
                $firstError = $exec['message'] ?? 'Queue job retry failed or could not be verified.';
                break;
            }
        }

        $total = $jobs->count();

        if ($retried === 0) {
            return [
                'success' => false,
                'verified' => false,
                'message' => $firstError ?? "{$total} failed job(s) could not be retried.",
            ];
        }

        return [
            'success' => $retried === $total,
            'verified' => true,
            'message' => $retried === $total
                ? "Retried and verified {$total} failed queue job(s)."
                : "Retried and verified {$retried} of {$total} failed queue job(s); {$firstError}",
        ];
    }

    /**
     * Real automated resolution for delayed orders: pick the oldest unassigned
     * delayed order, pick the least-loaded eligible courier, assign, and
     * verify the driver write against the database. Nothing here pretends a
     * change happened -- the router either verified it or we report failure.
     */
    private function assignCourierToOldestDelayedOrder(string $actorRole, int $adminId): array
    {
        $delayMinutes = (int) config('urban_goodz_ai.monique_proactive.delayed_order_minutes', 30);

        $order = Order::withoutGlobalScopes()
            ->where('order_status', 'pending')
            ->whereNull('delivery_man_id')
            ->where('created_at', '<=', now()->subMinutes($delayMinutes))
            ->orderBy('created_at')
            ->first();

        if (!$order) {
            return [
                'success' => false,
                'verified' => false,
                'message' => 'No delayed order is currently waiting for courier assignment.',
            ];
        }

        $driver = DeliveryMan::query()
            ->withoutGlobalScopes()
            ->where('is_delivery', 1)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('current_orders')
                    ->orWhere('current_orders', '<', (int) (config('dm_maximum_orders') ?: 1));
            })
            ->orderBy('current_orders')
            ->orderBy('id')
            ->first();

        if (!$driver) {
            return [
                'success' => false,
                'verified' => false,
                'message' => "Order #{$order->id} is delayed but no eligible courier is available right now. Dispatch priority was raised; please assign one manually.",
            ];
        }

        $exec = $this->router->execute('assign_order_courier', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ], [
            'role_check' => true,
            'actor_role' => $actorRole,
            'admin_id' => $adminId,
            'confirmed' => true,
        ]);

        return [
            'success' => (bool) ($exec['success'] ?? false),
            'verified' => (bool) ($exec['verified'] ?? false),
            'message' => $exec['message'] ?? 'Courier assignment could not be completed or verified.',
        ];
    }

    /**
     * Generate the morning Chief of Staff brief for an account.
     */
    public function getMorningBrief(string $accountType, int $accountId): array
    {
        $this->observeAndAct($accountType, $accountId);

        $pending = AiMoniqueNotification::forAccount($accountType, $accountId)
            ->pending()
            ->latest('id')
            ->limit(5)
            ->get();

        $resolvedToday = AiMoniqueNotification::forAccount($accountType, $accountId)
            ->where('status', AiMoniqueNotification::STATUS_RESOLVED)
            ->where('updated_at', '>=', now()->startOfDay())
            ->count();

        $bulletPoints = [];
        foreach ($pending as $n) {
            $bulletPoints[] = "• [{$n->priority}] {$n->title}: {$n->message}";
        }

        if ($resolvedToday > 0) {
            $bulletPoints[] = "• I completed {$resolvedToday} routine operational tasks overnight.";
        }

        $headline = $pending->isEmpty()
            ? "Good morning. Everything is running smoothly. I have reviewed all operations and there are no critical issues requiring your intervention."
            : "Good morning. Here is what needs your attention today:";

        return [
            'headline' => $headline,
            'bullet_points' => $bulletPoints,
            'pending_attention_count' => $pending->count(),
            'resolved_today_count' => $resolvedToday,
            'notifications' => $pending->toArray(),
        ];
    }

    /**
     * Proactive observer for Vendor App.
     */
    private function observeVendor(int $vendorId): array
    {
        $storeIds = Store::where('vendor_id', $vendorId)->pluck('id');
        $observations = [];

        $policy = (array) config('urban_goodz_ai.monique_proactive.vendor', []);
        $waitMinutes = (int) ($policy['waiting_order_minutes'] ?? 15);
        $urgentMinutes = (int) ($policy['waiting_order_urgent_minutes'] ?? 30);
        $outOfStockMin = (int) ($policy['out_of_stock_min_items'] ?? 1);
        $outOfStockEnabled = (bool) ($policy['out_of_stock_enabled'] ?? true);

        // 1. Orders monitored & Orders needing attention
        $totalOrders = Order::withoutGlobalScopes()->whereIn('store_id', $storeIds)->count();
        $this->valueTracker->recordMetric('vendor', $vendorId, MoniqueTrialValueTracker::METRIC_ORDERS_MONITORED, max(1, $totalOrders));

        $waitingOrders = Order::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->where('order_status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($waitMinutes))
            ->get();

        if ($waitingOrders->count() > 0) {
            $minCreated = $waitingOrders->min('created_at');
            $oldestMinutes = $minCreated ? (int) Carbon::parse($minCreated)->diffInMinutes(now()) : 0;
            $observations[] = [
                'category' => 'delayed_orders',
                'priority' => ($oldestMinutes >= $urgentMinutes) ? AiMoniqueNotification::PRIORITY_URGENT : AiMoniqueNotification::PRIORITY_HIGH,
                'title' => "{$waitingOrders->count()} orders require urgent attention",
                'message' => "You have {$waitingOrders->count()} order(s) waiting for acceptance. The longest has been waiting for {$oldestMinutes} minutes.",
            ];
        }

        // 2. Out of stock or low inventory
        $outOfStockItems = Item::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->where('status', 1)
            ->where('stock', '<=', 0)
            ->get(['id', 'name']);

        if ($outOfStockEnabled && $outOfStockItems->count() >= $outOfStockMin) {
            $names = $outOfStockItems->take(2)->pluck('name')->implode(', ');
            $observations[] = [
                'category' => 'out_of_stock',
                'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
                'title' => "{$outOfStockItems->count()} products are currently out of stock",
                'message' => "Products including {$names} are marked out of stock. Customers cannot order these items.",
                'action_params' => ['item_ids' => $outOfStockItems->pluck('id')->toArray()],
            ];
        }

        return $observations;
    }

    /**
     * Proactive observer for Business Portal. Optional module tables (queue
     * jobs, load sourcing, withdrawals) are guarded so a missing table never
     * crashes the scheduled proactive loop.
     */
    private function observeBusinessPortal(int $adminId): array
    {
        $observations = [];

        $delayMinutes = (int) config('urban_goodz_ai.monique_proactive.delayed_order_minutes', 30);
        $urgentMinutes = (int) config('urban_goodz_ai.monique_proactive.delayed_order_urgent_minutes', 60);
        $outOfStockMin = (int) config('urban_goodz_ai.monique_proactive.out_of_stock_min_items', 6);
        $withdrawalsEnabled = (bool) config('urban_goodz_ai.monique_proactive.categories.pending_withdrawals.enabled', true);

        // 1. Unassigned / delayed orders across platform
        $oldestDelayed = Order::withoutGlobalScopes()
            ->where('order_status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($delayMinutes))
            ->select('id', 'created_at')
            ->orderBy('created_at')
            ->first();

        $totalActiveOrders = Order::withoutGlobalScopes()->whereIn('order_status', ['pending', 'confirmed', 'processing'])->count();
        $this->valueTracker->recordMetric('admin', $adminId, MoniqueTrialValueTracker::METRIC_ORDERS_MONITORED, max(1, $totalActiveOrders));

        if ($oldestDelayed) {
            $oldestMinutes = (int) Carbon::parse($oldestDelayed->created_at)->diffInMinutes(now());
            $delayedOrders = Order::withoutGlobalScopes()
                ->where('order_status', 'pending')
                ->where('created_at', '<=', now()->subMinutes($delayMinutes))
                ->count();

            $observations[] = [
                'category' => 'delayed_orders',
                'priority' => ($oldestMinutes >= $urgentMinutes) ? AiMoniqueNotification::PRIORITY_URGENT : AiMoniqueNotification::PRIORITY_HIGH,
                'title' => "{$delayedOrders} platform orders delayed",
                'message' => "{$delayedOrders} orders have remained in pending status for longer than {$delayMinutes} minutes.",
            ];
        }

        // 2. Failed background queue jobs (optional table)
        if (Schema::hasTable('failed_jobs')) {
            $failedJobCount = DB::table('failed_jobs')->count();

            if ($failedJobCount > 0) {
                $observations[] = [
                    'category' => 'failed_queue_jobs',
                    'priority' => AiMoniqueNotification::PRIORITY_HIGH,
                    'title' => "{$failedJobCount} failed background jobs detected",
                    'message' => "Laravel queue worker has {$failedJobCount} failed jobs requiring retry.",
                ];
            }
        }

        // 3. Platform out of stock breakdown
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'stock')) {
            $outOfStockCount = DB::table('items')->where('status', 1)->where('stock', '<=', 0)->count();
            if ($outOfStockCount >= $outOfStockMin) {
                $observations[] = [
                    'category' => 'out_of_stock',
                    'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
                    'title' => "{$outOfStockCount} items currently out of stock across stores",
                    'message' => "Several vendor stores have active products with zero stock.",
                ];
            }
        }

        // 4. Pending withdrawals awaiting approval (optional table). Funds
        // movement always requires the owner's explicit sign-off -- there is no
        // autonomous approval tool, and Monique must not imply she approved one.
        if ($withdrawalsEnabled && Schema::hasTable('withdraw_requests') && Schema::hasColumn('withdraw_requests', 'approved')) {
            $pendingWithdrawals = DB::table('withdraw_requests')->where('approved', 0)->count();
            if ($pendingWithdrawals > 0) {
                $observations[] = [
                    'category' => 'pending_withdrawals',
                    'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
                    'title' => "{$pendingWithdrawals} withdrawal request(s) awaiting approval",
                    'message' => "Owners must approve {$pendingWithdrawals} withdrawal request(s) before funds are released.",
                ];
            }
        }

        return $observations;
    }
}
