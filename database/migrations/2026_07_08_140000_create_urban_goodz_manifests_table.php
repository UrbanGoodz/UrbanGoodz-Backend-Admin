<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_manifests')) {
            Schema::create('urban_goodz_manifests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_client_id');
                $table->foreign('business_client_id', 'ug_mnf_client_fk')
                      ->references('id')->on('urban_goodz_business_clients')
                      ->cascadeOnDelete();
                $table->string('manifest_name', 255)->nullable();
                $table->string('manifest_session_id', 36)->nullable()->unique();
                $table->unsignedBigInteger('pickup_location_id')->nullable();
                $table->foreign('pickup_location_id', 'ug_mnf_location_fk')
                      ->references('id')->on('urban_goodz_business_client_locations')
                      ->nullOnDelete();
                $table->text('pickup_location_text')->nullable();
                $table->date('service_date')->nullable();
                $table->string('status', 50)->default('draft');
                $table->integer('total_packages')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['business_client_id', 'status'], 'ug_mnf_client_status_idx');
            });
        }

        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_packages', 'manifest_id')) {
                    $table->unsignedBigInteger('manifest_id')->nullable()->after('business_client_id');
                    $table->foreign('manifest_id', 'ug_pkg_manifest_fk')
                          ->references('id')->on('urban_goodz_manifests')
                          ->nullOnDelete();
                    $table->index(['manifest_id', 'status'], 'ug_pkg_manifest_status_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (Schema::hasColumn('urban_goodz_route_packages', 'manifest_id')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign('ug_pkg_manifest_fk');
                    }
                    $table->dropColumn('manifest_id');
                }
            });
        }

        Schema::dropIfExists('urban_goodz_manifests');
    }
};
