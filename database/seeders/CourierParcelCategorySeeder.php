<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ParcelCategory;
use Illuminate\Database\Seeder;

/**
 * The Courier/Parcel Delivery module (module_type='parcel') existed with
 * zero rows in parcel_categories - the customer app's category screen
 * correctly showed "No parcel category found" because there was, in fact,
 * no data. This seeds a reasonable default set so the module is usable;
 * names/pricing are a placeholder starting point for the business to edit
 * via the admin panel, not a final content decision.
 */
class CourierParcelCategorySeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::where('module_type', 'parcel')->first();
        if (!$module) {
            $this->command->warn('No parcel-type module found, skipping.');
            return;
        }

        $categories = [
            ['name' => 'Documents', 'description' => 'Letters, paperwork, and important documents'],
            ['name' => 'Small Package', 'description' => 'Small parcels and boxes up to 5 lbs'],
            ['name' => 'Fragile Items', 'description' => 'Items requiring careful, cushioned handling'],
            ['name' => 'Electronics', 'description' => 'Phones, laptops, and other electronic devices'],
            ['name' => 'Food & Perishables', 'description' => 'Time-sensitive food and perishable items'],
            ['name' => 'Gifts & Flowers', 'description' => 'Gift packages, flowers, and special deliveries'],
        ];

        foreach ($categories as $cat) {
            ParcelCategory::firstOrCreate(
                ['name' => $cat['name'], 'module_id' => $module->id],
                [
                    'description' => $cat['description'],
                    'status' => 1,
                    'orders_count' => 0,
                    'parcel_per_km_shipping_charge' => 1.00,
                    'parcel_minimum_shipping_charge' => 5.00,
                ]
            );
        }
    }
}
