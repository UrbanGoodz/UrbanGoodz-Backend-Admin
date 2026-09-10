<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third pass of the from-scratch schema repair.
 *
 * The `items` table is missing five of its core product columns on a freshly
 * migrated database. No migration in the tree creates them, yet they are
 * central to the product model:
 *
 *   variations, choice_options, add_ons, attributes
 *       Read by CentralLogics\Helpers when formatting cart and product data.
 *       There is a fix in this very branch titled "null category_ids/variations
 *       crash cart_product_data_formatting", which is only reachable if the
 *       columns exist at all.
 *
 *   position
 *       Used for item ordering within a store.
 *
 * Types are copied from temp_products, the one table in the schema that still
 * carries the canonical definitions, rather than being invented:
 *   variations      text     nullable
 *   choice_options  text     nullable
 *   add_ons         string   nullable
 *   attributes      string(255) nullable
 *
 * Production predates this drift and already has these columns, so every
 * change is guarded by hasColumn() and this is a no-op there. Without it, both
 * QA product seeders fail with SQLSTATE[42S22] and no test environment can
 * hold a single orderable product.
 */
return new class extends Migration
{
    private function columns(): array
    {
        return [
            'variations'     => fn (Blueprint $t) => $t->text('variations')->nullable(),
            'choice_options' => fn (Blueprint $t) => $t->text('choice_options')->nullable(),
            'add_ons'        => fn (Blueprint $t) => $t->string('add_ons')->nullable(),
            'attributes'     => fn (Blueprint $t) => $t->string('attributes', 255)->nullable(),
            'position'       => fn (Blueprint $t) => $t->integer('position')->default(0),
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        $missing = array_filter(
            $this->columns(),
            fn ($_, $name) => ! Schema::hasColumn('items', $name),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($missing)) {
            return;
        }

        Schema::table('items', function (Blueprint $table) use ($missing) {
            foreach ($missing as $add) {
                $add($table);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        $present = array_values(array_filter(
            array_keys($this->columns()),
            fn ($name) => Schema::hasColumn('items', $name)
        ));

        if ($present) {
            Schema::table('items', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};
