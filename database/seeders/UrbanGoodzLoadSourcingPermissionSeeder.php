<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UrbanGoodzLoadSourcingPermissionSeeder extends Seeder
{
    protected array $permissions = [
        ['slug' => 'load_sourcing.view', 'label' => 'View Load Sourcing', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.search', 'label' => 'Run Sourcing Searches', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.run_manual', 'label' => 'Run Manual Sync', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.manage_saved_searches', 'label' => 'Manage Saved Searches', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.review_results', 'label' => 'Review Sourcing Results', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.approve', 'label' => 'Approve Sourced Loads', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.publish', 'label' => 'Publish to Load Board', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sourcing.assign', 'label' => 'Assign Loads to Drivers', 'module' => 'urban_goodz_load_sourcing'],
        ['slug' => 'load_sources.manage', 'label' => 'Manage Source Connectors', 'module' => 'urban_goodz_load_sources'],
        ['slug' => 'load_source_credentials.manage', 'label' => 'Manage Source Credentials', 'module' => 'urban_goodz_load_sources'],
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            DB::table('permission_modules')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'label' => $permission['label'],
                    'module' => $permission['module'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info(count($this->permissions) . ' load sourcing permissions seeded successfully.');
        $this->command->info('Slugs: ' . implode(', ', array_column($this->permissions, 'slug')));
    }
}
