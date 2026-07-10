<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_manifests')) {
            Schema::table('urban_goodz_manifests', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_manifests', 'service_type')) {
                    $table->string('service_type', 50)->nullable()->after('service_date');
                }
                if (!Schema::hasColumn('urban_goodz_manifests', 'scanned_packages')) {
                    $table->integer('scanned_packages')->default(0)->after('total_packages');
                }
                if (!Schema::hasColumn('urban_goodz_manifests', 'valid_packages')) {
                    $table->integer('valid_packages')->default(0)->after('scanned_packages');
                }
                if (!Schema::hasColumn('urban_goodz_manifests', 'invalid_packages')) {
                    $table->integer('invalid_packages')->default(0)->after('valid_packages');
                }
                if (!Schema::hasColumn('urban_goodz_manifests', 'generated_routes_count')) {
                    $table->integer('generated_routes_count')->default(0)->after('invalid_packages');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_manifests')) {
            Schema::table('urban_goodz_manifests', function (Blueprint $table) {
                $cols = ['service_type', 'scanned_packages', 'valid_packages', 'invalid_packages', 'generated_routes_count'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('urban_goodz_manifests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
