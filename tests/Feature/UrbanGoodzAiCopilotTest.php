<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzAgeVerification;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzRoutePackage;
use App\Services\AiCopilotService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UrbanGoodzAiCopilotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create all needed dummy tables in SQLite
        $dummyTables = [
            'orders' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->string('order_status')->nullable();
                $table->string('order_type')->nullable();
                $table->decimal('order_amount', 10, 2)->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'urban_goodz_route_packages' => function ($table) {
                $table->id();
                $table->string('tracking_id')->nullable();
                $table->string('status')->nullable();
                $table->unsignedBigInteger('dedicated_route_id')->nullable();
                $table->unsignedBigInteger('manifest_id')->nullable();
                $table->timestamp('dropoff_scanned_at')->nullable();
                $table->unsignedBigInteger('dropoff_scanned_by')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'urban_goodz_business_client_jobs' => function ($table) {
                $table->id();
                $table->string('job_number')->nullable();
                $table->string('pickup_name')->nullable();
                $table->unsignedBigInteger('assigned_delivery_man_id')->nullable();
                $table->string('status')->nullable();
                $table->unsignedBigInteger('business_client_id')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'order_anywhere_requests' => function ($table) {
                $table->id();
                $table->string('status')->nullable();
                $table->text('description')->nullable();
                $table->string('customer_name')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'urban_goodz_age_verifications' => function ($table) {
                $table->id();
                $table->string('verification_status')->nullable();
                $table->string('verification_attempted_at')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('refusal_reason')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'urban_goodz_load_board_loads' => function ($table) {
                $table->id();
                $table->string('status')->nullable();
                $table->decimal('payout_amount', 10, 2)->nullable();
                $table->decimal('rate_per_mile', 10, 2)->nullable();
                $table->string('origin_city')->nullable();
                $table->string('origin_state')->nullable();
                $table->string('destination_city')->nullable();
                $table->string('destination_state')->nullable();
                $table->string('load_number')->nullable();
                $table->decimal('distance_miles', 10, 2)->nullable();
                $table->string('load_type')->nullable();
                $table->string('equipment_type')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'urban_goodz_dedicated_routes' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('assigned_driver_id')->nullable();
                $table->string('status')->nullable();
                $table->string('route_name')->nullable();
                $table->integer('total_packages')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'delivery_men' => function ($table) {
                $table->id();
                $table->boolean('active')->default(true);
                $table->string('application_status')->nullable();
                $table->integer('current_orders')->nullable();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'ai_copilot_recommendations' => function ($table) {
                $table->id();
                $table->string('recommendation_type', 100)->nullable();
                $table->string('recommendation_subtype', 100)->nullable();
                $table->nullableMorphs('relatable');
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->unsignedBigInteger('route_id')->nullable()->index();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->text('suggested_action')->nullable();
                $table->text('reason')->nullable();
                $table->decimal('confidence_score', 5, 2)->nullable();
                $table->string('status', 50)->default('pending');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            },
            'ai_copilot_settings' => function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('value')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'ai_module_automation_settings' => function ($table) {
                $table->id();
                $table->string('module')->nullable();
                $table->boolean('enabled')->default(false);
                $table->string('automation_mode')->nullable();
                $table->decimal('min_confidence_score', 5, 2)->nullable();
                $table->decimal('max_auto_action_amount', 10, 2)->nullable();
                $table->string('max_risk_level')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'admins' => function ($table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->integer('role_id')->nullable();
                $table->string('created_at')->nullable();
                $table->string('updated_at')->nullable();
            },
            'data_settings' => function ($table) {
                $table->id();
                $table->string('key')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            },
            'business_settings' => function ($table) {
                $table->id();
                $table->string('key')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            },
            'storages' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('data_id')->nullable();
                $table->string('data_type')->nullable();
                $table->string('key')->nullable();
                $table->string('value')->nullable();
                $table->timestamps();
            }
        ];

        Schema::disableForeignKeyConstraints();
        foreach ($dummyTables as $name => $schema) {
            Schema::dropIfExists($name);
            Schema::create($name, $schema);
        }
        Schema::enableForeignKeyConstraints();

        // 2. Set default Copilot Settings
        Config::set('urban_goodz.ai_copilot.mode', 'recommend_only');
        \Illuminate\Support\Facades\DB::table('ai_copilot_settings')->updateOrInsert(
            ['key' => 'ai_ops_enabled'],
            ['value' => 'recommend_only']
        );
    }

    public function test_timestamps_normalization_and_stuck_orders(): void
    {
        // Clear tables
        Order::truncate();
        UrbanGoodzRoutePackage::truncate();
        UrbanGoodzBusinessClientJob::truncate();

        // 1. Carbon timestamp (stuck order)
        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'order_status' => 'pending',
            'order_type' => 'delivery',
            'order_amount' => 50.00,
            'created_at' => now()->subHours(10),
        ]);

        // 2. String timestamp (stuck order)
        Order::insert([
            'order_status' => 'confirmed',
            'order_type' => 'delivery',
            'order_amount' => 60.00,
            'created_at' => now()->subHours(8)->toDateTimeString(),
        ]);

        // 3. Null timestamp (stuck order)
        Order::insert([
            'order_status' => 'pending',
            'order_type' => 'delivery',
            'order_amount' => 70.00,
            'created_at' => null,
        ]);

        // 4. Stalled package with string/null/Carbon updated_at
        UrbanGoodzRoutePackage::insert([
            [
                'tracking_id' => 'PKG-111',
                'status' => 'failed',
                'updated_at' => now()->subHours(5)->toDateTimeString(),
                'created_at' => now()->subHours(5)->toDateTimeString(),
            ],
            [
                'tracking_id' => 'PKG-222',
                'status' => 'admin_review',
                'updated_at' => null,
                'created_at' => null,
            ]
        ]);

        // 5. Unassigned job with string/null/Carbon created_at
        UrbanGoodzBusinessClientJob::insert([
            [
                'job_number' => 'JOB-123',
                'pickup_name' => 'Pickup A',
                'status' => 'pending',
                'created_at' => now()->subHours(6)->toDateTimeString(),
            ]
        ]);

        $service = app(AiCopilotService::class);
        $results = $service->detectStuckOrders();

        $this->assertEquals('stuck_order', $results['type']);
        $this->assertGreaterThan(0, $results['count']);
    }

    public function test_artisan_command_generates_recommendations(): void
    {
        $this->artisan('ai-copilot:generate')
            ->assertExitCode(0);
    }

    public function test_generate_route_does_not_throw_500(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'copilot-test-admin@urbangoodz.com'],
            ['f_name' => 'Copilot', 'l_name' => 'Admin', 'phone' => '5551009999', 'password' => bcrypt('password'), 'role_id' => 1]
        );

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/urban-goodz/ai-copilot/generate');

        // Should redirect back to settings or index
        $response->assertStatus(302);
    }
}
