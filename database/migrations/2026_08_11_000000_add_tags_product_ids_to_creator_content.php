<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the tags and product_ids columns that creator content submission
     * relies on but the original create migration omitted.
     */
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_creator_content')) {
            return;
        }

        if (!Schema::hasColumn('urban_goodz_creator_content', 'tags')) {
            Schema::table('urban_goodz_creator_content', function (Blueprint $table) {
                $table->json('tags')->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('urban_goodz_creator_content', 'product_ids')) {
            Schema::table('urban_goodz_creator_content', function (Blueprint $table) {
                $table->json('product_ids')->nullable()->after('tags');
            });
        }
    }

    public function down(): void
    {
        Schema::table('urban_goodz_creator_content', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_creator_content', 'product_ids')) {
                $table->dropColumn('product_ids');
            }
            if (Schema::hasColumn('urban_goodz_creator_content', 'tags')) {
                $table->dropColumn('tags');
            }
        });
    }
};
