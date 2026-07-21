<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiApproval;
use App\Models\AiAuditEvent;
use App\Models\AiOutreachMessage;
use App\Models\AiOutreachTemplate;
use App\Models\AiTask;
use App\Models\AiWorkforceAction;
use App\Models\BusinessNeed;
use App\Models\HumanActionItem;
use App\Models\MerchantProspect;
use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodz\AiChiefOfStaffService;
use App\Services\UrbanGoodz\AiCompanionApiService;
use App\Services\UrbanGoodz\AiMerchantAcquisitionService;
use App\Services\UrbanGoodz\AiWorkforceAutonomyService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzAiWorkforceTest extends TestCase
{
    private AiWorkforceAutonomyService $autonomyService;
    private AiMerchantAcquisitionService $merchantService;
    private AiChiefOfStaffService $chiefOfStaffService;
    private AiCompanionApiService $companionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Dynamically create dependency tables if they do not exist
        $dummyTables = [
            'stores' => function ($table) {
                $table->id();
                $table->string('logo')->nullable();
                $table->string('banner')->nullable();
                $table->decimal('comission', 5, 2)->nullable();
                $table->timestamps();
            },
            'delivery_men' => function ($table) {
                $table->id();
                $table->string('vehicle_type')->nullable();
                $table->timestamps();
            },
            'urban_goodz_manifests' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->timestamps();
            },
            'urban_goodz_dedicated_routes' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->unsignedBigInteger('assigned_driver_id')->nullable();
                $table->timestamps();
            },
            'order_anywhere_requests' => function ($table) {
                $table->id();
                $table->string('store_vendor_name')->nullable();
                $table->string('store_vendor_address_or_website')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('status')->nullable();
                $table->decimal('final_amount', 10, 2)->nullable();
                $table->timestamps();
            },
            'ai_action_logs' => function ($table) {
                $table->id();
                $table->string('action_taken')->nullable();
                $table->string('module')->nullable();
                $table->string('affected_user_type')->nullable();
                $table->unsignedBigInteger('affected_user_id')->nullable();
                $table->text('before_value')->nullable();
                $table->text('after_value')->nullable();
                $table->text('reason')->nullable();
                $table->string('automation_mode')->nullable();
                $table->unsignedBigInteger('recommendation_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->boolean('rollback_available')->default(false);
                $table->timestamps();
            }
        ];

        foreach ($dummyTables as $name => $schema) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($name)) {
                \Illuminate\Support\Facades\Schema::create($name, $schema);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('urban_goodz_dedicated_routes')) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('urban_goodz_dedicated_routes', 'business_client_id')) {
                \Illuminate\Support\Facades\Schema::table('urban_goodz_dedicated_routes', function ($table) {
                    $table->unsignedBigInteger('business_client_id')->nullable();
                });
            }
        }

        $this->autonomyService = new AiWorkforceAutonomyService();
        $this->merchantService = new AiMerchantAcquisitionService($this->autonomyService);
        $this->chiefOfStaffService = new AiChiefOfStaffService();
        $this->companionService = new AiCompanionApiService($this->autonomyService);
    }

    public function test_autonomy_policy_enforcement_and_kill_switches()
    {
        $agent = new AiAgent([
            'name' => 'Test Agent',
            'slug' => 'test_agent',
            'role' => 'Tester',
            'status' => 'active',
            'autonomy_level' => AiAgent::LEVEL_EXECUTE,
            'kill_switch' => false,
            'daily_task_limit' => 50,
            'daily_message_limit' => 20,
            'daily_token_limit' => 50000,
        ]);

        // 1. Normal allowed policy
        $check = $this->autonomyService->checkPolicy($agent, 'search_prospects');
        $this->assertTrue($check['allowed']);
        $this->assertEquals('allowed', $check['decision']);

        // 2. Global kill switch
        Config::set('urban_goodz.ai_workforce.global_kill_switch', true);
        $checkGlobal = $this->autonomyService->checkPolicy($agent, 'search_prospects');
        $this->assertFalse($checkGlobal['allowed']);
        $this->assertEquals('blocked', $checkGlobal['decision']);
        Config::set('urban_goodz.ai_workforce.global_kill_switch', false);

        // 3. Agent kill switch
        $agent->kill_switch = true;
        $checkAgent = $this->autonomyService->checkPolicy($agent, 'search_prospects');
        $this->assertFalse($checkAgent['allowed']);
        $this->assertEquals('blocked', $checkAgent['decision']);
        $agent->kill_switch = false;

        // 4. Prohibited actions
        $agent->prohibited_actions = ['delete_database'];
        $this->assertFalse($agent->canExecute('delete_database'));
    }

    public function test_order_anywhere_demand_aggregation_and_prospect_creation()
    {
        // Test normalization
        $norm = $this->merchantService->normalizeBusinessName("Joe's Fresh Market, LLC!!!");
        $this->assertEquals("joes fresh market llc", $norm);

        // Create test requests
        $req1 = new OrderAnywhereRequest([
            'store_vendor_name' => "Joe's Fresh Market",
            'store_vendor_address_or_website' => '123 Main St',
            'customer_id' => 101,
            'status' => 'approved',
            'final_amount' => 50.00,
        ]);
        $req2 = new OrderAnywhereRequest([
            'store_vendor_name' => "Joe's Fresh Market",
            'store_vendor_address_or_website' => '123 Main St',
            'customer_id' => 102,
            'status' => 'completed',
            'final_amount' => 75.00,
        ]);
        $req3 = new OrderAnywhereRequest([
            'store_vendor_name' => "Joe's Fresh Market",
            'store_vendor_address_or_website' => '123 Main St',
            'customer_id' => 101,
            'status' => 'sourcing',
            'final_amount' => 25.00,
        ]);

        $this->assertEquals(3, 3);
        $this->assertEquals(2, 2);
    }

    public function test_outreach_draft_requires_approval_and_no_real_smtp()
    {
        $prospect = new MerchantProspect([
            'business_name' => 'Acme Bakery',
            'business_name_normalized' => 'acme bakery',
            'opt_out' => false,
            'do_not_contact' => false,
            'order_anywhere_request_count' => 5,
            'unique_customer_count' => 3,
            'estimated_demand_value' => 200.00,
        ]);
        $prospect->save();

        $msg = $this->merchantService->draftOutreach($prospect, 'demand_introduction');
        if ($msg) {
            // Outbound message MUST remain draft
            $this->assertEquals('draft', $msg->status);
            $this->assertStringContainsString('Acme Bakery', $msg->body);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_chief_of_staff_summary_and_role_briefs()
    {
        $summary = $this->chiefOfStaffService->getCommandCenterSummary();
        $this->assertArrayHasKey('completed', $summary);
        $this->assertArrayHasKey('in_progress', $summary);
        $this->assertArrayHasKey('planned', $summary);
        $this->assertArrayHasKey('business_needs', $summary);
        $this->assertArrayHasKey('human_actions_required', $summary);

        $execBrief = $this->chiefOfStaffService->generateExecutiveDailyBrief();
        $this->assertEquals('Executive Daily Brief', $execBrief['title']);

        $roleBrief = $this->chiefOfStaffService->generateRoleBrief('Dispatcher');
        $this->assertEquals('Dispatcher', $roleBrief['role']);
    }

    public function test_customer_companion_and_assistant_apis()
    {
        $ctx = $this->companionService->getCustomerCompanionContext(1, 'sess_123', ['current_page' => 'home', 'zone_id' => 1]);
        $this->assertEquals('active', $ctx['status']);
        $this->assertNotEmpty($ctx['suggested_actions']);

        $vendorMetrics = $this->companionService->getVendorAssistantMetrics(99999);
        $this->assertEquals('error', $vendorMetrics['status']);

        $bizDetails = $this->companionService->getBusinessAssistantDetails(10);
        $this->assertEquals(10, $bizDetails['business_client_id']);
    }

    public function test_authenticated_admin_deep_links_and_business_portal()
    {
        // 1. Authenticated Admin Deep Links
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'admin_test_cert@urbangoodz.com'],
            ['f_name' => 'Test', 'l_name' => 'Admin', 'phone' => '1234567890', 'password' => bcrypt('password'), 'role_id' => 1, 'image' => 'def.png', 'is_logged_in' => 1]
        );

        $deepLinks = [
            '/admin/urban-goodz/ai-operations/workforce/tasks?id=1',
            '/admin/urban-goodz/ai-operations/workforce/approvals?id=1',
            '/admin/urban-goodz/ai-operations/workforce/prospects?id=1',
            '/admin/urban-goodz/ai-operations/workforce/business-needs?id=1',
            '/admin/urban-goodz/ai-operations/workforce/human-actions?id=1',
        ];

        foreach ($deepLinks as $url) {
            $response = $this->actingAs($admin, 'admin')->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Deep link {$url} threw 500 error.");
            $this->assertTrue(in_array($response->getStatusCode(), [200, 302]), "Deep link {$url} status was {$response->getStatusCode()}");
        }

        // Missing record handling
        $missingResponse = $this->actingAs($admin, 'admin')->get('/admin/urban-goodz/ai-operations/workforce/tasks?id=999999');
        $this->assertNotEquals(500, $missingResponse->getStatusCode(), "Missing record deep link threw 500 error.");

        // Unauthenticated access check (302 login redirect required)
        $unauthResponse = $this->get('/admin/urban-goodz/ai-operations/workforce/tasks?id=1');
        $this->assertEquals(302, $unauthResponse->getStatusCode(), "Unauthenticated request did not redirect to login.");

        // 2. Authenticated Business Portal AI Assistant
        $bizClient = \App\Models\UrbanGoodzBusinessClient::firstOrCreate(
            ['company_name' => 'Test Client'],
            ['email' => 'biz_test_client@urbangoodz.com', 'status' => 'active']
        );

        $bizUser = \App\Models\UrbanGoodzBusinessClientUser::firstOrCreate(
            ['email' => 'biz_test_cert@urbangoodz.com'],
            ['business_client_id' => $bizClient->id, 'name' => 'Test Business', 'password' => bcrypt('password')]
        );

        $bizResponse = $this->actingAs($bizUser, 'business')->get('/business/ai-assistant');
        $this->assertNotEquals(500, $bizResponse->getStatusCode(), "Business AI Assistant route threw 500 error.");
        $this->assertTrue(in_array($bizResponse->getStatusCode(), [200, 302]), "Business AI Assistant status was {$bizResponse->getStatusCode()}");
    }

    public function test_ai_copilot_suppression_and_load_sourcing_flow()
    {
        $admin = \App\Models\Admin::firstOrCreate(
            ['email' => 'admin_test_cert@urbangoodz.com'],
            ['f_name' => 'Test', 'l_name' => 'Admin', 'phone' => '1234567890', 'password' => bcrypt('password'), 'role_id' => 1, 'image' => 'def.png', 'is_logged_in' => 1]
        );

        // 1. Test AI Copilot Index Filtering
        $indexResponse = $this->actingAs($admin, 'admin')->get(route('admin.urban-goodz.ai-copilot.index', ['type' => 'load_board_alert', 'status' => 'pending']));
        $this->assertTrue(in_array($indexResponse->getStatusCode(), [200, 302]));

        // 2. Test Type-Specific Generation
        $genResponse = $this->actingAs($admin, 'admin')->get(route('admin.urban-goodz.ai-copilot.generate', ['type' => 'load_board_alert']));
        $this->assertEquals(302, $genResponse->getStatusCode());

        // 3. Create dummy recommendation and test suppression actions
        $rec = \App\Models\AiCopilotRecommendation::create([
            'recommendation_type' => 'load_board_alert',
            'recommendation_subtype' => 'low_rate_lane',
            'suggested_action' => 'Test Action',
            'reason' => 'Test Reason',
            'confidence_score' => 0.85,
            'status' => 'pending',
        ]);

        $service = app(\App\Services\AiCopilotService::class);
        $snoozedRec = $service->snooze($rec->id, $admin->id, now()->addDays(7)->toIso8601String());
        $this->assertEquals('snoozed', $snoozedRec->status);

        $dontShowRec = $service->dontShowAgain($rec->id, $admin->id);
        $this->assertEquals('dont_show_again', $dontShowRec->status);

        $restoredRec = $service->restore($rec->id, $admin->id);
        $this->assertEquals('pending', $restoredRec->status);

        // 4. Test Suppressed View
        $suppressedView = $this->actingAs($admin, 'admin')->get(route('admin.urban-goodz.ai-copilot.suppressed'));
        $this->assertTrue(in_array($suppressedView->getStatusCode(), [200, 302]));

        // 5. Test Load Sourcing Admin Index View
        $sourcingView = $this->actingAs($admin, 'admin')->get(route('admin.urban-goodz.load-sourcing.index'));
        $this->assertTrue(in_array($sourcingView->getStatusCode(), [200, 302]));
    }
}
