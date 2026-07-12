<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_load_board_loads', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('provider')->default('internal');
            $table->string('load_number')->nullable();
            $table->string('status')->default('available');

            $table->string('origin_name')->nullable();
            $table->string('origin_city')->nullable();
            $table->string('origin_state')->nullable();
            $table->string('origin_zip')->nullable();
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();
            $table->timestamp('origin_ready_at')->nullable();

            $table->string('destination_name')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('destination_state')->nullable();
            $table->string('destination_zip')->nullable();
            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();
            $table->timestamp('destination_due_at')->nullable();

            $table->decimal('distance_miles', 10, 2)->nullable();
            $table->integer('estimated_duration_minutes')->nullable();

            $table->decimal('payout_amount', 10, 2)->default(0);
            $table->string('payout_type')->default('flat');
            $table->decimal('rate_per_mile', 8, 2)->nullable();

            $table->string('load_type')->nullable();
            $table->string('equipment_type')->nullable();
            $table->decimal('weight_lbs', 10, 2)->nullable();
            $table->decimal('length_ft', 8, 2)->nullable();
            $table->integer('pieces')->nullable();
            $table->text('commodity_description')->nullable();
            $table->text('special_requirements')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_hazmat')->default(false);
            $table->boolean('is_temperature_controlled')->default(false);
            $table->decimal('temperature_min_f', 6, 2)->nullable();
            $table->decimal('temperature_max_f', 6, 2)->nullable();
            $table->boolean('requires_liftgate')->default(false);
            $table->boolean('requires_pallet_jack')->default(false);
            $table->boolean('is_team_load')->default(false);
            $table->boolean('is_expedited')->default(false);

            $table->string('shipper_name')->nullable();
            $table->string('shipper_phone')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('consignee_phone')->nullable();

            $table->unsignedBigInteger('assigned_driver_id')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_proof')->nullable();

            $table->unsignedBigInteger('business_client_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'origin_state']);
            $table->index(['status', 'destination_state']);
            $table->index(['provider', 'external_id']);
            $table->index('assigned_driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_load_board_loads');
    }
};
