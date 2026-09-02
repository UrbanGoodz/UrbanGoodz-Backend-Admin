<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original create_reviews_table migration guards on
        // Schema::hasTable() and was a no-op on databases seeded from a SQL
        // dump that predated this column, so it never actually got added
        // despite the migration ledger showing it as run.
        if (!Schema::hasColumn('reviews', 'delivery_man_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index()->after('order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reviews', 'delivery_man_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex(['delivery_man_id']);
                $table->dropColumn('delivery_man_id');
            });
        }
    }
};
