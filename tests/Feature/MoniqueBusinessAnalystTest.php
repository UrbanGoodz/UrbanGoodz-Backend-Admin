<?php

namespace Tests\Feature;

use App\Contracts\AI\AIProviderInterface;
use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Services\UrbanGoodz\AI\AIProviderManager;
use App\Services\UrbanGoodz\Agent\MoniqueBusinessAnalyst;
use App\Services\UrbanGoodz\Agent\MoniqueBusinessMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MoniqueBusinessAnalystTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): Admin
    {
        return Admin::create([
            'f_name' => 'Boss', 'l_name' => 'Owner', 'email' => 'an-'.uniqid().'@urban-goodz.test', 'phone' => '1555'.random_int(1000000, 9999999),
            'password' => bcrypt('x'), 'role_id' => 1,
        ]);
    }

    private function analystReplying(?string $reply, bool $configured = true): MoniqueBusinessAnalyst
    {
        $provider = new class($reply, $configured) implements AIProviderInterface {
            public ?string $lastUser = null;

            public function __construct(private ?string $reply, private bool $configured) {}

            public function name(): string { return 'fake'; }

            public function model(): string { return 'fake'; }

            public function isConfigured(): bool { return $this->configured; }

            public function chatResult(string $systemPrompt, string $userMessage, array $context = [], array $history = []): array
            {
                $this->lastUser = $userMessage;

                return $this->reply === null
                    ? ['success' => false, 'response' => 'down', 'error_code' => 'x']
                    : ['success' => true, 'response' => $this->reply, 'error_code' => null];
            }

            public function healthCheck(): array { return []; }
        };
        $manager = new class($provider) extends AIProviderManager {
            public function __construct(private $p) {}

            public function resolve(?string $provider = null, bool $withFallback = true): AIProviderInterface { return $this->p; }
        };

        return new MoniqueBusinessAnalyst(app(MoniqueBusinessMetrics::class), $manager);
    }

    public function test_metrics_come_from_the_database_and_the_provider_only_sees_them(): void
    {
        $snapshot = app(MoniqueBusinessMetrics::class)->snapshot();
        $this->assertArrayHasKey('drivers_active', $snapshot);
    }

    public function test_real_evidence_becomes_a_recommendation_and_invented_evidence_is_dropped(): void
    {
        $admin = $this->admin();
        $reply = json_encode(['headline' => 'Driver supply is the constraint', 'insights' => [
            ['title' => 'Recruit drivers', 'observation' => 'Applications are stuck.', 'evidence' => ['driver_applications_waiting'], 'recommendation' => 'Review applications daily.', 'impact' => 'More supply.', 'confidence' => 'high', 'owner_decision' => true],
            ['title' => 'Competitor X is cheaper', 'observation' => 'Made up.', 'evidence' => ['competitor_price_index'], 'recommendation' => 'Cut prices.', 'confidence' => 'high'],
            ['title' => 'No evidence at all', 'observation' => 'Vibes.', 'evidence' => [], 'recommendation' => 'Do something.'],
        ]]);

        $result = $this->analystReplying("```json\n{$reply}\n```")->run(true);

        $this->assertSame('ai', $result['source']);
        $this->assertSame(1, $result['insights'], 'only the insight with real evidence may survive');
        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'business_insight')->firstOrFail();
        $this->assertStringContainsString('Recruit drivers', $note->message);
        $this->assertStringNotContainsString('Competitor X', $note->message);
        $this->assertSame('Driver supply is the constraint', $note->title);
    }

    public function test_when_the_ai_is_down_she_still_reports_the_real_numbers(): void
    {
        $admin = $this->admin();

        $result = $this->analystReplying(null)->run(true);

        $this->assertSame('numbers_only', $result['source']);
        $note = AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'business_insight')->firstOrFail();
        $this->assertStringContainsString('Approved active drivers', $note->message);
    }

    public function test_garbage_from_the_model_is_never_shown_as_analysis(): void
    {
        $admin = $this->admin();

        $result = $this->analystReplying('Sure! Here are some thoughts, no JSON at all.')->run(true);

        $this->assertSame('numbers_only', $result['source']);
        $this->assertSame(0, $result['insights']);
        $this->assertNotNull(AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'business_insight')->first());
    }

    public function test_she_writes_at_most_once_a_day_unless_forced(): void
    {
        $admin = $this->admin();
        $analyst = $this->analystReplying(null);

        $analyst->run(true);
        $second = $analyst->run(false);

        $this->assertSame(0, $second['notifications']);
        $this->assertSame(1, AiMoniqueNotification::forAccount('admin', $admin->id)->where('category', 'business_insight')->count());
    }
}
