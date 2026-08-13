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
        Schema::table('reviews', function (Blueprint $table) {
            foreach (['item_id', 'item_campaign_id', 'user_id', 'order_id', 'store_id', 'review_id'] as $column) {
                if (Schema::hasColumn('reviews', $column) && !Schema::hasIndex('reviews', [$column])) {
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
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('item_id');
            $table->dropIndex('item_campaim_id');
            $table->dropIndex('user_id');
            $table->dropIndex('order_id');
            $table->dropIndex('store_id');
            $table->dropIndex('review_id');
        });
    }
};
