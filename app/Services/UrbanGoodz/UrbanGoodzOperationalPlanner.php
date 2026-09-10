<?php

namespace App\Services\UrbanGoodz;

use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a broad operational request ("clear all of that") into a concrete,
 * bounded plan of individual registered actions.
 *
 * Everything the Digital Human can do is a single narrow action -
 * assign_order, retry_queue_job and so on. That is deliberate: there is no
 * clearAllTheThings() with sweeping powers, because a single action with a
 * vague name is exactly how a bulk mistake happens. What was missing was
 * something to decompose one broad request into several of those narrow
 * actions.
 *
 * Two rules shape this class:
 *
 * 1. It never invents an action. Every step names a registered action, and
 *    execution still goes through UrbanGoodzAIExecutionService, which
 *    re-checks authorization and verifies the result. Planning is not
 *    permission.
 *
 * 2. It plans only what it can do safely and says so about the rest. A
 *    request that touches 513 out-of-stock rows produces a breakdown step,
 *    never 513 mutations. Steps that cannot be automated are returned as
 *    `unplannable` with a reason, so the Digital Human reports the limit
 *    instead of quietly narrowing the request.
 */
class UrbanGoodzOperationalPlanner
{
    /**
     * Hard ceiling on mutations in one plan.
     *
     * A plan is a batch executed on one confirmation, so the blast radius has
     * to be bounded by something other than the model's judgement. Anything
     * beyond this is reported as remaining work rather than silently dropped.
     */
    public const MAX_ACTIONS_PER_PLAN = 25;

    public function __construct(
        private readonly AiChiefOfStaffService $chiefOfStaff,
    ) {}

    /**
     * Builds a plan for the current operational alerts.
     *
     * @param  list<string>|null  $onlyAlertKeys  Restrict to specific alerts.
     * @return array{steps: list<array<string,mixed>>, unplannable: list<array<string,mixed>>, summary: array<string,mixed>}
     */
    public function plan(?array $onlyAlertKeys = null): array
    {
        $alerts = collect($this->chiefOfStaff->getOperationalAlerts())
            ->filter(fn ($a) => ($a['available'] ?? false) && ($a['count'] ?? 0) > 0)
            ->when($onlyAlertKeys !== null, fn ($c) => $c->filter(
                fn ($a) => in_array($a['key'], $onlyAlertKeys, true)
            ));

        $steps = [];
        $unplannable = [];

        foreach ($alerts as $alert) {
            $key = $alert['key'];

            if (empty($alert['actions'])) {
                $unplannable[] = [
                    'alert' => $key,
                    'label' => $alert['label'],
                    'count' => $alert['count'],
                    'reason' => 'No automated action exists for this yet; it needs a person.',
                ];
                continue;
            }

            $planned = match ($key) {
                'unassigned_orders', 'delayed_orders' => $this->planOrderAssignments($key, $alert),
                'failed_queue_jobs' => $this->planQueueRetries($alert),
                'out_of_stock_items' => $this->planInventoryBreakdown($alert),
                default => ['steps' => [], 'unplannable' => [[
                    'alert' => $key,
                    'label' => $alert['label'],
                    'count' => $alert['count'],
                    'reason' => 'No planner is defined for this alert.',
                ]]],
            };

            $steps = array_merge($steps, $planned['steps']);
            $unplannable = array_merge($unplannable, $planned['unplannable']);
        }

        // Reads first: they are free and inform the mutations that follow.
        usort($steps, fn ($a, $b) => ($a['mutates'] ? 1 : 0) <=> ($b['mutates'] ? 1 : 0));

        $capped = array_slice($steps, 0, self::MAX_ACTIONS_PER_PLAN);
        if (count($steps) > self::MAX_ACTIONS_PER_PLAN) {
            $unplannable[] = [
                'alert' => 'plan_limit',
                'label' => 'Deferred to a later batch',
                'count' => count($steps) - self::MAX_ACTIONS_PER_PLAN,
                'reason' => 'A single plan is capped at ' . self::MAX_ACTIONS_PER_PLAN
                    . ' actions. Run the request again to continue.',
            ];
        }

        return [
            'steps' => $capped,
            'unplannable' => $unplannable,
            'summary' => [
                'total_steps' => count($capped),
                'mutating_steps' => count(array_filter($capped, fn ($s) => $s['mutates'])),
                'requires_confirmation' => (bool) array_filter($capped, fn ($s) => $s['requires_confirmation']),
                'unplannable_count' => count($unplannable),
            ],
        ];
    }

