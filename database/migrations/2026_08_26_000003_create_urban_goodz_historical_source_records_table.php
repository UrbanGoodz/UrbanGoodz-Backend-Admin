<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_historical_source_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('configuration_id');
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('source_type', 50);
            $table->string('source_description', 255)->nullable();
            $table->date('source_date')->nullable();
            $table->json('source_data')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.50);
            $table->string('confidence_label', 20)->default('estimated');
            $table->text('notes')->nullable();
            $table->boolean('overrides_reconstruction')->default(false);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->foreign('configuration_id', 'ug_hsr_cfg_fk')
                ->references('id')
                ->on('urban_goodz_historical_reconstruction_configurations')
                ->onDelete('cascade');
            $table->foreign('snapshot_id', 'ug_hsr_snap_fk')
                ->references('id')
                ->on('urban_goodz_historical_monthly_snapshots')
                ->onDelete('set null');
            $table->index('configuration_id', 'ug_hsr_config_id');
            $table->index('snapshot_id', 'ug_hsr_snapshot_id');
            $table->index('source_type', 'ug_hsr_source');
            $table->index('confidence_label', 'ug_hsr_confidence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_historical_source_records');
    }
};
