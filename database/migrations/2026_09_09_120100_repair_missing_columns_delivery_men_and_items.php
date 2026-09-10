<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second pass of the from-scratch schema repair (see
 * 2026_09_09_120000_add_missing_users_columns_from_canonical_definition).
 *
 * These three columns are referenced by application code and by the QA
 * seeders, but no migration in the tree ever creates them, so a freshly
 * migrated database does not have them:
 *
 *   delivery_men.identity_type    - the table already carries identity_image,
 *   delivery_men.identity_number    so the identity trio is split apart: two
 *                                   thirds of it were never migrated. Three QA
 *                                   seeders fail on identity_number with
 *                                   SQLSTATE[42S22].
 *
 *   items.category_ids            - read seven times in CentralLogics\Helpers,
 *                                   including formatCategoryIds(), which
 *                                   json_decodes it. Without the column those
 *                                   paths cannot run at all.
 *
 * The type for category_ids is taken from temp_products, the one table in the
 * schema that does define it - string(255) nullable holding a JSON array -
 * rather than being invented here.
 *
 * Production predates this drift and already has the columns, so every change
 * is guarded by hasColumn() and this migration is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_men')) {
            $add = [];
            if (! Schema::hasColumn('delivery_men', 'identity_type')) {
                $add[] = 'identity_type';
            }
            if (! Schema::hasColumn('delivery_men', 'identity_number')) {
                $add[] = 'identity_number';
            }

            if ($add) {
                Schema::table('delivery_men', function (Blueprint $table) use ($add) {
                    foreach ($add as $col) {
                        $table->string($col, 255)->nullable();
                    }
                });
            }
        }

        if (Schema::hasTable('items') && ! Schema::hasColumn('items', 'category_ids')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('category_ids', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_men')) {
            $drop = array_values(array_filter(
                ['identity_type', 'identity_number'],
                fn ($c) => Schema::hasColumn('delivery_men', $c)
            ));
            if ($drop) {
                Schema::table('delivery_men', fn (Blueprint $t) => $t->dropColumn($drop));
            }
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'category_ids')) {
            Schema::table('items', fn (Blueprint $t) => $t->dropColumn('category_ids'));
        }
    }
};
