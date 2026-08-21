<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiApproval;
use App\Models\AiTask;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiChiefOfStaffService
{
    private ?UrbanGoodzAIService $ai;

    public function __construct(?UrbanGoodzAIService $ai = null)
    {
        $this->ai = $ai;
    }

    private function ai(): UrbanGoodzAIService
    {
        return $this->ai ??= new UrbanGoodzAIService;
    }

    /**
     * The Chief of Staff's spoken read on the current operating picture.
     *
     * Grounded strictly in the counts this service already computed. When the
     * AI provider is unavailable the brief reports itself unavailable rather
     * than narrating an executive summary nobody generated.
     *
     * @see docs/URBAN_GOODZ_AI_PERSONALITIES.md
     *
     * @return array{available: bool, text: ?string, reason: ?string, generated_at: string}
     */
    public function narrateExecutiveBrief(?string $ownerName = null): array
    {
        $unavailable = fn (string $reason): array => [
            'available' => false,
            'text' => null,
            'reason' => $reason,
            'generated_at' => now()->toIso8601String(),
        ];

        if (! $this->ai()->isConfigured()) {
            return $unavailable('provider_not_configured');
        }

        $alerts = collect($this->getOperationalAlerts())
            ->filter(fn (array $alert): bool => $alert['available'] && ($alert['count'] ?? 0) > 0)
            ->map(fn (array $alert): array => [
                'condition' => $alert['label'],
                'count' => $alert['count'],
                'severity' => $alert['severity'],
            ])
            ->values()
            ->all();

        $task = 'Deliver this morning\'s executive brief on Urban Goodz operations.

Open by greeting the owner by name if you have one. State the operating picture in
one sentence, then walk the conditions that actually need attention, worst first.
For each one give a specific recommended action. Close with what is waiting on
their approval.

If nothing needs attention, say so in one short paragraph and do not manufacture
concern. Six sentences maximum. Plain prose, no headings, no bullet lists, no
markdown.';

        $grounding = [
            'owner_name' => $ownerName,
            'date' => today()->toFormattedDateString(),
            'command_center' => $this->getCommandCenterSummary(),
            'conditions_needing_attention' => $alerts,
            'note' => $alerts === []
                ? 'No operational alert currently has a non-zero count.'
                : null,
        ];

        $result = $this->ai()->chatResultAsPersona(
            PersonaRegistry::CHIEF_OF_STAFF,
            $task,
            'Give me the brief.',
            $grounding
        );

        if (! $result['success']) {
            return $unavailable($result['error_code'] ?? 'provider_error');
        }

        return [
            'available' => true,
            'text' => $result['response'],
            'reason' => null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

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

    /**
     * Actions the Digital Human can actually execute for a given alert.
     *
     * An alert used to carry only a `url`, so the best Monique could do with
     * "3 unassigned orders" was point at /admin/order/list/all and tell the
     * owner to go do it. Naming the registry action here is what lets her
     * offer to do it instead.
     *
     * Only actions that are registered in AllowedActionRegistry AND have a
     * working executor appear. An alert with an empty list is honest: there is
     * no automated action for it yet, and she must not imply otherwise.
     * Authorization is still decided per-call by the registry against the
     * authenticated actor - listing an action here never grants it.
     */
    private const ALERT_ACTIONS = [
        'unassigned_orders' => [
            ['action' => 'assign_order', 'label' => 'Assign a courier', 'requires_confirmation' => true],
        ],
        'delayed_orders' => [
            ['action' => 'assign_order', 'label' => 'Assign a courier to move it along', 'requires_confirmation' => true],
        ],
        'failed_queue_jobs' => [
            ['action' => 'retry_queue_job', 'label' => 'Retry the failed job', 'requires_confirmation' => true],
        ],
        'out_of_stock_items' => [
            // Read-only on purpose. "Clearing" out-of-stock items must never
            // mean deleting them; the breakdown is the safe first step and
            // tells the owner which stores are driving the number.
            ['action' => 'get_out_of_stock_by_store', 'label' => 'Break the count down by store', 'requires_confirmation' => false],
        ],
    ];

    private function alert(string $key, string $label, ?int $count, string $severity, string $url): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'available' => $count !== null,
            'severity' => $severity,
            'url' => $url,
            'actions' => self::ALERT_ACTIONS[$key] ?? [],
            'actionable' => !empty(self::ALERT_ACTIONS[$key]),
        ];
    }

    private function terminalOrderStatuses(): array
    {
        return ['delivered', 'failed', 'canceled', 'cancelled', 'refunded', 'refund_request_canceled'];
    }
}
