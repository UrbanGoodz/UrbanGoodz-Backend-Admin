<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_needs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // driver_shortage, order_anywhere_demand, vendor_onboarding_gap, low_inventory, catalog_defect, late_route_risk, settlement_inquiry
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('recommended_action')->nullable();
            $table->unsignedBigInteger('assigned_ai_agent_id')->nullable();
            $table->string('assigned_human_role')->nullable(); // Owner, Admin, etc.
            $table->dateTime('due_date')->nullable();
            $table->text('completion_criteria')->nullable();
            $table->string('status')->default('open'); // open, analyzing, in_progress, resolved, closed
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_needs');
    }
};
