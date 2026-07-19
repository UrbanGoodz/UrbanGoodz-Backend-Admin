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
        Schema::create('urban_goodz_route_clustering_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_client_id')->nullable();
            $table->unsignedBigInteger('manifest_id')->nullable();
            $table->json('clustering_params');
            $table->json('original_plan');
            $table->json('optimized_plan');
            $table->json('unrouteable_packages')->nullable();
            $table->integer('total_packages')->default(0);
            $table->integer('routed_packages')->default(0);
            $table->integer('unrouteable_count')->default(0);
            $table->integer('routes_generated')->default(0);
            $table->string('algorithm')->default('sweep_nearest_neighbor');
            $table->string('status')->default('generated');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('business_client_id')->references('id')->on('urban_goodz_business_clients')->nullOnDelete();
            $table->foreign('manifest_id')->references('id')->on('urban_goodz_manifests')->nullOnDelete();
            $table->index('business_client_id');
            $table->index('manifest_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_route_clustering_audits');
    }
};
