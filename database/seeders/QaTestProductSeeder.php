<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * A single real, orderable product for the QA Test Store, so the full
 * customer -> vendor -> driver order lifecycle can actually be exercised
 * end to end rather than stopping at "store loads, empty".
 */
class QaTestProductSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('name', 'QA Test Store')->first();
        if (!$store) {
            $this->command->warn('QA Test Store not found, run QaTestAccountsSeeder first.');
            return;
        }

        Item::updateOrCreate(
            ['name' => 'QA Test T-Shirt', 'store_id' => $store->id],
            [
                'description' => 'A test product for end-to-end order flow verification.',
                'category_id' => 85,
                'price' => 19.99,
                'tax' => 0,
                'discount' => 0,
                'status' => 1,
                'store_id' => $store->id,
                'module_id' => $store->module_id,
                'stock' => 100,
                'is_approved' => 1,
                'veg' => 0,
            ]
        );

        $this->command->info('QA test product seeded: QA Test T-Shirt, $19.99, store '.$store->id);
    }
}
