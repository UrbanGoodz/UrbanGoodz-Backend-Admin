<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AiChiefOfStaffService;
use App\Services\UrbanGoodz\AllowedActionRegistry;
use App\Services\UrbanGoodz\UrbanGoodzAIChiefOfStaffChatService;
use App\Services\UrbanGoodz\UrbanGoodzAIConciergeService;
use App\Services\UrbanGoodz\UrbanGoodzAIService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class UrbanGoodzAiPlatformSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createTables();
    }

    public function test_chief_of_staff_diagnostics_are_grounded_and_read_only(): void
    {
        DB::table('orders')->insert([
            [
                'id' => 11,
                'user_id' => 1,
                'delivery_man_id' => null,
                'order_status' => 'processing',
                'order_amount' => 20,
                'created_at' => now()->subHours(3),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'user_id' => 1,
                'delivery_man_id' => null,
                'order_status' => 'delivered',
                'order_amount' => 30,
                'created_at' => now()->subHours(4),
                'updated_at' => now(),
            ],
        ]);
        DB::table('items')->insert([
            ['stock' => 0, 'status' => 1],
            ['stock' => 5, 'status' => 1],
        ]);
        DB::table('urban_goodz_payment_ledgers')->insert([
            'payment_status' => 'failed',
            'customer_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('load_source_errors')->insert(['resolved' => false]);

        $service = app(AiChiefOfStaffService::class);
        $alerts = collect($service->getOperationalAlerts())->keyBy('key');
        $beforeNeeds = DB::table('business_needs')->count();
        $beforeActions = DB::table('human_action_items')->count();

        $diagnostics = $service->runDiagnosticScan();

        $this->assertSame(1, $alerts['unassigned_orders']['count']);
        $this->assertSame(1, $alerts['delayed_orders']['count']);
        $this->assertSame(1, $alerts['out_of_stock_items']['count']);
        $this->assertSame(1, $alerts['failed_payments']['count']);
        $this->assertSame(1, $alerts['load_sourcing_errors']['count']);
        $this->assertTrue($diagnostics['read_only']);
        $this->assertSame($beforeNeeds, DB::table('business_needs')->count());
        $this->assertSame($beforeActions, DB::table('human_action_items')->count());
    }

    public function test_concierge_only_returns_orders_owned_by_authenticated_customer(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'f_name' => 'First', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'f_name' => 'Other', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('orders')->insert([
            [
                'id' => 101,
                'user_id' => 1,
                'delivery_man_id' => null,
                'order_status' => 'processing',
                'order_amount' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 999,
                'user_id' => 2,
                'delivery_man_id' => null,
                'order_status' => 'picked_up',
                'order_amount' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnFalse();
        $provider->shouldReceive('persona')->andReturn((new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CONCIERGE));

        $conversation = (new UrbanGoodzAIConciergeService($provider))
            ->processQuery('Track my delivery', 1);

        $this->assertSame('resolved', $conversation->status);
        $this->assertStringContainsString('Order #101', $conversation->response_text);
        $this->assertStringNotContainsString('Order #999', $conversation->response_text);
        $this->assertSame('deterministic_database', $conversation->metadata['response_source']);
    }

    public function test_concierge_threads_prior_turns_into_the_next_ai_call_as_real_history(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'f_name' => 'First', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('urban_goodz_ai_conversations')->insert([
            [
                'customer_id' => 1,
                'session_id' => 'sess-1',
                'query_text' => "I'm looking for a birthday gift.",
                'response_text' => "Absolutely. Who's it for?",
                'status' => 'resolved',
                'source' => 'customer_api',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);
        // A different session for the same customer must never leak in.
        DB::table('urban_goodz_ai_conversations')->insert([
            [
                'customer_id' => 1,
                'session_id' => 'sess-other',
                'query_text' => 'unrelated question',
                'response_text' => 'unrelated answer',
                'status' => 'resolved',
                'source' => 'customer_api',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CONCIERGE);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('persona')->andReturn($persona);
        $provider->shouldReceive('classifyIntent')->andReturn(['intent' => 'unknown', 'confidence' => 0.9, 'entities' => []]);

        $capturedHistory = null;
        $provider->shouldReceive('chatResult')
            ->once()
            ->withArgs(function ($system, $user, $context, $history) use (&$capturedHistory) {
                $capturedHistory = $history;
                return true;
            })
            ->andReturn(['success' => true, 'response' => 'My wife loves jewelry.', 'error_code' => null]);

        (new UrbanGoodzAIConciergeService($provider))
            ->processQuery('My wife.', 1, 'customer_api', 'sess-1');

        $this->assertSame([
            ['role' => 'user', 'content' => "I'm looking for a birthday gift."],
            ['role' => 'assistant', 'content' => "Absolutely. Who's it for?"],
        ], $capturedHistory);
    }

    public function test_stranded_query_is_recognized_and_handed_off_without_the_ai_provider(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'f_name' => 'First', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('urban_goodz_ai_intents')->insert([
            'slug' => 'stranded',
            'name' => 'Stranded / Roadside Help',
            'keywords' => json_encode(['stranded', 'broke down', 'flat tire']),
            'response_template' => "Okay, let's get you some help. Are you somewhere safe? I'm connecting you to Urban Goodz Stranded.",
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CONCIERGE);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnFalse();
        $provider->shouldReceive('persona')->andReturn($persona);

        $conversation = (new UrbanGoodzAIConciergeService($provider))
            ->processQuery('my car broke down on the highway', 1, 'customer_api', 'sess-2');

        $this->assertSame('resolved', $conversation->status);
        $this->assertStringContainsString('Urban Goodz Stranded', $conversation->response_text);
        $this->assertSame('stranded', $conversation->detectedIntent->slug);
    }

    /**
     * The "Get Help Now" hand-off button must not depend on the model's own
     * free-form classification getting this right -- a real, reproducible
     * gap found on-device: the AI answered with the correct calm/concerned
     * tone but classified the intent as something else, so no hand-off
     * button rendered. This proves the deterministic keyword override wins
     * regardless of what the AI classifier returns.
     */
    public function test_stranded_keyword_overrides_a_wrong_ai_classification(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'f_name' => 'First', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('urban_goodz_ai_intents')->insert([
            'slug' => 'stranded',
            'name' => 'Stranded / Roadside Help',
            'keywords' => json_encode(['stranded']),
            'response_template' => 'fallback template, not used on the AI path',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CONCIERGE);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('persona')->andReturn($persona);
        // The AI misclassifies -- this is the real failure mode observed live.
        $provider->shouldReceive('classifyIntent')->andReturn(['intent' => 'account_support', 'confidence' => 0.7, 'entities' => []]);
        $provider->shouldReceive('chatResult')->andReturn([
            'success' => true,
            'response' => 'Are you safe? Let me connect you.',
            'error_code' => null,
        ]);

        $conversation = (new UrbanGoodzAIConciergeService($provider))
            ->processQuery("I'm stranded on the highway", 1, 'customer_api', 'sess-override');

        $this->assertSame('stranded', $conversation->detectedIntent->slug);
    }

    public function test_marketplace_intent_asks_for_real_grounding_never_an_absent_key(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'f_name' => 'First', 'l_name' => 'Customer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CONCIERGE);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('persona')->andReturn($persona);
        $provider->shouldReceive('classifyIntent')->andReturn([
            'intent' => 'marketplace-search', 'confidence' => 0.9, 'entities' => ['search_query' => 'organic vegetables'],
        ]);

        $capturedContext = null;
        $provider->shouldReceive('chatResult')
            ->once()
            ->withArgs(function ($system, $user, $context, $history) use (&$capturedContext) {
                $capturedContext = $context;
                return true;
            })
            ->andReturn(['success' => true, 'response' => 'Here is what I found.', 'error_code' => null]);

        (new UrbanGoodzAIConciergeService($provider))
            ->processQuery('organic vegetables', 1, 'customer_api', 'sess-3');

        // The key must be present (even if empty, because the fixture has no
        // real inventory) -- its absence is what would let the model invent
        // a product instead of admitting it found nothing.
        $this->assertArrayHasKey('marketplace_results', $capturedContext);
        $this->assertIsArray($capturedContext['marketplace_results']);
    }

    public function test_concierge_rejects_unauthenticated_service_calls(): void
    {
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $service = new UrbanGoodzAIConciergeService($provider);

        $this->expectException(AuthenticationException::class);
        $service->processQuery('Show my orders', null, 'customer_api');
    }

    public function test_provider_failure_is_not_reported_as_success(): void
    {
        Config::set('urban_goodz_ai.provider', 'openai');
        Config::set('openai.api_key', 'test-key-that-is-not-a-real-secret');
        Config::set('openai.base_url', 'https://api.openai.test/v1');
        Http::fake([
            'api.openai.test/*' => Http::response(['error' => ['message' => 'provider unavailable']], 503),
        ]);

        $result = (new UrbanGoodzAIService())->chatResult('System prompt', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertSame('provider_error', $result['error_code']);
        $this->assertStringContainsString('No action was taken', $result['response']);
    }

    public function test_provider_sends_history_turns_as_real_messages_not_just_the_latest_query(): void
    {
        Config::set('urban_goodz_ai.provider', 'openai');
        Config::set('openai.api_key', 'test-key-that-is-not-a-real-secret');
        Config::set('openai.base_url', 'https://api.openai.test/v1');
        Http::fake([
            'api.openai.test/*' => Http::response(['choices' => [['message' => ['content' => 'Sure, here you go.']]]], 200),
        ]);

        (new UrbanGoodzAIService())->chatResult('System prompt', 'And the second one?', [], [
            ['role' => 'user', 'content' => 'Give me a recommendation.'],
            ['role' => 'assistant', 'content' => 'Here is my first pick.'],
        ]);

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'];
            $roles = array_column($messages, 'role');
            $contents = array_column($messages, 'content');

            return $roles === ['system', 'user', 'assistant', 'user']
                && $contents[1] === 'Give me a recommendation.'
                && $contents[2] === 'Here is my first pick.'
                && $contents[3] === 'And the second one?';
        });
    }

    public function test_skylar_chat_falls_back_to_real_command_center_counts_without_ai(): void
    {
        DB::table('admins')->insert(['id' => 1]);
        DB::table('business_needs')->insert([
            ['status' => 'open', 'severity' => 'high', 'created_at' => now(), 'updated_at' => now()],
            ['status' => 'open', 'severity' => 'low', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ai_tasks')->insert(['status' => 'running', 'created_at' => now(), 'updated_at' => now()]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CHIEF_OF_STAFF);
        $chat = new UrbanGoodzAIChiefOfStaffChatService(
            Mockery::mock(UrbanGoodzAIService::class, ['isConfigured' => false, 'persona' => $persona]),
            app(AiChiefOfStaffService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzAIExecutionService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzOperationalPlanner::class),
        );

        $conversation = $chat->processQuery('how are we doing', 1, 'D\'Andre Good', 'sky-sess-1');

        $this->assertSame('resolved', $conversation->status);
        // Real count from the two rows inserted above -- not a guessed/canned number.
        $this->assertStringContainsString('2 open business need(s)', $conversation->response_text);
        $this->assertSame(UrbanGoodzAIChiefOfStaffChatService::SOURCE, $conversation->source);
    }

    public function test_skylar_chat_memory_is_scoped_by_source_and_does_not_leak_from_monique(): void
    {
        DB::table('admins')->insert(['id' => 7]);
        DB::table('users')->insert([
            'id' => 7, 'f_name' => 'Collides', 'l_name' => 'WithAdminId', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Same numeric id (7), different actor entirely -- a Monique conversation
        // that must never surface as Skylar's memory.
        DB::table('urban_goodz_ai_conversations')->insert([
            'customer_id' => 7,
            'session_id' => 'shared-id-7',
            'query_text' => 'find me sneakers',
            'response_text' => 'Here are some sneakers.',
            'status' => 'resolved',
            'source' => 'customer_api',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CHIEF_OF_STAFF);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('persona')->andReturn($persona);

        $capturedHistory = null;
        $provider->shouldReceive('chatResult')
            ->once()
            ->withArgs(function ($system, $user, $context, $history) use (&$capturedHistory) {
                $capturedHistory = $history;
                return true;
            })
            ->andReturn(['success' => true, 'response' => 'Nothing urgent right now.', 'error_code' => null]);

        $chat = new UrbanGoodzAIChiefOfStaffChatService(
            $provider,
            app(AiChiefOfStaffService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzAIExecutionService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzOperationalPlanner::class),
        );
        $chat->processQuery('anything urgent?', 7, 'Real Admin', 'shared-id-7');

        $this->assertSame([], $capturedHistory);
    }

    public function test_skylar_chat_flags_urgent_language_for_the_prompt(): void
    {
        DB::table('admins')->insert(['id' => 3]);

        $persona = (new \App\Services\UrbanGoodz\AI\Persona\PersonaRegistry())->get(\App\Services\UrbanGoodz\AI\Persona\PersonaRegistry::CHIEF_OF_STAFF);
        $provider = Mockery::mock(UrbanGoodzAIService::class);
        $provider->shouldReceive('isConfigured')->andReturnTrue();
        $provider->shouldReceive('persona')->andReturn($persona);

        $capturedContext = null;
        $provider->shouldReceive('chatResult')
            ->once()
            ->withArgs(function ($system, $user, $context) use (&$capturedContext) {
                $capturedContext = $context;
                return true;
            })
            ->andReturn(['success' => true, 'response' => 'On it.', 'error_code' => null]);

        $chat = new UrbanGoodzAIChiefOfStaffChatService(
            $provider,
            app(AiChiefOfStaffService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzAIExecutionService::class),
            app(\App\Services\UrbanGoodz\UrbanGoodzOperationalPlanner::class),
        );
        $conversation = $chat->processQuery('we have an emergency, the site is down', 3, null, 'sky-sess-2');

        $this->assertTrue($capturedContext['flagged_as_urgent']);
        $this->assertTrue($conversation->metadata['flagged_as_urgent']);
    }

    public function test_explicit_customer_role_prevents_cross_table_id_role_collision(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'f_name' => 'Customer',
            'l_name' => 'One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('admins')->insert(['id' => 1]);
        Cache::flush();

        $result = app(AllowedActionRegistry::class)
            ->validateUserCanExecute('marketplace-search', 'search_marketplace', 1, 'customer');

        $this->assertTrue($result['allowed']);
        $this->assertSame('customer', $result['actor_role']);
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->timestamps();
        });
        Schema::create('admins', fn(Blueprint $table) => $table->id());
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ai_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('decision');
            $table->timestamps();
        });
        Schema::create('merchant_prospects', function (Blueprint $table) {
            $table->id();
            $table->string('prospect_status')->nullable();
            $table->decimal('attributed_revenue', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('business_needs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('severity')->nullable();
            $table->string('assigned_human_role')->nullable();
            $table->timestamps();
        });
        Schema::create('human_action_items', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('priority')->nullable();
            $table->string('assigned_role')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->string('order_status');
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->integer('stock')->default(0);
            $table->boolean('status')->default(true);
        });
        Schema::create('urban_goodz_payment_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('ledger_number')->nullable();
            $table->string('event_type')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->string('payment_status');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('approved')->default(0);
        });
        Schema::create('failed_jobs', fn(Blueprint $table) => $table->id());
        Schema::create('load_source_errors', function (Blueprint $table) {
            $table->id();
            $table->boolean('resolved')->default(false);
        });
        Schema::create('urban_goodz_ai_intents', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->text('response_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('urban_goodz_ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->text('query_text');
            $table->unsignedBigInteger('detected_intent_id')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('response_text');
            $table->string('status');
            $table->string('source');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
