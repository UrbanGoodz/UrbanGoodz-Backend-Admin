<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_measurement_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_measurement_requests', 'front_photo_file_id')) {
                $table->unsignedBigInteger('front_photo_file_id')->nullable()->after('front_photo_path');
                $table->foreign('front_photo_file_id')->references('id')->on('urban_goodz_files')->onDelete('set null');
            }
            if (!Schema::hasColumn('urban_goodz_measurement_requests', 'side_photo_file_id')) {
                $table->unsignedBigInteger('side_photo_file_id')->nullable()->after('side_photo_path');
                $table->foreign('side_photo_file_id')->references('id')->on('urban_goodz_files')->onDelete('set null');
            }
            if (!Schema::hasColumn('urban_goodz_measurement_requests', 'back_photo_file_id')) {
                $table->unsignedBigInteger('back_photo_file_id')->nullable()->after('back_photo_path');
                $table->foreign('back_photo_file_id')->references('id')->on('urban_goodz_files')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_measurement_requests', function (Blueprint $table) {
            $columns = ['front_photo_file_id', 'side_photo_file_id', 'back_photo_file_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('urban_goodz_measurement_requests', $column)) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
