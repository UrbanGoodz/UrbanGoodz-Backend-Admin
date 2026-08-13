<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (Schema::hasColumn('urban_goodz_route_packages', 'dedicated_route_id') && DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign('ug_pkg_route_fk');
                }
            });

            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                $table->unsignedBigInteger('dedicated_route_id')->nullable()->change();
            });

            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                $table->foreign('dedicated_route_id', 'ug_pkg_route_fk')
                      ->references('id')->on('urban_goodz_dedicated_routes')
                      ->nullOnDelete();

                if (!Schema::hasColumn('urban_goodz_route_packages', 'manifest_session_id')) {
                    $table->string('manifest_session_id', 36)->nullable()->after('business_client_id');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'scanned_by')) {
                    $table->unsignedBigInteger('scanned_by')->nullable()->after('manifest_session_id');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'scanned_at')) {
                    $table->timestamp('scanned_at')->nullable()->after('scanned_by');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
            $table->dropColumn(['manifest_session_id', 'scanned_by', 'scanned_at']);
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('ug_pkg_route_fk');
            }
        });

        Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('dedicated_route_id')->nullable(false)->change();
        });

        Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
            $table->foreign('dedicated_route_id', 'ug_pkg_route_fk')
                  ->references('id')->on('urban_goodz_dedicated_routes')
                  ->cascadeOnDelete();
        });
    }
};
