<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'business_type_slug')) {
                $table->string('business_type_slug', 100)->nullable()->after('module_id')->index();
                $table->foreign('business_type_slug')->references('slug')->on('urban_goodz_business_types')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'business_type_slug')) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['business_type_slug']);
                }
                $table->dropColumn('business_type_slug');
            }
        });
    }
};
