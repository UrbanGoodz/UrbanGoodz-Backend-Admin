<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_historical_reconstruction_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->uuid('reconstruction_id')->nullable();
            $table->unsignedBigInteger('configuration_id')->nullable();
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('action', 50);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('reconstruction_id', 'ug_hrat_recon_id');
            $table->index('configuration_id', 'ug_hrat_config_id');
            $table->index('snapshot_id', 'ug_hrat_snapshot');
            $table->index('action', 'ug_hrat_action');
            $table->index('entity_type', 'ug_hrat_entity');
            $table->index('admin_id', 'ug_hrat_admin');
            $table->index('created_at', 'ug_hrat_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_historical_reconstruction_audit_trail');
    }
};
