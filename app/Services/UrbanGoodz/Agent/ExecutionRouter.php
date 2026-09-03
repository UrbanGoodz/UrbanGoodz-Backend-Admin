<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiAuditEvent;
use Illuminate\Support\Facades\Log;

class ExecutionRouter
{
    private array $adapters = [];

    public function __construct(
        private readonly AgentToolRegistry $registry,
        private readonly NativeToolAdapter $nativeAdapter,
        private readonly PolsiaAdapter $polsiaAdapter
    ) {
        $this->adapters['native'] = $this->nativeAdapter;
        $this->adapters['polsia'] = $this->polsiaAdapter;
    }

    public function execute(string $toolName, array $parameters, array $context = []): array
    {
        // 1. Tool existence check
        $tool = $this->registry->getTool($toolName);
        if (!$tool) {
            return [
                'success' => false,
                'verified' => false,
                'tool' => $toolName,
                'error_code' => 'tool_not_found',
                'message' => "Tool '{$toolName}' is not registered in the agent tool registry.",
            ];
        }

        // 2. Authorization check
        $role = $context['actor_role'] ?? 'admin';
        if (!$this->registry->isAuthorized($toolName, $role)) {
            Log::warning("Agent authorization blocked tool execution", [
                'tool' => $toolName,
                'role' => $role,
                'user_id' => $context['admin_id'] ?? null,
            ]);

            return [
                'success' => false,
                'verified' => false,
                'tool' => $toolName,
                'error_code' => 'unauthorized',
                'message' => "Role '{$role}' is not authorized to execute tool '{$toolName}'.",
            ];
        }

        // 3. Human confirmation check
        $requiresConfirmation = $this->registry->requiresConfirmation($toolName);
        $isConfirmed = !empty($context['confirmed']);

        if ($requiresConfirmation && !$isConfirmed) {
            return [
                'success' => true,
                'awaiting_confirmation' => true,
                'verified' => false,
                'tool' => $toolName,
                'parameters' => $parameters,
                'risk_level' => $tool['risk_level'],
                'message' => "This action ({$tool['description']}) is classified as {$tool['risk_level']} and requires explicit confirmation before execution.",
            ];
        }

        // 4. Determine adapter
        $preferredAdapter = config('urban_goodz_ai.execution.default_adapter', 'native');
        $adapter = $this->adapters[$preferredAdapter] ?? $this->nativeAdapter;

        // 5. Execute with automatic fallback if preferred is Polsia and fails
        $result = $adapter->execute($toolName, $parameters, $context);

        if (!$result['success'] && $adapter->name() === 'polsia') {
            Log::info("Polsia execution unavailable for '{$toolName}'. Gracefully failing over to Native execution.");
            $result = $this->nativeAdapter->execute($toolName, $parameters, $context);
            $result['fallback_from_polsia'] = true;
        }

        // 6. Audit Logging
        $this->recordAudit($toolName, $parameters, $context, $result);

        return $result;
    }

    public function getRegistry(): AgentToolRegistry
    {
        return $this->registry;
    }

    public function getActiveAdapterName(): string
    {
        return config('urban_goodz_ai.execution.default_adapter', 'native');
    }

    public function isPolsiaConfigured(): bool
    {
        return $this->polsiaAdapter->isConfigured();
    }

    private function recordAudit(string $toolName, array $params, array $context, array $result): void
    {
        try {
            AiAuditEvent::create([
                'event_type' => 'agent_tool_executed',
                'policy_decision' => ($result['success'] ?? false) ? 'allowed' : 'rejected',
                'request_metadata' => [
                    'tool' => $toolName,
                    'parameters' => $params,
                    'adapter' => $result['adapter'] ?? 'unknown',
                    'context' => $context,
                ],
                'result_metadata' => [
                    'verified' => $result['verified'] ?? false,
                    'previous_state' => $result['previous_state'] ?? null,
                    'new_state' => $result['new_state'] ?? null,
                    'message' => $result['message'] ?? null,
                ],
                'actor_type' => 'admin',
                'actor_id' => $context['admin_id'] ?? null,
                'status' => ($result['success'] ?? false) ? 'completed' : 'failed',
                'severity' => ($this->registry->getRiskLevel($toolName) === AgentToolRegistry::RISK_HIGH_WRITE) ? 'warning' : 'info',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist AiAuditEvent: ' . $e->getMessage());
        }
    }
}
