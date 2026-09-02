<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * A single real, orderable product for the QA Automation Store, so scripted
 * backend verification can exercise the full customer -> vendor -> driver
 * order lifecycle without touching QaTestProductSeeder's human-tester store.
 */
class QaAutomationProductSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('name', 'QA Automation Store')->first();
        if (!$store) {
            $this->command->warn('QA Automation Store not found, run QaAutomationAccountsSeeder first.');
            return;
        }

        Item::updateOrCreate(
            ['name' => 'QA Automation Widget', 'store_id' => $store->id],
            [
                'description' => 'A test product for scripted end-to-end order flow verification.',
                'category_id' => 85,
                'category_ids' => json_encode([['id' => 85, 'position' => 1]]),
                'choice_options' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'variations' => json_encode([]),
                'price' => 12.5,
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

        $this->command->info('QA automation product seeded: QA Automation Widget, $12.50, store '.$store->id);
    }
}
