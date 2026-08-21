<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

/**
 * Monique's real conversational chat (chief_of_staff persona, display name
 * swapped from "Skylar") -- the business-portal counterpart to
 * UrbanGoodzAIConciergeService. Every message is grounded in the same live
 * counts the Command Center dashboard already shows (AiChiefOfStaffService),
 * remembers prior turns within a session, and recognizes when an owner is
 * describing a genuine operational emergency.
 *
 * Conversations are stored in the same urban_goodz_ai_conversations table as
 * the customer concierge's (now displayed as "Skylar"), but always with
 * source = self::SOURCE and the admin's id in
 * customer_id -- every history/prune query below filters on both together so
 * an admin id can never collide with a customer id of the same number.
 */
class UrbanGoodzAIChiefOfStaffChatService
{
    public const SOURCE = 'chief_of_staff_chat';

    private const HISTORY_TURNS = 6;

    private const MAX_STORED_PER_ADMIN = 30;

    public function __construct(
        private readonly UrbanGoodzAIService $ai,
        private readonly AiChiefOfStaffService $chiefOfStaff,
        private readonly UrbanGoodzAIExecutionService $execution,
        private readonly UrbanGoodzOperationalPlanner $planner,
    ) {}

    /**
     * Phrases that mean "deal with everything you just told me about".
     *
     * Matched conservatively: a broad sweep is exactly the request that should
     * not be inferred loosely, and a miss is harmless (Monique answers
     * normally) while a false positive proposes work nobody asked for.
     */
    private const BULK_PATTERNS = [
        'clear all', 'clear everything', 'clear this', 'clear that', 'clear those',
        'clear it', 'clear things', 'handle all', 'handle everything', 'handle it',
        'handle that', 'take care of all', 'take care of everything', 'take care of it',
        'take care of that', 'fix all', 'fix everything', 'fix that', 'sort it',
        'sort all', 'deal with all', 'deal with everything', 'deal with it',
        'resolve all', 'resolve everything', 'do it all',
    ];

    private function looksLikeBulkRequest(string $query): bool
    {
        $q = strtolower($query);
        foreach (self::BULK_PATTERNS as $pattern) {
            if (str_contains($q, $pattern)) {
                return true;
            }
        }
        return false;
    }

