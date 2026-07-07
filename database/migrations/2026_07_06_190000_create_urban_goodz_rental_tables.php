<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_rental_assets')) {
            Schema::create('urban_goodz_rental_assets', function (Blueprint $table) {
                $table->id();
                $table->string('business_type_slug');
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('asset_type');
                $table->string('make')->nullable();
                $table->string('model')->nullable();
                $table->string('year', 4)->nullable();
                $table->string('plate_number')->nullable();
                $table->string('vin')->nullable();
                $table->string('unit_number')->nullable();
                $table->json('photos')->nullable();
                $table->string('status')->default('available');
                $table->decimal('daily_rate', 12, 2)->nullable();
                $table->decimal('hourly_rate', 12, 2)->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->integer('mileage_limit')->nullable();
                $table->string('pickup_location')->nullable();
                $table->string('return_location')->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_rental_bookings')) {
            Schema::create('urban_goodz_rental_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_asset_id')->constrained('urban_goodz_rental_assets')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone')->nullable();
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('status')->default('pending');
                $table->string('payment_status')->default('pending');
                $table->string('deposit_status')->default('pending');
                $table->string('verification_status')->default('pending');
                $table->decimal('total_amount', 12, 2)->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->text('admin_notes')->nullable();
                $table->text('customer_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_rental_inspections')) {
            Schema::create('urban_goodz_rental_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rental_booking_id')->constrained('urban_goodz_rental_bookings')->cascadeOnDelete();
                $table->string('inspection_type');
                $table->json('photos')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('damage_found')->default(false);
                $table->decimal('damage_amount', 12, 2)->nullable();
                $table->string('status')->default('pending');
                $table->string('inspected_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_rental_inspections');
        Schema::dropIfExists('urban_goodz_rental_bookings');
        Schema::dropIfExists('urban_goodz_rental_assets');
    }
};
