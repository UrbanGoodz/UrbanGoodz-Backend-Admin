<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_measurement_requests')) {
            return;
        }

        Schema::create('urban_goodz_measurement_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('tailor_id')->nullable()->index();
            $table->unsignedBigInteger('measurement_profile_id')->nullable()->index();
            $table->string('preferred_fit')->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('chest_bust', 8, 2)->nullable();
            $table->decimal('waist', 8, 2)->nullable();
            $table->decimal('hips', 8, 2)->nullable();
            $table->decimal('inseam', 8, 2)->nullable();
            $table->decimal('sleeve_length', 8, 2)->nullable();
            $table->decimal('shoulder_width', 8, 2)->nullable();
            $table->string('source')->default('manual');
            $table->string('front_photo_path')->nullable();
            $table->string('side_photo_path')->nullable();
            $table->string('back_photo_path')->nullable();
            $table->boolean('face_blur_enabled')->default(true);
            $table->string('face_blur_status')->nullable();
            $table->string('privacy_review_status')->nullable();
            $table->decimal('platform_measurement_fee', 10, 2)->default(0);
            $table->decimal('vendor_review_fee', 10, 2)->default(0);
            $table->decimal('total_measurement_fee', 10, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status')->default('waived');
            $table->boolean('free_tester_mode')->default(true);
            $table->string('measurement_status')->default('not_started');
            $table->string('review_status')->default('pending');
            $table->text('tailor_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->decimal('quote_amount', 10, 2)->nullable();
            $table->string('mockup_reference')->nullable();
            $table->text('corrected_measurements')->nullable();
            $table->string('item_wanted')->nullable();
            $table->string('request_type')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->boolean('consent_to_share_photos')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_measurement_requests');
    }
};
