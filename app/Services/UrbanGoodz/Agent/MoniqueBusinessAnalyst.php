<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Services\UrbanGoodz\AI\AIProviderManager;
use Illuminate\Support\Facades\Log;

/**
 * Monique thinking like a chief of staff: she reads the real numbers, says what they mean for the
 * business, and recommends what to do about it, ranked. She may think freely about strategy, but she
 * may only CITE figures that exist in the metrics snapshot, an insight with no real evidence is dropped,
 * and nothing here changes data or moves money.
 */
class MoniqueBusinessAnalyst
{
    public const CATEGORY = 'business_insight';

    public function __construct(
        private readonly MoniqueBusinessMetrics $metrics,
        private readonly ?AIProviderManager $providers = null,
    ) {}

    public function run(bool $force = false): array
    {
        $snapshot = $this->metrics->snapshot();
        if ($snapshot === []) {
            return ['status' => 'no_data', 'notifications' => 0];
        }

        $thin = $this->metrics->isThin($snapshot);
        $insights = [];
        $headline = null;
        $source = 'numbers_only';

        try {
            $provider = ($this->providers ?? new AIProviderManager)->resolve();
            if ($provider->isConfigured()) {
                $result = $provider->chatResult($this->systemPrompt(), $this->userMessage($snapshot, $thin));
                if (($result['success'] ?? false) && is_string($result['response'] ?? null)) {
                    [$headline, $insights] = $this->parse($result['response'], $snapshot);
                    $source = $insights ? 'ai' : 'numbers_only';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Monique analyst could not reach the AI provider', ['error' => $e->getMessage()]);
        }

        $title = $headline ?: ($thin ? 'Weekly numbers (low volume, so no trends yet)' : 'Weekly business numbers');
        $message = $insights ? $this->renderInsights($insights) : $this->renderNumbers($snapshot, $thin);

        $made = 0;
        foreach (Admin::query()->pluck('id') as $adminId) {
            $recent = AiMoniqueNotification::forAccount('admin', $adminId)
                ->where('category', self::CATEGORY)
                ->where('created_at', '>=', now()->subHours($force ? 0 : 20))
                ->exists();
            if ($recent) {
                continue;
            }
            AiMoniqueNotification::create([
                'account_type' => 'admin',
                'account_id' => $adminId,
                'category' => self::CATEGORY,
                'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
                'title' => mb_substr($title, 0, 180),
                'message' => $message,
                'actions' => [['label' => 'Dismiss', 'action' => 'dismiss']],
                'is_actionable' => false,
                'can_auto_resolve' => false,
                'auto_resolved' => false,
                'status' => AiMoniqueNotification::STATUS_PENDING,
                'delivered_channels' => ['in_app', 'notification_center'],
            ]);
            $made++;
        }

        return ['status' => 'ok', 'source' => $source, 'insights' => count($insights), 'notifications' => $made];
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are Monique, chief of staff at Urban Goodz, an on-demand delivery company: Order Anywhere (a customer asks for something from anywhere, it is sourced and priced with a markup, a nearby driver buys and delivers it), a store marketplace, dispatch and logistics for businesses, medical courier, and Stranded roadside help.
Think like a sharp, commercially minded operator and owner's advisor. Be direct and specific. Rank by business impact. Challenge assumptions, spot risks and openings the owner has not asked about, and say plainly when something is not working.
HARD RULES: Use ONLY the numbers in the data provided; never invent a figure, a customer, a competitor or a market statistic. Every insight must cite the metric keys it rests on. If volume is small, say the sample is too small for a conclusion and recommend how to learn faster instead of pretending. You advise; you do not move money, approve people or change prices yourself.
Reply with JSON only, no prose around it:
{"headline":"one sentence","insights":[{"title":"","observation":"what the numbers show","evidence":["metric_key"],"recommendation":"the concrete next step","impact":"why it matters, qualitative unless the numbers support a figure","confidence":"low|medium|high","owner_decision":true|false}]}
At most 5 insights, best first.
TXT;
    }

    private function userMessage(array $snapshot, bool $thin): string
    {
        return json_encode([
            'period' => 'last 7 days versus the 7 days before',
            'low_volume' => $thin,
            'metrics' => $snapshot,
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0: ?string, 1: array<int, array<string, mixed>>} */
    private function parse(string $raw, array $snapshot): array
    {
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)));
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [null, []];
        }

        $clean = [];
        foreach ((array) ($data['insights'] ?? []) as $i) {
            if (! is_array($i)) {
                continue;
            }
            $evidence = array_values(array_filter((array) ($i['evidence'] ?? []), fn ($k) => is_string($k) && isset($snapshot[$k])));
            // An insight that cites no real metric is an opinion dressed as analysis. Drop it.
            if ($evidence === [] || empty($i['title']) || empty($i['recommendation'])) {
                continue;
            }
            $clean[] = [
                'title' => mb_substr((string) $i['title'], 0, 140),
                'observation' => mb_substr((string) ($i['observation'] ?? ''), 0, 500),
                'evidence' => $evidence,
                'recommendation' => mb_substr((string) $i['recommendation'], 0, 500),
                'impact' => mb_substr((string) ($i['impact'] ?? ''), 0, 300),
                'confidence' => in_array($i['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $i['confidence'] : 'low',
                'owner_decision' => (bool) ($i['owner_decision'] ?? true),
            ];
            if (count($clean) >= 5) {
                break;
            }
        }

        $headline = isset($data['headline']) && is_string($data['headline']) ? mb_substr($data['headline'], 0, 180) : null;

        return [$headline, $clean];
    }

    private function renderInsights(array $insights): string
    {
        $lines = [];
        foreach ($insights as $n => $i) {
            $lines[] = ($n + 1) . '. ' . $i['title'] . ' [' . $i['confidence'] . ' confidence]'
                . "\n   What I see: " . $i['observation']
                . "\n   What I'd do: " . $i['recommendation']
                . ($i['impact'] ? "\n   Why it matters: " . $i['impact'] : '')
                . "\n   Based on: " . implode(', ', $i['evidence'])
                . ($i['owner_decision'] ? "\n   Needs your decision." : '');
        }

        return implode("\n\n", $lines);
    }

    private function renderNumbers(array $snapshot, bool $thin): string
    {
        $lines = [];
        foreach ($snapshot as $m) {
            $prior = $m['prior'];
            $change = ($prior !== null && $prior != 0.0) ? sprintf(' (%+.0f%% vs the week before)', (($m['value'] - $prior) / $prior) * 100) : '';
            $lines[] = '- ' . $m['label'] . ': ' . $m['value'] . ' ' . $m['unit'] . $change;
        }
        $note = $thin ? "Volume is still small, so these are not trends yet.\n" : '';

        return $note . implode("\n", $lines);
    }
}
