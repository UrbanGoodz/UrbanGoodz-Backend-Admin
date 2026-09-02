<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_historical_monthly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('reconstruction_id')->unique('ug_hms_recon_uid');
            $table->unsignedBigInteger('configuration_id');
            $table->date('snapshot_month');
            $table->integer('snapshot_year');
            $table->integer('snapshot_month_number');

            $table->decimal('estimated_orders', 10, 2)->default(0);
            $table->decimal('estimated_average_order_value', 10, 2)->default(0);
            $table->decimal('estimated_total_order_value', 12, 2)->default(0);
            $table->decimal('estimated_order_commission_revenue', 12, 2)->default(0);
            $table->decimal('estimated_delivery_fee_per_order', 10, 2)->default(0);
            $table->decimal('estimated_delivery_fee_revenue', 12, 2)->default(0);
            $table->decimal('estimated_platform_delivery_fee_revenue', 12, 2)->default(0);
            $table->decimal('estimated_total_platform_revenue', 12, 2)->default(0);
            $table->integer('estimated_active_driver_count')->default(0);
            $table->integer('estimated_owner_deliveries')->default(0);
            $table->decimal('estimated_driver_payouts', 12, 2)->default(0);
            $table->decimal('estimated_operating_expenses', 12, 2)->default(0);
            $table->decimal('estimated_net_income', 12, 2)->default(0);
            $table->decimal('calculated_net_income', 12, 2)->default(0);
            $table->decimal('net_income_variance_from_baseline', 12, 2)->default(0);

            $table->string('source_type', 50)->default('historical_reconstruction');
            $table->string('reconstruction_method', 100)->default('mathematical_estimation');
            $table->string('reconstruction_version', 20)->default('1.0');
            $table->string('confidence', 20)->default('estimated');
            $table->text('notes')->nullable();

            $table->json('assumptions_used')->nullable();
            $table->json('calculation_log')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('configuration_id', 'ug_hms_cfg_fk')
                ->references('id')
                ->on('urban_goodz_historical_reconstruction_configurations')
                ->onDelete('cascade');
            $table->index('snapshot_month', 'ug_hms_month');
            $table->index('configuration_id', 'ug_hms_config_id');
            $table->index('confidence', 'ug_hms_confidence');
            $table->index('source_type', 'ug_hms_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_historical_monthly_snapshots');
    }
};
