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
        Schema::table('categories', function (Blueprint $table) {
            foreach (['parent_id', 'name'] as $column) {
                if (Schema::hasColumn('categories', $column) && !Schema::hasIndex('categories', [$column])) {
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
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('parent_id');
            $table->dropIndex('name');
        });
    }
};
