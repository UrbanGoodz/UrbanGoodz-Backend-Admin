<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two reasons a customer opened the app and saw no products.
 *
 * 1. An item only renders when its module_id matches the module_id of the
 *    store it belongs to. 144 items were tagged module 3 (Pharmacy) while
 *    sitting in stores under module 10 (Pharmacy/Health) - every item in
 *    Houston Wellness Pharmacy, for instance. They could never be displayed,
 *    and module 3 reported 144 items against 0 stores.
 *
 * 2. Modules with no stores at all were still live and browsable. For the
 *    Houston zone the module list led with Rental (0 stores, 0 items),
 *    followed by Pharmacy (0 stores) and THC/CBD (0 stores) - so landing on
 *    the first tab showed an empty catalogue.
 *
 * Both are corrected by matching an item to the module its own store is in,
 * and by hiding only those modules that genuinely have no stores. The empty
 * check is evaluated at run time rather than against hardcoded ids, so this
 * cannot hide a module that has stores in another environment.
 */
return new class extends Migration
{
    /** Modules found empty in production; each is re-checked before hiding. */
    private const EMPTY_MODULE_IDS = [2, 3, 7];

    public function up(): void
    {
        if (!Schema::hasTable('items') || !Schema::hasTable('stores')) {
            return;
        }

        // Re-tag every item onto the module its store actually belongs to.
        DB::table('items')
            ->join('stores', 'items.store_id', '=', 'stores.id')
            ->whereColumn('items.module_id', '!=', 'stores.module_id')
            ->update(['items.module_id' => DB::raw('stores.module_id')]);

        if (!Schema::hasTable('modules')) {
            return;
        }

        foreach (self::EMPTY_MODULE_IDS as $moduleId) {
            $storeCount = DB::table('stores')->where('module_id', $moduleId)->count();

            // Only hide a module that really has nothing behind it. A module
            // that has gained stores since this was written is left alone.
            if ($storeCount > 0) {
                continue;
            }

            // Only touch a module that is actually live, so down() cannot
            // later switch on something that was already off.
            DB::table('modules')
                ->where('id', $moduleId)
                ->where('status', 1)
                ->update(['status' => 0]);
        }
    }

    public function down(): void
    {
        // Restores only modules that are still empty, which is the set up()
        // could have hidden. A module that has since gained stores was never
        // hidden by this migration and is left untouched.
        if (Schema::hasTable('modules') && Schema::hasTable('stores')) {
            foreach (self::EMPTY_MODULE_IDS as $moduleId) {
                if (DB::table('stores')->where('module_id', $moduleId)->count() > 0) {
                    continue;
                }

                DB::table('modules')->where('id', $moduleId)->update(['status' => 1]);
            }
        }

        // The item re-tagging is deliberately NOT reversed. The original
        // module_id per row is not derivable from the corrected state, and
        // restoring it would only make those items invisible again. Production
        // has an exact per-row restore at
        // backups/20260929_catalog_fix/restore_item_modules.sql if it is ever
        // genuinely needed.
    }
};
