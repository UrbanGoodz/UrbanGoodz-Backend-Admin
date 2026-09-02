<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_historical_reconstruction_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('configuration_name', 255);
            $table->date('reconstruction_start_date');
            $table->date('reconstruction_end_date');
            $table->decimal('baseline_monthly_orders', 10, 2)->default(750);
            $table->decimal('baseline_average_order_value', 10, 2)->default(29.00);
            $table->decimal('baseline_order_commission_pct', 5, 2)->default(23.00);
            $table->decimal('baseline_delivery_fee', 10, 2)->default(15.00);
            $table->decimal('baseline_platform_delivery_fee_pct', 5, 2)->default(3.00);
            $table->integer('baseline_active_drivers')->default(13);
            $table->decimal('baseline_avg_monthly_net', 10, 2)->default(5700.00);
            $table->decimal('orders_variation_pct', 5, 2)->default(10.00);
            $table->decimal('aov_variation_pct', 5, 2)->default(8.00);
            $table->decimal('delivery_fee_variation_pct', 5, 2)->default(7.00);
            $table->decimal('driver_count_variation_pct', 5, 2)->default(15.00);
            $table->decimal('operating_expense_ratio', 5, 2)->default(25.00);
            $table->text('evidentiary_disclaimer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active', 'ug_hrc_is_active');
            $table->index('reconstruction_start_date', 'ug_hrc_start_date');
            $table->index('reconstruction_end_date', 'ug_hrc_end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_historical_reconstruction_configurations');
    }
};
