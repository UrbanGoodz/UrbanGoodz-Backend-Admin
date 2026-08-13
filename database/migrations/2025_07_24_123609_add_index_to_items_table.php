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
        Schema::table('items', function (Blueprint $table) {
            foreach (['category_id', 'store_id', 'name', 'slug', 'price', 'created_at', 'order_count', 'avg_rating'] as $column) {
                if (!Schema::hasIndex('items', [$column])) {
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
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('category_id');
            $table->dropIndex('store_id');
            $table->dropIndex('name');
            $table->dropIndex('slug');
            $table->dropIndex('price');
            $table->dropIndex('created_at');
            $table->dropIndex('order_count');
            $table->dropIndex('avg_rating');
        });
    }
};
