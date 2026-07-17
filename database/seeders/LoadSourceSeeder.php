<?php

namespace Database\Seeders;

use App\Models\LoadSource;
use App\Models\LoadSourcingSetting;
use Illuminate\Database\Seeder;

class LoadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['source_key' => 'urban_goodz_internal', 'name' => 'Urban Goodz Internal', 'type' => 'internal', 'enabled' => true, 'api_status' => 'connected', 'partnership_status' => 'active', 'supports_bidding' => true, 'supports_booking' => true, 'supports_automation' => true, 'description' => 'Urban Goodz internal load board'],
            ['source_key' => 'email_inbox', 'name' => 'Email Load Alerts', 'type' => 'email', 'enabled' => true, 'api_status' => 'connected', 'partnership_status' => 'active', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'Load alert emails ingested automatically'],
            ['source_key' => 'manual_import', 'name' => 'Manual Import', 'type' => 'manual', 'enabled' => true, 'api_status' => 'connected', 'partnership_status' => 'active', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'Admin/dispatcher manual load entry'],
            ['source_key' => 'dat', 'name' => 'DAT', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => true, 'supports_booking' => false, 'description' => 'DAT load board', 'source_url' => 'https://www.dat.com'],
            ['source_key' => 'truckstop', 'name' => 'Truckstop.com', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => true, 'supports_booking' => false, 'description' => 'Truckstop load board', 'source_url' => 'https://www.truckstop.com'],
            ['source_key' => 'trulos', 'name' => 'Trulos', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'Trulos freight platform — awaiting partner API access', 'source_url' => 'https://www.trulos.com'],
            ['source_key' => 'tb_load', 'name' => 'TB Load', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'TB Load board — awaiting partner API access'],
            ['source_key' => 'direct_freight', 'name' => 'Direct Freight', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'Direct Freight load board — awaiting partner API access'],
            ['source_key' => 'trucker_path', 'name' => 'Trucker Path / TruckLoads', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'Trucker Path TruckLoads — awaiting partner API access'],
            ['source_key' => 'trucksmarter', 'name' => 'TruckSmarter', 'type' => 'api', 'enabled' => false, 'api_status' => 'awaiting_credentials', 'partnership_status' => 'pending', 'supports_bidding' => false, 'supports_booking' => false, 'description' => 'TruckSmarter — awaiting partner API access'],
        ];

        foreach ($sources as $data) {
            LoadSource::updateOrCreate(
                ['source_key' => $data['source_key']],
                $data
            );
        }

        $defaults = [
            'platform_fee_percent' => ['value' => '12.0', 'type' => 'decimal', 'description' => 'Platform fee percentage deducted from gross rate'],
            'fuel_cost_per_mile' => ['value' => '0.75', 'type' => 'decimal', 'description' => 'Estimated fuel cost per mile'],
            'toll_estimation_per_mile' => ['value' => '0.05', 'type' => 'decimal', 'description' => 'Estimated toll cost per mile'],
            'default_max_deadhead_miles' => ['value' => '100', 'type' => 'integer', 'description' => 'Default maximum deadhead distance in miles'],
            'minimum_confidence_threshold' => ['value' => '30', 'type' => 'integer', 'description' => 'Minimum AI score threshold to generate recommendation'],
            'auto_alert_threshold' => ['value' => '70', 'type' => 'integer', 'description' => 'Score threshold for automatic driver alerts'],
        ];

        foreach ($defaults as $key => $data) {
            LoadSourcingSetting::updateOrCreate(
                ['setting_key' => $key],
                $data
            );
        }

        $weights = ['profit' => 25, 'rate_per_mile' => 15, 'deadhead' => 15, 'equipment_match' => 15, 'schedule_feasibility' => 10, 'broker_quality' => 10, 'return_load' => 5, 'driver_preference' => 5];
        LoadSourcingSetting::updateOrCreate(
            ['setting_key' => 'scoring_weights'],
            ['setting_value' => json_encode($weights), 'setting_type' => 'json', 'description' => 'AI scoring weight distribution (must total 100)']
        );
    }
}
