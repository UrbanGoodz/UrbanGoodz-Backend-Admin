<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_action_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('source_agent_id')->nullable();
            $table->unsignedBigInteger('source_task_id')->nullable();
            $table->unsignedBigInteger('source_action_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable(); // admin id or customer/driver id depending on context
            $table->string('assigned_role')->nullable(); // Owner, Admin, Dispatcher, Vendor Support, Customer Support, Finance, Business Operations
            $table->string('business_area')->nullable(); // onboarding, billing, routing, catalog, load_board, compliance
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->dateTime('due_date')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled, escalated
            $table->text('required_action')->nullable();
            $table->text('recommended_next_step')->nullable();
            $table->json('supporting_evidence')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->decimal('financial_impact', 12, 2)->nullable();
            $table->string('risk_level')->default('low'); // low, medium, high
            $table->string('escalation_path')->nullable();
            $table->text('completion_notes')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->dateTime('completion_time')->nullable();
            $table->dateTime('follow_up_date')->nullable();
            $table->timestamps();

            $table->index('assigned_role');
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_action_items');
    }
};
