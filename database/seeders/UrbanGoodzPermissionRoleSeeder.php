<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class UrbanGoodzPermissionRoleSeeder extends Seeder
{
    protected array $defaultModules = [
        'urban_goodz_platform_core',
        'urban_goodz_control_center',
        'urban_goodz_order_anywhere',
        'urban_goodz_files',
        'urban_goodz_ai_concierge',
        'urban_goodz_dispatch',
        'urban_goodz_business_types',
        'urban_goodz_capabilities',
        'urban_goodz_car_rental',
        'urban_goodz_rentals',
    ];

    public function run(): void
    {
        AdminRole::updateOrCreate(
            ['name' => 'Urban Goodz Operations'],
            [
                'modules' => json_encode($this->defaultModules),
                'status' => 1,
            ]
        );

        $this->command->info('Urban Goodz Operations role created/updated successfully.');
        $this->command->info('Modules: ' . implode(', ', $this->defaultModules));
        $this->command->info('Note: urban_goodz_payments is NOT included in default modules.');
    }
}
