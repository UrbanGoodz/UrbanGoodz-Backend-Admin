<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiMoniqueSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoniqueTrialValueTracker
{
    public const METRIC_TASKS_COMPLETED = 'tasks_completed';
    public const METRIC_ORDERS_MONITORED = 'orders_monitored';
    public const METRIC_ISSUES_IDENTIFIED = 'issues_identified';
    public const METRIC_ISSUES_RESOLVED = 'issues_resolved';
    public const METRIC_REPORTS_PREPARED = 'reports_prepared';
    public const METRIC_ISSUES_ESCALATED = 'issues_escalated';
    public const METRIC_VERIFIED_ACTIONS = 'verified_actions_taken';

    public function __construct(
        private readonly MoniqueEntitlementService $entitlementService
    ) {}

    /**
     * Increment a tracked value metric for this account's trial.
     */
    public function recordMetric(string $accountType, int $accountId, string $metric, int $amount = 1): void
    {
        try {
            $sub = $this->entitlementService->getOrCreateSubscription($accountType, $accountId);
            $metadata = $sub->metadata ?? [];
            $metrics = $metadata['value_metrics'] ?? [
                'tasks_completed' => 0,
                'orders_monitored' => 0,
                'issues_identified' => 0,
                'issues_resolved' => 0,
                'reports_prepared' => 0,
                'issues_escalated' => 0,
                'verified_actions_taken' => 0,
                'false_completions_prevented' => 0,
            ];

            $metrics[$metric] = ($metrics[$metric] ?? 0) + $amount;
            $metadata['value_metrics'] = $metrics;

            $sub->metadata = $metadata;
            $sub->save();
        } catch (\Throwable $e) {
            Log::warning("Could not record Monique trial metric [{$metric}]: " . $e->getMessage());
        }
    }

    /**
     * Get the full trial value dashboard metrics.
     */
    public function getTrialDashboard(string $accountType, int $accountId): array
    {
        $sub = $this->entitlementService->getOrCreateSubscription($accountType, $accountId);
        $entitlement = $this->entitlementService->checkEntitlement($accountType, $accountId);

        $metadata = $sub->metadata ?? [];
        $metrics = $metadata['value_metrics'] ?? [
            'tasks_completed' => 0,
            'orders_monitored' => 0,
            'issues_identified' => 0,
            'issues_resolved' => 0,
            'reports_prepared' => 0,
            'issues_escalated' => 0,
            'verified_actions_taken' => 0,
            'false_completions_prevented' => 0,
        ];

        $trialStart = $sub->trial_start_at ?? now();
        $daysActive = max(1, (int) $trialStart->diffInDays(now()));

        return [
            'headline' => "Monique's First 30 Days",
            'subheading' => "Your AI Employee working alongside you.",
            'trial_status' => $sub->status,
            'days_remaining' => $entitlement['days_remaining'],
            'days_active' => $daysActive,
            'monthly_price' => (float) $sub->price_per_month,
            'auto_continue' => (bool) $sub->auto_continue,
            'metrics' => [
                'tasks_completed' => (int) ($metrics['tasks_completed'] ?? 0),
                'orders_monitored' => (int) ($metrics['orders_monitored'] ?? 0),
                'issues_identified' => (int) ($metrics['issues_identified'] ?? 0),
                'issues_resolved' => (int) ($metrics['issues_resolved'] ?? 0),
                'reports_prepared' => (int) ($metrics['reports_prepared'] ?? 0),
                'issues_escalated' => (int) ($metrics['issues_escalated'] ?? 0),
                'verified_actions_taken' => (int) ($metrics['verified_actions_taken'] ?? 0),
                'false_completions_prevented' => 0, // Zero tolerance for false completions
            ],
            'call_to_action' => [
                'label' => "Keep Monique Working",
                'price_label' => "\$" . number_format($sub->price_per_month, 2) . "/month",
                'status_message' => $entitlement['message'],
            ],
        ];
    }
}
