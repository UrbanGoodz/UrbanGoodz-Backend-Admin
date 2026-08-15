<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use App\Services\UrbanGoodz\AI\Persona\PersonaRegistry;
use Illuminate\Auth\AuthenticationException;

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
    ) {}

    public function processQuery(string $queryText, ?int $adminId, ?string $adminName = null, ?string $sessionId = null): UrbanGoodzAIConversation
    {
        if (!$adminId) {
            throw new AuthenticationException('Admin authentication is required.');
        }

        $systemPrompt = $this->buildSystemPrompt($adminName);
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
    private function buildSystemPrompt(?string $adminName): string
    {
        $summary = $this->chiefOfStaff->getCommandCenterSummary();
        $alerts = collect($this->chiefOfStaff->getOperationalAlerts())
            ->filter(fn ($a) => $a['available'] ?? false)
            ->map(fn ($a) => ['label' => $a['label'], 'count' => $a['count']])
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
outside what operational data can resolve.";

        $grounding = [
            'admin_name' => $adminName,
            'command_center_summary' => $summary,
            'operational_alerts' => $alerts,
        ];

        return $this->ai->persona(PersonaRegistry::CHIEF_OF_STAFF)->systemPrompt($task, $grounding);
    }
}