    public function processQuery(string $queryText, ?int $adminId, ?string $adminName = null, ?string $sessionId = null): UrbanGoodzAIConversation
    {
        if (!$adminId) {
            throw new AuthenticationException('Admin authentication is required.');
        }

        // Try to actually do the thing before talking about it.
        //
        // Monique used to be text-only: she could read the operational alerts
        // and describe them, but had no path to act, so she told the owner to
        // go and do it in the admin panel. Everything that protects the
        // business - role check, confirmation gating, idempotency, audit log,
        // post-execution verification - already lives in the execution
        // service, so this routes through it rather than around it.
        //
        // Actions the registry marks as requiring confirmation come back
        // awaiting confirmation and are NOT executed here; Monique asks first.
        $actionResult = $this->attemptAction($queryText, $adminId);

        $systemPrompt = $this->buildSystemPrompt($adminName, $actionResult);
        $history = $this->recentHistory($adminId, $sessionId);

        $result = $this->ai->isConfigured()
            ? $this->processWithAI($queryText, $adminId, $systemPrompt, $sessionId, $history)
            : $this->processWithoutAI($queryText, $adminId, $sessionId);

        $this->pruneOld($adminId);

        return $result;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function recentHistory(?int $adminId, ?string $sessionId): array
    {
        if (!$adminId || !$sessionId) {
            return [];
        }

        $rows = UrbanGoodzAIConversation::where('customer_id', $adminId)
            ->where('source', self::SOURCE)
            ->where('session_id', $sessionId)
            ->whereNotNull('response_text')
            ->latest()
            ->limit(self::HISTORY_TURNS)
            ->get(['query_text', 'response_text'])
            ->reverse();

        $history = [];
        foreach ($rows as $row) {
            $history[] = ['role' => 'user', 'content' => $row->query_text];
            $history[] = ['role' => 'assistant', 'content' => $row->response_text];
        }

        return $history;
    }

    private function pruneOld(?int $adminId): void
    {
        if (!$adminId) {
            return;
        }

        $keepIds = UrbanGoodzAIConversation::where('customer_id', $adminId)
            ->where('source', self::SOURCE)
            ->latest()
            ->limit(self::MAX_STORED_PER_ADMIN)
            ->pluck('id');

        UrbanGoodzAIConversation::where('customer_id', $adminId)
            ->where('source', self::SOURCE)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function processWithAI(string $queryText, int $adminId, string $systemPrompt, ?string $sessionId, array $history): UrbanGoodzAIConversation
    {
        $isUrgent = $this->looksUrgent($queryText);

        $providerResult = $this->ai->chatResult($systemPrompt, $queryText, [
            'flagged_as_urgent' => $isUrgent,
        ], $history);

        $responseText = $providerResult['response'];
        $status = $providerResult['success'] ? 'resolved' : 'failed';

        return UrbanGoodzAIConversation::create([
            'customer_id' => $adminId,
            'session_id' => $sessionId,
            'query_text' => $queryText,
            'response_text' => $responseText,
            'status' => $status,
            'source' => self::SOURCE,
            'metadata' => [
                'response_source' => 'ai_provider',
                'provider_success' => $providerResult['success'],
                'provider_error_code' => $providerResult['error_code'],
                'flagged_as_urgent' => $isUrgent,
            ],
        ]);
    }

    /**
     * No AI provider configured -- answer from the same live counts as the
     * dashboard rather than a canned line, so this is never a fabricated
     * number even without a model attached.
     */
    private function processWithoutAI(string $queryText, int $adminId, ?string $sessionId): UrbanGoodzAIConversation
    {
        $summary = $this->chiefOfStaff->getCommandCenterSummary();
        $responseText = sprintf(
            "AI assistance is currently unavailable, so here is what's live right now: %d task(s) in progress, %d open business need(s), %d pending human action(s), %d pending approval(s). No summary was generated -- these are direct counts.",
            $summary['in_progress'] ?? 0,
            $summary['business_needs'] ?? 0,
            $summary['human_actions_required'] ?? 0,
            $summary['approvals'] ?? 0,
        );

        return UrbanGoodzAIConversation::create([
            'customer_id' => $adminId,
            'session_id' => $sessionId,
            'query_text' => $queryText,
            'response_text' => $responseText,
            'status' => 'resolved',
            'source' => self::SOURCE,
            'metadata' => [
                'response_source' => 'deterministic_database',
                'flagged_as_urgent' => $this->looksUrgent($queryText),
            ],
        ]);
    }

    private function looksUrgent(string $queryText): bool
    {
        $normalized = strtolower($queryText);
        foreach (['urgent', 'emergency', 'crisis', 'down', 'outage', 'losing money', 'furious', 'lawsuit', 'right now', 'immediately'] as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skylar (chief_of_staff persona) carries identity, voice, and the platform rule block from the
     * persona system; only the task and the live grounding facts are built
     * here, mirroring UrbanGoodzAIConciergeService::buildSystemPrompt().
     */
    /**
     * Runs the query through the Digital Human action layer.
     *
     * Returns null when the query was not an actionable request, so ordinary
     * conversation is unaffected. Never throws into the chat path: if the
     * action layer fails, Monique still answers, but she is told it failed
     * rather than being left free to imply it worked.
     *
     * @return array<string,mixed>|null
     */
    private function attemptAction(string $queryText, int $adminId): ?array
    {
        // A broad sweep is a plan, not a single action. Decompose it into
        // named steps and propose them; nothing mutating runs until the owner
        // confirms, and the confirmed run goes through executePlan(), which
        // authorizes and verifies each step individually.
        if ($this->looksLikeBulkRequest($queryText)) {
            return $this->planBulkRequest($adminId);
        }

        try {
            $result = $this->execution->executeIntent($queryText, $adminId, 'admin');

            // Unroutable / unrecognised queries are conversation, not failed
            // actions - don't report those as action failures.
            $intent = $result['intent'] ?? null;
            if (!$intent || $intent === 'unknown') {
                return null;
            }

            return [
                'attempted' => true,
                'succeeded' => (bool) ($result['success'] ?? false),
                'verified' => (bool) ($result['verified'] ?? false),
                'action' => $result['action'] ?? null,
                'intent' => $intent,
                'awaiting_confirmation' => (bool) ($result['awaiting_confirmation'] ?? false),
                'blocked_reason' => $result['blocked_reason'] ?? null,
                'outcome' => $result['message'] ?? $result['explanation'] ?? null,
                'previous_state' => $result['previous_state'] ?? null,
                'new_state' => $result['new_state'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Chief of staff action attempt failed', [
                'admin_id' => $adminId,
                'exception' => $e::class,
            ]);

            return [
                'attempted' => true,
                'succeeded' => false,
                'verified' => false,
                'outcome' => 'The action layer returned an error, so nothing was confirmed.',
            ];
        }
    }

    /**
     * Builds the plan for a broad request and returns it as an action result.
     *
     * Read-only steps run immediately - they change nothing and make the
     * proposal concrete. Mutating steps are proposed, never auto-run.
     *
     * @return array<string,mixed>
     */
    private function planBulkRequest(int $adminId): array
    {
        try {
            $plan = $this->planner->plan();

            $reads = array_values(array_filter($plan['steps'], fn ($s) => !$s['mutates']));
            $mutations = array_values(array_filter($plan['steps'], fn ($s) => $s['mutates']));

            $readResults = $reads
                ? $this->execution->executePlan($reads, $adminId, 'admin')
                : null;

            return [
                'attempted' => true,
                'succeeded' => false,
                'verified' => false,
                'awaiting_confirmation' => count($mutations) > 0,
                'action' => 'execute_plan',
                'intent' => 'operations',
                'plan' => [
                    'proposed_steps' => array_map(fn ($s) => [
                        'label' => $s['label'],
                        'action' => $s['action'],
                    ], $mutations),
                    'proposed_count' => count($mutations),
                    'unplannable' => $plan['unplannable'],
                ],
                'read_results' => $readResults,
                'outcome' => count($mutations) === 0
                    ? 'There is nothing I can action automatically right now.'
                    : count($mutations) . ' action(s) are ready to run once you confirm.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Chief of staff bulk planning failed', [
                'admin_id' => $adminId,
                'exception' => $e::class,
            ]);

            return [
                'attempted' => true,
                'succeeded' => false,
                'verified' => false,
                'outcome' => 'I could not build a plan for that, so nothing was attempted.',
            ];
        }
    }

    /**
     * @param array<string,mixed>|null $actionResult
     */
    private function buildSystemPrompt(?string $adminName, ?array $actionResult = null): string
    {
        $summary = $this->chiefOfStaff->getCommandCenterSummary();
        // Carry each alert's executable actions through to the model. Without
        // them the alert is just a number and a link, which is what made her
        // answer "go to the admin panel"; with them she can offer to do it.
        $alerts = collect($this->chiefOfStaff->getOperationalAlerts())
            ->filter(fn ($a) => $a['available'] ?? false)
            ->map(fn ($a) => [
                'label' => $a['label'],
                'count' => $a['count'],
                'actionable' => $a['actionable'] ?? false,
                'actions' => $a['actions'] ?? [],
            ])
            ->values()
            ->toArray();

        $task = "Help this business owner run Urban Goodz: explain what the live metrics below mean,
flag what needs their attention first, and recommend a concrete next action.
Only use the numbers supplied below -- never invent a metric, order count, or
dollar figure that isn't in the application data.

If `flagged_as_urgent` is true, this may be a genuine operational emergency
(an outage, a furious client, a compliance or legal problem). Drop straight
into focused, direct triage: what is actually broken, what is the immediate
mitigation, and what needs the owner's action in the next few minutes. Do not
open with a pleasantry when the flag is true.

Escalate to a human specialist (legal, compliance, finance) when the matter is
outside what operational data can resolve.

YOU CAN ACT. You are not limited to advice, and you must never tell the owner
that an action 'requires direct administrative execution through the admin
panel' or that you 'cannot execute from this briefing'. Operational requests
are routed through the Urban Goodz action layer before you reply, and the
outcome is given to you as `action_result`.

How to use `action_result`:
- absent: no action was attempted. Answer normally.
- awaiting_confirmation true: the action is real and available but commits the
  business, so it needs a yes. State plainly what you are about to do and ask
  to proceed.
- succeeded true and verified true: it is done and was confirmed against the
  database. Say what changed, using previous_state and new_state.
- succeeded false: it did NOT happen. Say so directly and give the reason from
  `outcome` or `blocked_reason`. Never soften a failure into an implication of
  success, and never claim something is done that is not.

Never describe an action as completed unless verified is true.

Each entry in `operational_alerts` carries `actions` - what you can actually do
about that alert. When an alert is actionable, offer the action rather than
describing where the owner could go and do it themselves. When `actions` is
empty there is genuinely no automated action for that alert yet; say what it
needs instead of inventing a capability.

'Clear' never means deleting records. For out-of-stock items the safe move is
the store breakdown, not removal.

When `action_result.plan` is present the owner asked you to handle everything.
List `proposed_steps` concretely - the actual orders and jobs, not a vague
offer - then ask to proceed. Name anything in `unplannable` and why it needs a
person; never let it pass unmentioned, and never imply you handled it.";

        $grounding = [
            'admin_name' => $adminName,
            'command_center_summary' => $summary,
            'operational_alerts' => $alerts,
            'action_result' => $actionResult,
        ];

        return $this->ai->persona(PersonaRegistry::CHIEF_OF_STAFF)->systemPrompt($task, $grounding);
    }
}
