<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\DeliveryMan;

class CreateTestDriver extends Command
{
    protected $signature = 'urban-goods:create-test-driver
                            {--email= : Override test driver email}
                            {--password= : Override test driver password}
                            {--zone= : Zone ID (default: 2)}';
    protected $description = 'Create a test driver for UrbanGoodz Driver App acceptance testing';

    public function handle()
    {
        $this->info('=== UrbanGoodz Driver Test Setup ===');
        $this->newLine();

        $email = $this->option('email') ?: 'test.driver001@urbangoodzdelivery.com';
        $password = $this->option('password') ?: 'TestDriver2026!$';
        $zoneId = (int)($this->option('zone') ?: 2);

        // Step 1: Vehicle — use DB facade to avoid model dependency issues
        $this->info('Step 1: Checking vehicles...');
        $vehicle = DB::table('vehicles')->where('type', 'car')->where('status', 1)->first();
        if (!$vehicle) {
            $vehicleId = DB::table('vehicles')->insertGetId([
                'type' => 'car',
                'capacity' => 4,
                'min_cap' => 1,
                'avg_cap' => 4,
                'max_cap' => 6,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("  Created vehicle: car (ID: {$vehicleId})");
        } else {
            $vehicleId = $vehicle->id;
            $this->info("  Found vehicle: {$vehicle->type} (ID: {$vehicleId})");
        }

        // Step 2: Driver — use DeliveryMan model (always available)
        $this->info('Step 2: Creating test driver...');
        $dm = DeliveryMan::updateOrCreate(
            ['email' => $email],
            [
                'f_name' => 'Test',
                'l_name' => 'Driver001',
                'phone' => '+15551230001',
                'identity_type' => 'passport',
                'identity_number' => 'TEST-DM-001-IDENTITY',
                'password' => Hash::make($password),
                'zone_id' => $zoneId,
                'earning' => 15.00,
                'vehicle_id' => $vehicleId,
                'type' => 'zone_wise',
                'application_status' => 'approved',
                'status' => 1,
                'active' => 1,
                'available' => 1,
                'is_delivery' => 1,
                'is_ride' => 0,
                'image' => 'def.png',
                'identity_image' => json_encode([]),
                'auth_token' => Str::random(120),
                'ref_code' => 'TEST' . Str::random(8),
                'fcm_token' => null,
                'current_orders' => 0,
                'assigned_order_count' => 0,
                'loyalty_point' => 0,
            ]
        );

        $this->info("  Driver: {$dm->f_name} {$dm->l_name} (ID: {$dm->id})");
        $this->info("  Email: {$dm->email}");
        $this->info("  Phone: {$dm->phone}");
        $this->info("  Zone: {$dm->zone_id}");
        $this->info("  Vehicle: {$dm->vehicle_id}");
        $this->info("  Status: {$dm->application_status} | Active: {$dm->active}");

        $this->newLine();
        $this->info('========================================');
        $this->info('TEST DRIVER CREDENTIALS');
        $this->info('========================================');
        $this->info("Email:     {$dm->email}");
        $this->info("Password:  {$password}");
        $this->info("Auth Token: {$dm->auth_token}");
        $this->info('========================================');
        $this->newLine();

        $this->info('To test the driver app:');
        $this->info('  1. Open the UrbanGoodz Driver APK');
        $this->info('  2. Paste the auth_token above into the token field');
        $this->info('  3. The driver should load with test data');
        $this->newLine();

        $this->info('To test API endpoints:');
        $this->info("  GET /api/v1/urban-goodz/driver/business-jobs?token={$dm->auth_token}");

        return 0;
    }
}
