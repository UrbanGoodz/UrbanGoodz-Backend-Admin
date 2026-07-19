<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_batch_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_batch_id')->constrained('urban_goodz_intake_batches')->cascadeOnDelete();
            $table->foreignId('business_client_id')->constrained('urban_goodz_business_clients')->cascadeOnDelete();
            $table->string('tracking_id')->nullable();
            $table->string('external_package_id')->nullable();
            $table->string('order_reference_number')->nullable();
            $table->string('barcode')->nullable();
            $table->string('source_type')->default('manual_entry');
            $table->string('source_file_ref')->nullable();
            $table->string('source_manifest_row')->nullable();

            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('pickup_city')->nullable();
            $table->string('pickup_state', 10)->nullable();
            $table->string('pickup_zip', 20)->nullable();

            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->string('dropoff_address')->nullable();
            $table->string('dropoff_city')->nullable();
            $table->string('dropoff_state', 10)->nullable();
            $table->string('dropoff_zip', 20)->nullable();

            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('priority')->default('normal');
            $table->timestamp('delivery_window_start')->nullable();
            $table->timestamp('delivery_window_end')->nullable();
            $table->decimal('weight_lbs', 8, 2)->nullable();
            $table->decimal('volume_cubic_ft', 8, 2)->nullable();
            $table->string('package_type')->default('parcel');
            $table->boolean('age_restricted')->default(false);
            $table->boolean('requires_signature')->default(false);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_custody')->default(false);
            $table->text('special_instructions')->nullable();

            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_session_id')->nullable();
            $table->integer('version')->default(1);

            $table->string('validation_status')->default('pending');
            $table->json('validation_errors')->nullable();
            $table->string('duplicate_status')->default('none');
            $table->foreignId('duplicate_of_package_id')->nullable()->constrained('urban_goodz_batch_packages')->nullOnDelete();
            $table->string('geocoding_status')->default('pending');
            $table->json('geocoding_result')->nullable();
            $table->string('route_assignment_status')->default('unassigned');
            $table->foreignId('dedicated_route_id')->nullable()->constrained('urban_goodz_dedicated_routes')->nullOnDelete();
            $table->integer('stop_order')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['intake_batch_id', 'validation_status'], 'ug_packages_batch_val_idx');
            $table->index(['intake_batch_id', 'duplicate_status'], 'ug_packages_batch_dup_idx');
            $table->index(['intake_batch_id', 'route_assignment_status'], 'ug_packages_batch_route_idx');
            $table->index('tracking_id');
            $table->index('barcode');
            $table->index(['business_client_id', 'external_package_id'], 'ug_packages_biz_ext_idx');
            $table->index(['business_client_id', 'order_reference_number'], 'ug_packages_biz_ref_idx');
            $table->index(['intake_batch_id', 'created_by_user_id'], 'ug_packages_batch_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_batch_packages');
    }
};
