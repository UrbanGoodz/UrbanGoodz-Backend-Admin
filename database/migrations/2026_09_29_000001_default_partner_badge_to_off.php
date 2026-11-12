<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * stores.partner_badge_enabled shipped with a default of 1, so every store
 * created through any path - admin "add store", the vendor web signup, the
 * vendor app signup, and the bulk importer - claimed the Urban Goodz Partner
 * badge the moment the row was written, before anyone granted anything. That
 * is the same false claim the 234 sourced storefronts were corrected for, and
 * with the default left as it was it regenerates on every new signup.
 *
 * A badge is granted, never assumed. Existing rows are deliberately left
 * alone; urban-goodz:audit-partner-badges reports them so the grant decision
 * stays a human one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stores', 'partner_badge_enabled')) {
            return;
        }

        // Raw ALTER rather than a Blueprint change(): it only rewrites the
        // column default, so it does not need doctrine/dbal and does not
        // rebuild a table that has 248 live storefronts in it.
        DB::statement('ALTER TABLE `stores` ALTER COLUMN `partner_badge_enabled` SET DEFAULT 0');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('stores', 'partner_badge_enabled')) {
            return;
        }

        DB::statement('ALTER TABLE `stores` ALTER COLUMN `partner_badge_enabled` SET DEFAULT 1');
    }
};
