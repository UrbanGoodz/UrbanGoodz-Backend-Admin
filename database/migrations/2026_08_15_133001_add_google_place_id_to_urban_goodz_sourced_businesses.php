<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_sourced_businesses')) {
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'google_place_id')) {
                    $table->string('google_place_id')->nullable();
                }
            });
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                $indexes = collect(Schema::getIndexes('urban_goodz_sourced_businesses'))->pluck('name');
                if (!$indexes->contains('ug_sourced_businesses_place_id_idx')) {
                    $table->index('google_place_id', 'ug_sourced_businesses_place_id_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_sourced_businesses')) {
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                $table->dropColumn('google_place_id');
            });
        }
    }
};
