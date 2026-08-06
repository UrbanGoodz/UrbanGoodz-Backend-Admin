<?php

use App\Services\UrbanGoodzStrandedSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the last hard-coded price from Stranded and materialises the
 * administrator-editable settings.
 *
 * Two things happen here:
 *
 * 1. `help_request_fee_minor` carried a column default of 500. A schema
 *    default is still a hard-coded price: any row inserted by a path that
 *    forgot to price it would silently acquire a $5 fee. The default becomes
 *    0, which is an absence rather than a price. The application always sets
 *    the value explicitly from settings.
 *
 * 2. The settings rows are created so they are visible and editable in the
 *    admin immediately, rather than only existing implicitly as code
 *    defaults until somebody presses Save.
 *
 * Written as raw SQL because altering a column default otherwise needs
 * doctrine/dbal, which this project does not require.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_stranded_requests')) {
            DB::statement('ALTER TABLE `urban_goodz_stranded_requests` MODIFY `help_request_fee_minor` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }

        // Seeded through the settings service so the keys and their defaults
        // are declared in exactly one place. updateOrCreate is deliberate:
        // business_settings.key has no unique index, so an upsert would
        // append a duplicate row rather than update the existing one.
        foreach (UrbanGoodzStrandedSettings::all() as $key => $value) {
            UrbanGoodzStrandedSettings::put($key, $value);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_stranded_requests')) {
            DB::statement('ALTER TABLE `urban_goodz_stranded_requests` MODIFY `help_request_fee_minor` BIGINT UNSIGNED NOT NULL DEFAULT 500');
        }
    }
};
