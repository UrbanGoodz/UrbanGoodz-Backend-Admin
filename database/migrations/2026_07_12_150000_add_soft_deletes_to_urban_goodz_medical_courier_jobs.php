<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('urban_goodz_medical_courier_jobs')
            && ! Schema::hasColumn('urban_goodz_medical_courier_jobs', 'deleted_at')
        ) {
            Schema::table('urban_goodz_medical_courier_jobs', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('urban_goodz_medical_courier_jobs')
            && Schema::hasColumn('urban_goodz_medical_courier_jobs', 'deleted_at')
        ) {
            Schema::table('urban_goodz_medical_courier_jobs', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
