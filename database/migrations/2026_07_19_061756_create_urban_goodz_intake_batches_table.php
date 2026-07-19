<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_intake_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_client_id')->constrained('urban_goodz_business_clients')->cascadeOnDelete();
            $table->foreignId('business_location_id')->nullable()->constrained('urban_goodz_business_client_locations')->nullOnDelete();
            $table->string('batch_name')->nullable();
            $table->date('service_date');
            $table->time('intake_start_time')->nullable();
            $table->time('intake_cutoff_time')->nullable();
            $table->integer('expected_package_count')->default(0);
            $table->integer('final_package_count')->nullable();
            $table->string('routing_policy')->default('standard');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatcher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('DRAFT');
            $table->integer('version')->default(1);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('routing_snapshot')->nullable();
            $table->timestamp('routing_snapshot_at')->nullable();
            $table->json('late_package_policy')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_client_id', 'service_date']);
            $table->index('status');
            $table->index('is_locked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_intake_batches');
    }
};
