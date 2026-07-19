<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiAgent;
use App\Models\AiAuditEvent;
use App\Models\AiTask;
use App\Models\AiWorkforceAction;
use App\Models\AiApproval;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AiWorkforceAutonomyService
{
    public function checkPolicy(AiAgent $agent, string $actionType, array $metadata = []): array
    {
        // 1. Global kill switch
        if (Config::get('urban_goodz.ai_workforce.global_kill_switch', false)) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Global kill switch active'], 'warning', 'critical');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Global kill switch is active.'];
        }

        // 2. Agent kill switch
        if ($agent->isKilled()) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Agent kill switch active'], 'warning', 'critical');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Agent kill switch is active.'];
        }

        // 3. Status active
        if (!$agent->isActive()) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Agent inactive'], 'warning', 'high');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Agent status is not active.'];
        }

        // 4. Prohibited actions
        if (!$agent->canExecute($actionType)) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Action type prohibited'], 'warning', 'high');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => "Action '{$actionType}' is prohibited for this agent."];
        }

        // 5. Daily limits
        if ($agent->hasReachedTaskLimit()) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Task limit reached'], 'limit_reached', 'high');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Daily task limit reached.'];
        }
        if ($agent->hasReachedMessageLimit() && in_array($actionType, ['send_email', 'send_outreach'])) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Message limit reached'], 'limit_reached', 'high');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Daily message limit reached.'];
        }
        if ($agent->hasReachedTokenLimit()) {
            $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Token limit reached'], 'limit_reached', 'high');
            return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Daily token limit reached.'];
        }

        // 6. Active hours check
        if ($agent->active_hours) {
            $start = $agent->active_hours['start'] ?? '00:00';
            $end = $agent->active_hours['end'] ?? '23:59';
            $tz = $agent->active_hours['timezone'] ?? 'UTC';
            $now = now()->setTimezone($tz);
            $currentTime = $now->format('H:i');
            if ($currentTime < $start || $currentTime > $end) {
                $this->logAudit($agent->id, null, null, $actionType, 'blocked', $metadata, ['reason' => 'Outside active hours', 'current_time' => $currentTime, 'hours' => "{$start}-{$end}"], 'warning', 'info');
                return ['allowed' => false, 'decision' => 'blocked', 'reason' => 'Outside agent active hours.'];
            }
        }

        // 7. Autonomy Level Decision
        $level = $agent->getAutonomyForAction($actionType);
        if ($level === AiAgent::LEVEL_ESCALATE) {
            $this->logAudit($agent->id, null, null, $actionType, 'escalated', $metadata, ['level' => 'level_4_escalate'], 'escalation', 'high');
            return ['allowed' => false, 'decision' => 'escalated', 'reason' => 'Policy requires human escalation.'];
        }

        if ($level === AiAgent::LEVEL_RECOMMEND) {
            $this->logAudit($agent->id, null, null, $actionType, 'approval_required', $metadata, ['level' => 'level_2_recommend'], 'approval_requested', 'info');
            return ['allowed' => true, 'decision' => 'approval_required', 'reason' => 'Requires approval before execution.'];
        }

        if ($level === AiAgent::LEVEL_OBSERVE) {
            $this->logAudit($agent->id, null, null, $actionType, 'allowed', $metadata, ['level' => 'level_1_observe'], 'observe_only', 'info');
            return ['allowed' => true, 'decision' => 'observe', 'reason' => 'Observe only mode. Execution skipped.'];
        }

        $this->logAudit($agent->id, null, null, $actionType, 'allowed', $metadata, ['level' => 'level_3_execute'], 'execution_started', 'info');
        return ['allowed' => true, 'decision' => 'allowed', 'reason' => 'Allowed to execute autonomously.'];
    }

    public function recordAction(AiAgent $agent, ?AiTask $task, string $actionType, array $payload, string $status, array $result = [], ?int $tokens = 0): AiWorkforceAction
    {
        $action = AiWorkforceAction::create([
            'ai_agent_id' => $agent->id,
            'ai_task_id' => $task ? $task->id : null,
            'action_type' => $actionType,
            'request_payload' => $payload,
            'result' => $result,
            'status' => $status,
            'approval_status' => 'approved',
            'tokens_used' => $tokens,
            'estimated_cost' => $tokens * 0.00002, // mock cost calculation
        ]);

        $this->logAudit($agent->id, $task ? $task->id : null, $action->id, $actionType, 'allowed', $payload, $result, 'execution_completed', 'info');
        return $action;
    }

    public function createApprovalRequest(AiAgent $agent, ?AiTask $task, string $actionType, array $payload, string $reason): AiApproval
    {
        $action = AiWorkforceAction::create([
            'ai_agent_id' => $agent->id,
            'ai_task_id' => $task ? $task->id : null,
            'action_type' => $actionType,
            'request_payload' => $payload,
            'status' => 'pending',
            'approval_status' => 'pending',
        ]);

        $approval = AiApproval::create([
            'ai_workforce_action_id' => $action->id,
            'requested_approver_id' => $agent->escalation_recipient_id ?? 1, // Fallback to Admin 1
            'decision' => 'pending',
            'reason' => $reason,
        ]);

        $this->logAudit($agent->id, $task ? $task->id : null, $action->id, $actionType, 'approval_required', $payload, ['approval_id' => $approval->id], 'approval_requested', 'warning');
        return $approval;
    }

    public function logAudit(?int $agentId, ?int $taskId, ?int $actionId, string $eventType, string $decision, array $reqMeta = [], array $resMeta = [], string $status = 'success', string $severity = 'info'): AiAuditEvent
    {
        return AiAuditEvent::create([
            'ai_agent_id' => $agentId,
            'ai_task_id' => $taskId,
            'ai_workforce_action_id' => $actionId,
            'event_type' => $eventType,
            'policy_decision' => $decision,
            'request_metadata' => $reqMeta,
            'result_metadata' => $resMeta,
            'status' => $status,
            'severity' => $severity,
        ]);
    }
}
