<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            foreach (['user_id', 'item_id', 'store_id'] as $column) {
                if (Schema::hasColumn('wishlists', $column) && !Schema::hasIndex('wishlists', [$column])) {
                    $table->index($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropIndex('user_id');
            $table->dropIndex('item_id');
            $table->dropIndex('store_id');
        });
    }
};
