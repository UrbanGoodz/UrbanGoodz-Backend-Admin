<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AiChiefOfStaffService;
use App\Services\UrbanGoodz\AllowedActionRegistry;
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
        $provider->shouldReceive('isConfigured')->once()->andReturnFalse();

        $conversation = (new UrbanGoodzAIConciergeService($provider))
            ->processQuery('Track my delivery', 1);

        $this->assertSame('resolved', $conversation->status);
        $this->assertStringContainsString('Order #101', $conversation->response_text);
        $this->assertStringNotContainsString('Order #999', $conversation->response_text);
        $this->assertSame('deterministic_database', $conversation->metadata['response_source']);
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