    /**
     * One assignment step per order, each naming a specific eligible courier.
     *
     * Couriers are matched to orders one-to-one rather than everyone being
     * handed the same "best" driver, and the pool respects the same
     * active/available scopes the assignment path itself enforces - so the
     * plan does not promise assignments the backend will then reject.
     *
     * @return array{steps: list<array<string,mixed>>, unplannable: list<array<string,mixed>>}
     */
    private function planOrderAssignments(string $alertKey, array $alert): array
    {
        if (!Schema::hasTable('orders')) {
            return ['steps' => [], 'unplannable' => []];
        }

        try {
            $query = DB::table('orders')
                ->whereNull('delivery_man_id')
                ->whereNotIn('order_status', $this->terminalOrderStatuses());

            if ($alertKey === 'delayed_orders') {
                $query->where('created_at', '<', now()->subHours(2));
            }

            $orders = $query->orderBy('created_at')
                ->limit(self::MAX_ACTIONS_PER_PLAN)
                ->pluck('id');

            if ($orders->isEmpty()) {
                return ['steps' => [], 'unplannable' => []];
            }

            $couriers = DB::table('delivery_men')
                ->where('active', 1)
                ->where('application_status', 'approved')
                ->where('current_orders', '<', config('dm_maximum_orders') ?? 1)
                ->orderBy('current_orders')
                ->limit($orders->count())
                ->pluck('id');

            $steps = [];
            $unassignable = 0;

            foreach ($orders as $i => $orderId) {
                $courierId = $couriers[$i] ?? null;
                if ($courierId === null) {
                    $unassignable++;
                    continue;
                }

                $steps[] = [
                    'action' => 'assign_order',
                    'module' => 'delivery',
                    'label' => "Assign order {$orderId} to courier {$courierId}",
                    'params' => [
                        '_routed_action' => 'assign_order',
                        'order_id' => $orderId,
                        'driver_id' => $courierId,
                    ],
                    'mutates' => true,
                    'requires_confirmation' => true,
                    'alert' => $alertKey,
                ];
            }

            $unplannable = [];
            if ($unassignable > 0) {
                $unplannable[] = [
                    'alert' => $alertKey,
                    'label' => 'Orders with no eligible courier',
                    'count' => $unassignable,
                    'reason' => 'No active courier is under the maximum order limit, so these need dispatch.',
                ];
            }

            return ['steps' => $steps, 'unplannable' => $unplannable];

        } catch (\Throwable $e) {
            Log::warning('Operational planner: order assignment planning failed', ['exception' => $e::class]);
            return ['steps' => [], 'unplannable' => [[
                'alert' => $alertKey,
                'label' => $alert['label'],
                'count' => $alert['count'],
                'reason' => 'The order query failed, so no assignments were planned.',
            ]]];
        }
    }

    /**
     * @return array{steps: list<array<string,mixed>>, unplannable: list<array<string,mixed>>}
     */
    private function planQueueRetries(array $alert): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['steps' => [], 'unplannable' => []];
        }

        try {
            $jobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(self::MAX_ACTIONS_PER_PLAN)
                ->pluck('uuid');

            $steps = [];
            foreach ($jobs as $uuid) {
                $steps[] = [
                    'action' => 'retry_queue_job',
                    'module' => 'operations',
                    'label' => "Retry failed job {$uuid}",
                    'params' => [
                        '_routed_action' => 'retry_queue_job',
                        'job_uuid' => $uuid,
                    ],
                    'mutates' => true,
                    'requires_confirmation' => true,
                    'alert' => 'failed_queue_jobs',
                ];
            }

            return ['steps' => $steps, 'unplannable' => []];

        } catch (\Throwable $e) {
            Log::warning('Operational planner: queue retry planning failed', ['exception' => $e::class]);
            return ['steps' => [], 'unplannable' => [[
                'alert' => 'failed_queue_jobs',
                'label' => $alert['label'],
                'count' => $alert['count'],
                'reason' => 'The failed job query failed, so no retries were planned.',
            ]]];
        }
    }

    /**
     * Out-of-stock is a read, never a bulk mutation.
     *
     * @return array{steps: list<array<string,mixed>>, unplannable: list<array<string,mixed>>}
     */
    private function planInventoryBreakdown(array $alert): array
    {
        return [
            'steps' => [[
                'action' => 'get_out_of_stock_by_store',
                'module' => 'operations',
                'label' => 'Break the out-of-stock items down by store',
                'params' => ['_routed_action' => 'get_out_of_stock_by_store'],
                'mutates' => false,
                'requires_confirmation' => false,
                'alert' => 'out_of_stock_items',
            ]],
            'unplannable' => [[
                'alert' => 'out_of_stock_items',
                'label' => 'Restocking the out-of-stock items',
                'count' => $alert['count'],
                'reason' => 'Restocking is a vendor and catalogue workflow. These items are never deleted to clear the count.',
            ]],
        ];
    }

    private function terminalOrderStatuses(): array
    {
        return ['delivered', 'failed', 'canceled', 'cancelled', 'refunded', 'refund_request_canceled'];
    }
}
