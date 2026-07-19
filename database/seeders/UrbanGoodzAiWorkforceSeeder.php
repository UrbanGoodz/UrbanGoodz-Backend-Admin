<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use App\Models\AiOutreachTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UrbanGoodzAiWorkforceSeeder extends Seeder
{
    public function run(): void
    {
        // Register the merchant_prospects migration if it's not registered
        DB::table('migrations')->insertOrIgnore([
            'migration' => '2026_07_19_160400_create_merchant_prospects_table',
            'batch' => 80,
        ]);

        // Seed default AI Agents
        $agents = [
            [
                'name' => 'AI Chief of Staff',
                'slug' => 'chief_of_staff',
                'role' => 'Chief of Staff',
                'description' => 'Coordinates active AI workforce tasks, summarizes business needs, escalates human action items, and generates executive briefings.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_EXECUTE,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.2],
                'confidence_threshold' => 0.8000,
                'daily_task_limit' => 200,
                'daily_message_limit' => 50,
                'daily_token_limit' => 200000,
                'kill_switch' => false,
            ],
            [
                'name' => 'AI Merchant Acquisition Employee',
                'slug' => 'merchant_acquisition_employee',
                'role' => 'Merchant Acquisition',
                'description' => 'Monitors Order Anywhere demand, normalizing business entities, scoring prospect value, and drafting personalized outreach sequences.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_RECOMMEND,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.3],
                'confidence_threshold' => 0.7500,
                'daily_task_limit' => 150,
                'daily_message_limit' => 100,
                'daily_token_limit' => 150000,
                'kill_switch' => false,
            ],
            [
                'name' => 'Customer Companion Assistant',
                'slug' => 'customer_companion',
                'role' => 'Customer Companion',
                'description' => 'Floating customer-facing assistant for product discovery, order anywhere creation, and localized promotions.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_EXECUTE,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.5],
                'confidence_threshold' => 0.7000,
                'daily_task_limit' => 500,
                'daily_message_limit' => 0,
                'daily_token_limit' => 500000,
                'kill_switch' => false,
            ],
            [
                'name' => 'Driver Assistant',
                'slug' => 'driver_assistant',
                'role' => 'Driver Assistant',
                'description' => 'Recommends active load/route opportunities to drivers, provides payout explanations, and assists with delivery exceptions.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_EXECUTE,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.4],
                'confidence_threshold' => 0.8000,
                'daily_task_limit' => 400,
                'daily_message_limit' => 0,
                'daily_token_limit' => 400000,
                'kill_switch' => false,
            ],
            [
                'name' => 'Vendor Assistant',
                'slug' => 'vendor_assistant',
                'role' => 'Vendor Assistant',
                'description' => 'Aids vendors with onboarding verification, inventory management, settlement timing explanations, and stock alerts.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_EXECUTE,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.3],
                'confidence_threshold' => 0.8000,
                'daily_task_limit' => 300,
                'daily_message_limit' => 0,
                'daily_token_limit' => 300000,
                'kill_switch' => false,
            ],
            [
                'name' => 'Business Assistant',
                'slug' => 'business_assistant',
                'role' => 'Business Assistant',
                'description' => 'Supports Business Client Portal tenants with route manifest queries, staff scheduling alerts, and invoice tracking under strict isolation.',
                'status' => 'active',
                'autonomy_level' => AiAgent::LEVEL_EXECUTE,
                'provider_config' => ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.3],
                'confidence_threshold' => 0.8500,
                'daily_task_limit' => 300,
                'daily_message_limit' => 0,
                'daily_token_limit' => 300000,
                'kill_switch' => false,
            ],
        ];

        foreach ($agents as $agentData) {
            AiAgent::updateOrCreate(['slug' => $agentData['slug']], $agentData);
        }

        // Seed outreach templates
        $templates = [
            [
                'slug' => 'demand_introduction',
                'name' => 'Merchant Acquisition - Day 0 Intro',
                'subject' => 'Partnership Opportunity for {{business_name}}',
                'body' => "Hi {{business_name}},\n\nWe have received {{request_count}} delivery requests from {{customer_count}} customers looking to order from your business on Urban Goodz.\n\nJoin our platform today: {{onboarding_url}}",
                'category' => 'outreach',
                'sequence_day' => 0,
                'is_active' => true,
            ],
            [
                'slug' => 'followup_1',
                'name' => 'Merchant Acquisition - Day 3 Followup',
                'subject' => 'Follow up: Partnership Opportunity for {{business_name}}',
                'body' => "Hi {{business_name}},\n\nJust following up on our previous note. We still see active customer demand for your business. Sign up here: {{onboarding_url}}",
                'category' => 'outreach',
                'sequence_day' => 3,
                'is_active' => true,
            ],
            [
                'slug' => 'followup_2',
                'name' => 'Merchant Acquisition - Day 7 Demand Reminder',
                'subject' => 'Active Customer Demand for {{business_name}}',
                'body' => "Hi {{business_name}},\n\nCustomers are waiting for your products. Register today: {{onboarding_url}}",
                'category' => 'outreach',
                'sequence_day' => 7,
                'is_active' => true,
            ],
            [
                'slug' => 'final_followup',
                'name' => 'Merchant Acquisition - Day 12 Final Notice',
                'subject' => 'Final Notice: Customer Demand for {{business_name}}',
                'body' => "Hi {{business_name}},\n\nThis is our final follow-up. We will hold your customer demand page for 5 more days before removing it. Register here: {{onboarding_url}}",
                'category' => 'outreach',
                'sequence_day' => 12,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tmplData) {
            AiOutreachTemplate::updateOrCreate(['slug' => $tmplData['slug']], $tmplData);
        }
    }
}
