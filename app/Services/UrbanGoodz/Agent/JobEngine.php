<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiAgent;
use App\Models\AiTask;
use App\Models\AiWorkforceAction;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobEngine
{
    public function __construct(
        private readonly ExecutionRouter $router,
        private readonly UrbanGoodzAIService $ai
    ) {}

    /**
     * Create and execute a multi-step operational job from a business objective.
     */
    public function runJob(string $objective, int $adminId, array $context = []): array
    {
        $taskModel = $this->createJob($objective, $adminId);
        return $this->executeJob($taskModel->id, $context);
    }

    /**
     * Create a persisted AiTask job record.
     */
    public function createJob(string $objective, int $adminId): AiTask
    {
        $agent = AiAgent::where('slug', 'monique_chief_of_staff')->first()
            ?? AiAgent::where('role', 'Chief of Staff')->first();

        return AiTask::create([
            'ai_agent_id' => $agent?->id,
            'task_type' => 'executive_job',
            'objective' => $objective,
            'priority' => 1,
            'status' => 'pending',
            'idempotency_key' => 'job_' . Str::random(16),
            'input' => [
                'objective' => $objective,
                'admin_id' => $adminId,
                'created_at' => now()->toIso8601String(),
            ],
            'output' => [],
        ]);
    }

    /**
     * Execute a persisted job step by step.
     */
    public function executeJob(int $jobId, array $context = []): array
    {
        $job = AiTask::find($jobId);
        if (!$job) {
            return [
                'success' => false,
                'verified' => false,
                'message' => "Job #{$jobId} not found.",
            ];
        }

        $job->markRunning();
        $objective = $job->objective;
        $adminId = $job->input['admin_id'] ?? 1;
        $context['admin_id'] = $adminId;

        $steps = $this->decomposeObjective($objective);
        $executedTasks = [];
        $overallSuccess = true;
        $allVerified = true;

        foreach ($steps as $index => $step) {
            $taskNum = $index + 1;
            $toolName = $step['tool'];
            $params = $step['parameters'] ?? [];
            $description = $step['description'] ?? "Task #{$taskNum}";

            // Persist workforce action
            $actionRecord = AiWorkforceAction::create([
                'ai_agent_id' => $job->ai_agent_id,
                'ai_task_id' => $job->id,
                'action_type' => $toolName,
                'target_type' => $step['target_type'] ?? 'system',
                'request_payload' => $params,
                'status' => 'running',
                'provider' => $this->router->getActiveAdapterName(),
            ]);

            // Execute through router
            $result = $this->router->execute($toolName, $params, $context);

            $actionSuccess = (bool) ($result['success'] ?? false);
            $actionVerified = (bool) ($result['verified'] ?? false);

            $actionRecord->update([
                'status' => $actionSuccess ? 'completed' : 'failed',
                'result' => $result,
            ]);

            $executedTasks[] = [
                'step' => $taskNum,
                'description' => $description,
                'tool' => $toolName,
                'success' => $actionSuccess,
                'verified' => $actionVerified,
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? [],
            ];

            if (!$actionSuccess) {
                $overallSuccess = false;
            }
            if (!$actionVerified) {
                $allVerified = false;
            }
        }

        $finalOutput = [
            'job_id' => $job->id,
            'objective' => $objective,
            'status' => $overallSuccess ? 'completed' : 'partially_completed',
            'verified' => $allVerified,
            'tasks_executed' => count($executedTasks),
            'tasks' => $executedTasks,
            'completed_at' => now()->toIso8601String(),
        ];

        if ($overallSuccess) {
            $job->markCompleted($finalOutput, 0.95);
        } else {
            $job->markFailed('One or more sub-tasks encountered errors.');
        }

        return [
            'success' => $overallSuccess,
            'verified' => $allVerified,
            'job_id' => $job->id,
            'objective' => $objective,
            'output' => $finalOutput,
            'report' => $this->generateCompletionReport($objective, $executedTasks),
        ];
    }

    /**
     * Decompose a business objective into concrete sequential tasks.
     *
     * @return list<array{tool: string, description: string, parameters: array<string, mixed>, target_type?: string}>
     */
    public function decomposeObjective(string $objective): array
    {
        $lower = strtolower($objective);

        // Scenario 1: Vendor onboarding backlog / cleanup
        if (str_contains($lower, 'vendor') && (str_contains($lower, 'onboard') || str_contains($lower, 'backlog') || str_contains($lower, 'incomplete') || str_contains($lower, 'clean'))) {
            return [
                [
                    'tool' => 'audit_vendor_onboarding',
                    'description' => 'Audit all registered vendors and identify missing documentation or incomplete applications',
                    'parameters' => ['scope' => 'incomplete_only'],
                    'target_type' => 'vendor_applications',
                ],
                [
                    'tool' => 'list_vendors',
                    'description' => 'Retrieve active versus inactive vendor catalog status for capacity comparison',
                    'parameters' => ['status' => 'inactive', 'limit' => 10],
                    'target_type' => 'vendors',
                ],
            ];
        }

        // Scenario 2: Operations / Inventory / Out of stock
        if (str_contains($lower, 'stock') || str_contains($lower, 'inventory') || str_contains($lower, 'store')) {
            return [
                [
                    'tool' => 'get_out_of_stock_inventory',
                    'description' => 'Query currently out-of-stock items grouped by vendor store',
                    'parameters' => ['limit' => 15],
                    'target_type' => 'items',
                ],
                [
                    'tool' => 'get_command_center_metrics',
                    'description' => 'Verify overall operational platform metrics and active alerts',
                    'parameters' => [],
                    'target_type' => 'command_center',
                ],
            ];
        }

        // Default: Query Command Center and Vendor Status
        return [
            [
                'tool' => 'get_command_center_metrics',
                'description' => 'Retrieve current operational command center brief and live alerts',
                'parameters' => [],
                'target_type' => 'command_center',
            ],
            [
                'tool' => 'audit_vendor_onboarding',
                'description' => 'Check vendor onboarding queue status',
                'parameters' => ['scope' => 'all'],
                'target_type' => 'vendors',
            ],
        ];
    }

    private function generateCompletionReport(string $objective, array $tasks): string
    {
        $count = count($tasks);
        $successCount = count(array_filter($tasks, fn ($t) => $t['success']));
        $lines = ["Chief of Staff Execution Report: Completed {$successCount} of {$count} tasks for objective: \"{$objective}\"."];

        foreach ($tasks as $t) {
            $statusMark = $t['verified'] ? "✓ [Verified]" : "✗ [Unverified]";
            $lines[] = "- {$t['description']}: {$statusMark} {$t['message']}";
        }

        return implode("\n", $lines);
    }
}
