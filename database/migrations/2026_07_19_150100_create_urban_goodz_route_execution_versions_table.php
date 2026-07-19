<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_route_execution_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dedicated_route_id');
            $table->unsignedBigInteger('driver_id');
            $table->integer('version');
            $table->string('endpoint_type');
            $table->string('private_endpoint_address')->nullable();
            $table->decimal('private_endpoint_lat', 10, 7)->nullable();
            $table->decimal('private_endpoint_lng', 10, 7)->nullable();
            $table->decimal('miles', 10, 2);
            $table->integer('duration_minutes');
            $table->json('stop_order_sequence'); // JSON list of stop details
            $table->string('status')->default('active'); // active, pending_approval, rejected
            $table->timestamps();

            // Foreign keys and indices
            $table->foreign('dedicated_route_id', 'fk_ug_rev_dedicated_route_id')
                  ->references('id')
                  ->on('urban_goodz_dedicated_routes')
                  ->onDelete('cascade');
            $table->foreign('driver_id', 'fk_ug_rev_driver_id')
                  ->references('id')
                  ->on('delivery_men')
                  ->onDelete('cascade');
            
            $table->index(['dedicated_route_id', 'version'], 'idx_ug_rev_route_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_route_execution_versions');
    }
};
