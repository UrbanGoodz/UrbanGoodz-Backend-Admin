<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs schema drift on `users` between a from-scratch migration run and the
 * schema production actually has.
 *
 * How the drift happened: 2014_10_12_000000_create_users_table.php creates
 * `users` early in the run, and 2022_05_09_235958_create_core_users_and_
 * vendors_tables_if_missing.php - which holds the canonical column set - is a
 * "create if missing" migration. By the time it runs the table already exists,
 * so it skips entirely and six of its columns are never created.
 *
 * Production was built from the 6amMart baseline that already had those
 * columns, so it is unaffected. Any freshly migrated environment was not, and
 * the gap is not cosmetic: DashboardController@dashboard_data counts active and
 * blocked customers with User::where('status', ...), which throws
 * SQLSTATE[42S22] Unknown column 'status' against a fresh schema. It also broke
 * three QA seeders, which is how it surfaced.
 *
 * Definitions below are copied verbatim from the canonical migration rather
 * than inferred, so a fresh database ends up matching production exactly.
 *
 * Idempotent by design: every column is guarded by hasColumn(), so this is a
 * no-op on production and on any environment already carrying them.
 */
return new class extends Migration
{
    /**
     * column name => closure adding it, matching the canonical definition.
     */
    private function columns(): array
    {
        return [
            'interest'          => fn (Blueprint $t) => $t->string('interest', 255)->nullable(),
            'is_phone_verified' => fn (Blueprint $t) => $t->boolean('is_phone_verified')->default(false),
            'login_medium'      => fn (Blueprint $t) => $t->string('login_medium', 255)->nullable(),
            'order_count'       => fn (Blueprint $t) => $t->integer('order_count')->default(0),
            'social_id'         => fn (Blueprint $t) => $t->string('social_id', 255)->nullable(),
            'status'            => fn (Blueprint $t) => $t->boolean('status')->default(true),
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $missing = array_filter(
            $this->columns(),
            fn ($_, $name) => ! Schema::hasColumn('users', $name),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($missing)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($missing) {
            foreach ($missing as $add) {
                $add($table);
            }
        });

        // Where the table already tracked activity as is_active, carry that
        // value across so existing rows are not silently all marked active.
        if (array_key_exists('status', $missing) && Schema::hasColumn('users', 'is_active')) {
            \DB::table('users')->update(['status' => \DB::raw('is_active')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // Only drop what this migration would have added, and never on a
        // database where the column predates it in the canonical definition.
        $present = array_filter(
            array_keys($this->columns()),
            fn ($name) => Schema::hasColumn('users', $name)
        );

        if (empty($present)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($present) {
            $table->dropColumn(array_values($present));
        });
    }
};
