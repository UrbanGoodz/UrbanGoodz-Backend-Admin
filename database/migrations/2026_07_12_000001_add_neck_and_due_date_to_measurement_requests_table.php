<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_measurement_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_measurement_requests', 'neck')) {
                $table->decimal('neck', 8, 2)->nullable()->after('shoulder_width');
            }
            if (!Schema::hasColumn('urban_goodz_measurement_requests', 'due_date')) {
                $table->date('due_date')->nullable()->after('budget');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_measurement_requests', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_measurement_requests', 'neck')) {
                $table->dropColumn('neck');
            }
            if (Schema::hasColumn('urban_goodz_measurement_requests', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });
    }
};
