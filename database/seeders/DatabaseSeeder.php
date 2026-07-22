<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call([
            UserSeeder::class,
            MeasurementRequestSeeder::class,
            UrbanGoodzBusinessTypeSeeder::class,
            UrbanGoodzAIIntentSeeder::class,
            UrbanGoodzDriverPricingPolicySeeder::class,
            UrbanGoodzLoadBoardSeeder::class,
            UrbanGoodzPermissionRoleSeeder::class,
            UrbanGoodzIngestionSeeder::class,
            UrbanGoodzTestVendorSeeder::class,
            UrbanGoodzAiWorkforceSeeder::class,
        ]);
    }
}
