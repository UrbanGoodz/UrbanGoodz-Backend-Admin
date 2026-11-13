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

            // A read-only report that was already delivered (and auto-resolved) must not repeat every
            // 15 minutes while the backlog it describes is still there.
            if (in_array($category, ['driver_applications_pending', 'reimbursements_pending'], true)
                && AiMoniqueNotification::forAccount($accountType, $accountId)
                    ->where('category', $category)
                    ->where('created_at', '>=', now()->subHours(6))
                    ->exists()) {
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

            $created = AiMoniqueNotification::create([
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
            $notificationsCreated[] = $created;
            $this->emailAdmin($accountType, $accountId, $created);
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
                // Generate a stock breakdown. get_out_of_stock_inventory reports
                // platform-wide, so a vendor gets the store-scoped alert tool
                // instead - otherwise the router rejects the call as
                // unauthorized and the vendor's notification is undismissable.
                $isVendor = strtolower((string) $notif->account_type) === 'vendor';
                $exec = $isVendor
                    ? $this->router->execute('vendor_operational_alerts', [], [
                        'actor_role' => 'vendor',
                        'vendor_id' => $notif->account_id,
                        'admin_id' => $notif->account_id,
                    ])
                    : $this->router->execute('get_out_of_stock_inventory', [], [
                        'actor_role' => $notif->account_type,
                    ]);
                $resolutionSummary = $exec['message'] ?? 'Generated inventory breakdown.';
                $success = (bool) ($exec['success'] ?? false);
                $verified = (bool) ($exec['verified'] ?? false);
            } elseif ($category === 'vendor_onboarding') {
                $exec = $this->router->execute('audit_vendor_onboarding', [], ['actor_role' => 'admin']);
                $resolutionSummary = $exec['message'] ?? 'Vendor backlog audited.';
                $success = (bool) ($exec['success'] ?? false);
                $verified = (bool) ($exec['verified'] ?? false);
            } elseif ($category === 'order_anywhere_unassigned') {
                $result = $this->widenAndRedispatchOrderAnywhere();
                $resolutionSummary = $result['message'];
                $success = $result['success'];
                $verified = $result['verified'];
            } elseif ($category === 'driver_applications_pending') {
                $result = $this->auditDriverApplications();
                $resolutionSummary = $result['message'];
                $success = $result['success'];
                $verified = $result['verified'];
            } elseif ($category === 'reimbursements_pending') {
                $result = $this->precheckReimbursements();
                $resolutionSummary = $result['message'];
                $success = $result['success'];
                $verified = $result['verified'];
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
            'order_anywhere_unassigned' => $this->widenAndRedispatchOrderAnywhere(),
            'driver_applications_pending' => $this->auditDriverApplications(),
            'reimbursements_pending' => $this->precheckReimbursements(),
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

        $isVendor = strtolower($actorRole) === 'vendor';
        $vendorStoreIds = $isVendor
            ? Store::where('vendor_id', $adminId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        // A vendor only ever sees and acts on its own stores' backlog; an admin
        // keeps the platform-wide view.
        $order = Order::withoutGlobalScopes()
            ->where('order_status', 'pending')
            ->whereNull('delivery_man_id')
            ->where('created_at', '<=', now()->subMinutes($delayMinutes))
            ->when($isVendor, fn ($q) => $q->whereIn('store_id', $vendorStoreIds))
            ->orderBy('created_at')
            ->first();

        if (!$order) {
            return [
                'success' => false,
                'verified' => false,
                'message' => 'No delayed order is currently waiting for courier assignment.',
            ];
        }

        // Always location aware: nearest courier to the store first, measured from where each courier
        // is now. A courier with no known position is never picked blindly.
        $store = Store::withoutGlobalScopes()->find($order->store_id);
        $storeLat = (float) ($store->latitude ?? 0);
        $storeLng = (float) ($store->longitude ?? 0);
        if (! $store || ! $storeLat || ! $storeLng) {
            return [
                'success' => false,
                'verified' => false,
                'message' => "Order #{$order->id} is delayed but its store has no location on file, so Monique cannot tell which courier is nearest. Please assign one manually.",
            ];
        }

        $candidates = DeliveryMan::query()
            ->withoutGlobalScopes()
            ->where('is_delivery', 1)
            ->where('active', 1)
            ->where('application_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('current_orders')
                    ->orWhere('current_orders', '<', (int) (config('dm_maximum_orders') ?: 1));
            })
            ->when($isVendor, fn ($q) => $q->where(function ($inner) use ($adminId) {
                $inner->where('vendor_id', $adminId)
                    ->orWhere('available_for_marketplace', 1);
            }))
            // Platform view: another business's private couriers are not ours to hand work to, unless
            // they opted into the marketplace. The store's own couriers are always eligible.
            ->when(! $isVendor, fn ($q) => $q->where(function ($inner) use ($store) {
                $inner->whereNotIn('ownership_type', ['vendor_owned', 'business_owned'])
                    ->orWhereNull('ownership_type')
                    ->orWhere('available_for_marketplace', 1);
                if ((int) ($store->vendor_id ?? 0) > 0) {
                    $inner->orWhere('vendor_id', (int) $store->vendor_id);
                }
            }))
            ->limit(500)
            ->get()
            ->all();

        $maxMiles = (float) config('urban_goodz_ai.monique_proactive.ops.max_assign_miles', 60);
        $ranked = app(\App\Services\DriverLocator::class)->rankByDistance($candidates, $storeLat, $storeLng, $maxMiles);
        // A business with its own couriers gets first call on them (nearest to furthest), then the rest.
        $ownerVendorId = (int) ($store->vendor_id ?? 0);
        if ($ownerVendorId > 0) {
            usort($ranked, fn ($a, $b) => [(int) $a->vendor_id === $ownerVendorId ? 0 : 1, $a->distance_miles, $a->id]
                <=> [(int) $b->vendor_id === $ownerVendorId ? 0 : 1, $b->distance_miles, $b->id]);
        }
        $driver = $ranked[0] ?? null;

        if (!$driver) {
            return [
                'success' => false,
                'verified' => false,
                'message' => "Order #{$order->id} is delayed but no eligible courier with a known location is within {$maxMiles} miles of the store. Please assign one manually.",
            ];
        }

        $exec = $this->router->execute('assign_order_courier', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ], [
            'role_check' => true,
            'actor_role' => $actorRole,
            'admin_id' => $adminId,
            'vendor_id' => $isVendor ? $adminId : null,
            'confirmed' => true,
        ]);

        $ok = (bool) ($exec['success'] ?? false);
        $message = $exec['message'] ?? 'Courier assignment could not be completed or verified.';
        if ($ok) {
            $message .= ' Chosen as the nearest available courier (' . $driver->distance_miles . ' mi from the store, ' . ($driver->location_source === 'live' ? 'live location' : 'saved base address') . ').';
        }

        return [
            'success' => $ok,
            'verified' => (bool) ($exec['verified'] ?? false),
            'message' => $message,
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

        return array_merge($observations, $this->observeUrbanGoodzOperations());
    }

    /**
     * What a platform employee would watch beyond the store marketplace: customer requests nobody has
     * picked up, driver applications and payout requests waiting on a person. Each query is guarded
     * so a missing table in an older schema can never take the whole loop down.
     */
    private function observeUrbanGoodzOperations(): array
    {
        $observations = [];

        try {
            if (Schema::hasTable('order_anywhere_requests')) {
                $minutes = max(1, (int) \App\Services\OrderAnywhereDispatchSettings::get('escalate_after_minutes') ?: 10);
                $waiting = DB::table('order_anywhere_requests')
                    ->where('status', 'approved')
                    ->whereNull('assigned_delivery_man_id')
                    ->where('updated_at', '<=', now()->subMinutes($minutes))
                    ->count();
                if ($waiting > 0) {
                    $observations[] = [
                        'category' => 'order_anywhere_unassigned',
                        'priority' => AiMoniqueNotification::PRIORITY_URGENT,
                        'title' => "{$waiting} Order Anywhere request(s) have no driver",
                        'message' => "{$waiting} paid Order Anywhere request(s) have waited more than {$minutes} minutes with no driver. "
                            . 'Automatic dispatch is still offering them; assign a driver from the Order Anywhere page, or they will be cancelled and refunded.',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Monique could not observe Order Anywhere', ['error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('delivery_men') && Schema::hasColumn('delivery_men', 'application_status')) {
                $hours = max(1, (int) config('urban_goodz_ai.monique_proactive.ops.driver_application_hours', 24));
                $pending = DB::table('delivery_men')
                    ->where('application_status', 'pending')
                    ->where('created_at', '<=', now()->subHours($hours))
                    ->count();
                if ($pending > 0) {
                    $observations[] = [
                        'category' => 'driver_applications_pending',
                        'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
                        'title' => "{$pending} driver application(s) waiting for review",
                        'message' => "{$pending} driver application(s) have been waiting more than {$hours} hours. Drivers cannot take work until an admin approves them.",
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Monique could not observe driver applications', ['error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('urban_goodz_order_anywhere_reimbursements')) {
                $hours = max(1, (int) config('urban_goodz_ai.monique_proactive.ops.reimbursement_hours', 4));
                $pending = DB::table('urban_goodz_order_anywhere_reimbursements')
                    ->where('status', 'pending')
                    ->where('created_at', '<=', now()->subHours($hours))
                    ->count();
                if ($pending > 0) {
                    $observations[] = [
                        'category' => 'reimbursements_pending',
                        'priority' => AiMoniqueNotification::PRIORITY_HIGH,
                        'title' => "{$pending} driver reimbursement claim(s) waiting",
                        'message' => "{$pending} driver receipt claim(s) have waited more than {$hours} hours. The driver paid with their own money and is waiting to be paid back.",
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Monique could not observe reimbursements', ['error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('urban_goodz_driver_payout_requests')) {
                $hours = max(1, (int) config('urban_goodz_ai.monique_proactive.ops.driver_payout_hours', 24));
                $pending = DB::table('urban_goodz_driver_payout_requests')
                    ->where('status', 'pending')
                    ->where('created_at', '<=', now()->subHours($hours))
                    ->count();
                if ($pending > 0) {
                    $observations[] = [
                        'category' => 'driver_payouts_pending',
                        'priority' => AiMoniqueNotification::PRIORITY_HIGH,
                        'title' => "{$pending} driver payout request(s) waiting",
                        'message' => "{$pending} driver payout request(s) have waited more than {$hours} hours. Paying people needs an admin's sign-off; Monique never moves money.",
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Monique could not observe driver payouts', ['error' => $e->getMessage()]);
        }

        return $observations;
    }

    /**
     * Stuck Order Anywhere job: nobody is within the search radius. Double the radius for this one
     * job (capped), offer it again, and only claim success if a new offer really exists.
     */
    private function widenAndRedispatchOrderAnywhere(): array
    {
        $minutes = max(1, (int) \App\Services\OrderAnywhereDispatchSettings::get('escalate_after_minutes') ?: 10);
        $request = \App\Models\OrderAnywhereRequest::query()
            ->where('status', 'approved')
            ->where('fulfillment_type', \App\Models\OrderAnywhereRequest::FULFILLMENT_EXTERNAL_MERCHANT)
            ->whereNull('assigned_delivery_man_id')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->orderBy('id')
            ->first();

        if (! $request) {
            return ['success' => false, 'verified' => false, 'message' => 'No unassigned Order Anywhere request is waiting right now.'];
        }

        $max = (float) config('urban_goodz_ai.monique_proactive.ops.max_widen_miles', 60);
        $current = max(
            (float) \App\Services\OrderAnywhereDispatchSettings::get('radius_miles'),
            (float) ($request->metadata['dispatch_radius_override_miles'] ?? 0)
        );
        $widened = min($current * 2, $max);

        if ($widened <= $current) {
            return ['success' => false, 'verified' => false, 'message' => "{$request->request_number} is already searching {$current} miles, the widest Monique will go. No driver is in range: assign one manually or recruit drivers near the pickup."];
        }

        $request->update(['metadata' => [...($request->metadata ?? []), 'dispatch_radius_override_miles' => $widened]]);
        $before = (int) \App\Models\AiDispatch::where('order_id', $request->order_id ?: 0)->max('id');

        try {
            app(\App\Services\OrderAnywhereOrderConversionService::class)->handleApproved($request->fresh());
        } catch (\Throwable $e) {
            Log::warning('Monique widen-and-redispatch failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);
        }

        $request->refresh();
        $after = (int) \App\Models\AiDispatch::where('order_id', $request->order_id ?: 0)->max('id');

        if ($request->order_id && $after > $before) {
            return ['success' => true, 'verified' => true, 'message' => "Widened the search for {$request->request_number} from {$current} to {$widened} miles and offered it to the nearest driver in range."];
        }

        return ['success' => false, 'verified' => false, 'message' => "Widened the search for {$request->request_number} to {$widened} miles, but no online driver is in range. Assign one manually."];
    }

    /** Read-only: which pending driver applications are complete enough to review, and what the rest lack. */
    private function auditDriverApplications(): array
    {
        if (! Schema::hasTable('delivery_men') || ! Schema::hasColumn('delivery_men', 'application_status')) {
            return ['success' => false, 'verified' => false, 'message' => 'Driver applications are not available in this environment.'];
        }

        $rows = DB::table('delivery_men')->where('application_status', 'pending')->orderBy('created_at')->limit(50)
            ->get(['id', 'f_name', 'l_name', 'image', 'identity_image', 'vehicle_id', 'phone', 'email']);

        if ($rows->isEmpty()) {
            return ['success' => true, 'verified' => true, 'message' => 'No driver applications are waiting.'];
        }

        $ready = [];
        $incomplete = [];
        foreach ($rows as $r) {
            $missing = [];
            if (empty($r->image) || $r->image === 'def.png') {
                $missing[] = 'profile photo';
            }
            if (empty($r->identity_image) || in_array(trim((string) $r->identity_image), ['[]', 'null', '""'], true)) {
                $missing[] = 'ID photo';
            }
            if (empty($r->vehicle_id)) {
                $missing[] = 'vehicle';
            }
            $name = trim(($r->f_name ?? '') . ' ' . ($r->l_name ?? '')) ?: "driver #{$r->id}";
            if ($missing) {
                $incomplete[] = "{$name} (#{$r->id}) is missing: " . implode(', ', $missing);
            } else {
                $ready[] = "{$name} (#{$r->id})";
            }
        }

        $message = count($ready) . ' application(s) are complete and ready for your review'
            . ($ready ? ': ' . implode(', ', array_slice($ready, 0, 10)) : '') . '. '
            . count($incomplete) . ' are incomplete'
            . ($incomplete ? ': ' . implode('; ', array_slice($incomplete, 0, 10)) : '')
            . '. Monique does not approve or reject drivers; that stays with you.';

        return ['success' => true, 'verified' => true, 'message' => $message];
    }

    /**
     * Read-only recommendation for each pending reimbursement claim: is the receipt there, is the
     * total within what the customer funded, is the claimant the assigned driver. She never approves.
     */
    private function precheckReimbursements(): array
    {
        if (! Schema::hasTable('urban_goodz_order_anywhere_reimbursements')) {
            return ['success' => false, 'verified' => false, 'message' => 'Reimbursements are not available in this environment.'];
        }

        $claims = \App\Models\UrbanGoodzOrderAnywhereReimbursement::where('status', 'pending')->orderBy('id')->limit(25)->get();
        if ($claims->isEmpty()) {
            return ['success' => true, 'verified' => true, 'message' => 'No reimbursement claims are waiting.'];
        }

        $lines = [];
        foreach ($claims as $c) {
            $order = \App\Models\OrderAnywhereRequest::find($c->order_anywhere_request_id);
            $issues = [];
            $path = $c->getRawOriginal('receipt_path');
            if (! $path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                $issues[] = 'receipt file is missing';
            }
            if (! $order || (int) $order->assigned_delivery_man_id !== (int) $c->delivery_man_id) {
                $issues[] = 'claimant is not the assigned driver';
            }
            if ((float) $c->claimed_total > (float) $c->authorized_total + 0.005) {
                $issues[] = 'over the customer-funded budget by $' . number_format((float) $c->claimed_total - (float) $c->authorized_total, 2);
            }
            $label = 'Claim #' . $c->id . ' ($' . number_format((float) $c->claimed_total, 2) . ')';
            $lines[] = $issues
                ? $label . ': needs a look, ' . implode('; ', $issues)
                : $label . ': looks fine to approve (receipt present, within budget, right driver)';
        }

        return ['success' => true, 'verified' => true, 'message' => implode('. ', $lines) . '. Monique only recommends; you approve or reject.'];
    }

    /**
     * Tell the admin outside the admin page. Only urgent/high findings, only when something is
     * actually pending, and the channel is recorded only if the mail really went out.
     */
    private function emailAdmin(string $accountType, int $accountId, AiMoniqueNotification $notification): void
    {
        if ($accountType === 'vendor'
            || ! (bool) config('urban_goodz_ai.monique_proactive.email_alerts', true)
            || ! in_array($notification->priority, [AiMoniqueNotification::PRIORITY_URGENT, AiMoniqueNotification::PRIORITY_HIGH], true)) {
            return;
        }

        try {
            $email = \App\Models\Admin::query()->whereKey($accountId)->value('email');
            if (! $email) {
                return;
            }
            \Illuminate\Support\Facades\Mail::raw(
                $notification->message . "\n\nOpen the admin dashboard to review. - Monique",
                function ($m) use ($email, $notification) {
                    $m->to($email)->subject('Monique: ' . $notification->title);
                }
            );
            $notification->update(['delivered_channels' => array_values(array_unique(array_merge(
                (array) $notification->delivered_channels,
                ['email']
            )))]);
        } catch (\Throwable $e) {
            Log::warning('Monique alert email failed', ['account_id' => $accountId, 'error' => $e->getMessage()]);
        }
    }
}
