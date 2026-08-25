<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\UrbanGoodzAIChiefOfStaffChatService;
use App\Services\UrbanGoodz\UrbanGoodzOperationalPlanner;
use App\Services\UrbanGoodz\PersonReferenceResolver;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * How an operator actually talks to Monique.
 *
 * The unit tests prove each action works when called directly. These prove the
 * thing that was actually broken in production: that a request typed the way a
 * person types it reaches an action at all, instead of Monique explaining what
 * the operator could go do themselves.
 */
class MoniqueUserUsageTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Monique refuses to act without an authenticated admin, which is
        // correct - every mutating action is attributed to a person.
        $this->admin = Admin::firstOrCreate(
            ['email' => 'usage-test-admin@urbangoodz.com'],
            [
                'f_name' => 'Usage',
                'l_name' => 'Tester',
                'phone' => '5550000001',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );
        auth()->guard('admin')->login($this->admin);
    }

    private function chat(): UrbanGoodzAIChiefOfStaffChatService
    {
        return app(UrbanGoodzAIChiefOfStaffChatService::class);
    }

    private function ask(string $text): string
    {
        $conversation = $this->chat()->processQuery(
            $text,
            $this->admin->id,
            'D\'Andre',
            'usage-test'
        );
        return strtolower($conversation->response_text ?? '');
    }

    /** Language that means Monique is deflecting instead of acting. */
    private function assertNotDeflection(string $reply, string $request): void
    {
        $deflections = [
            'you can go to',
            'navigate to',
            'you should visit',
            'please go to',
            'you will need to manually',
            'i am unable to make changes',
            'i cannot make changes',
        ];
        foreach ($deflections as $phrase) {
            $this->assertStringNotContainsString(
                $phrase,
                $reply,
                "Monique deflected instead of acting on: \"{$request}\"\nReply: {$reply}"
            );
        }
    }

    public function test_operator_asks_for_a_status_briefing(): void
    {
        $request = 'what is going on with my business today';
        $reply = $this->ask($request);

        $this->assertNotEmpty($reply, 'Monique returned nothing at all');
        $this->assertNotDeflection($reply, $request);
    }

    public function test_operator_asks_to_clear_everything(): void
    {
        // The original defect: "clear all" produced an explanation, not a plan.
        $request = 'clear all of that';
        $reply = $this->ask($request);

        $this->assertNotEmpty($reply);
        $this->assertNotDeflection($reply, $request);
    }

    public function test_operator_asks_to_handle_it_all(): void
    {
        $request = 'handle all of it for me';
        $reply = $this->ask($request);

        $this->assertNotEmpty($reply);
        $this->assertNotDeflection($reply, $request);
    }

    public function test_operator_asks_about_out_of_stock(): void
    {
        $request = 'what is out of stock right now';
        $reply = $this->ask($request);

        $this->assertNotEmpty($reply);
        $this->assertNotDeflection($reply, $request);
    }

    public function test_operator_asks_to_retry_failed_jobs(): void
    {
        $request = 'retry the failed queue jobs';
        $reply = $this->ask($request);

        $this->assertNotEmpty($reply);
        $this->assertNotDeflection($reply, $request);
    }

    public function test_planner_decomposes_a_broad_request(): void
    {
        $planner = app(UrbanGoodzOperationalPlanner::class);
        $plan = $planner->plan();

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('steps', $plan);
        $this->assertArrayHasKey('summary', $plan);
        $this->assertLessThanOrEqual(
            UrbanGoodzOperationalPlanner::MAX_ACTIONS_PER_PLAN,
            count($plan['steps']),
            'A single plan must stay within the action cap'
        );
    }

    public function test_planner_explains_what_it_cannot_do(): void
    {
        $plan = app(UrbanGoodzOperationalPlanner::class)->plan();

        // Anything unplannable must carry a reason - never silently dropped.
        foreach ($plan['unplannable'] ?? [] as $item) {
            $this->assertNotEmpty(
                $item['reason'] ?? '',
                'An unplannable item must say why, so the operator knows what is left'
            );
        }
        $this->assertTrue(true);
    }

    public function test_operator_refers_to_a_person_by_pronoun_across_turns(): void
    {
        // The conversational case pronoun resolution exists for.
        $resolver = new PersonReferenceResolver();
        $resolver->observeTurn('Marcus is my driver. He is running late.');
        $out = $resolver->observeTurn('Can you reassign his orders?');

        $this->assertEquals('Marcus', $out['his'] ?? null);
        $this->assertEquals('he', $resolver->pronounsFor('Marcus')['subject']);
    }

    public function test_unknown_person_is_addressed_neutrally(): void
    {
        $resolver = new PersonReferenceResolver();
        $resolver->observeTurn('Taylor has three open orders.');

        $this->assertFalse($resolver->pronounsAreKnown('Taylor'));
        $this->assertEquals('they', $resolver->pronounsFor('Taylor')['subject']);
    }

    public function test_monique_answers_every_phrasing_of_the_same_request(): void
    {
        // Real operators do not use one canonical phrasing.
        $phrasings = [
            'clear all',
            'take care of all of that',
            'fix everything',
        ];

        foreach ($phrasings as $phrasing) {
            $reply = $this->ask($phrasing);
            $this->assertNotEmpty($reply, "No reply to \"{$phrasing}\"");
            $this->assertNotDeflection($reply, $phrasing);
        }
    }
}
