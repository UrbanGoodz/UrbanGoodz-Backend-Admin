<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('ai_task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->foreignId('ai_workforce_action_id')->nullable()->constrained('ai_workforce_actions')->nullOnDelete();
            $table->string('event_type'); // policy_check, execution_started, execution_completed, approval_requested, limit_reached, kill_switch_triggered, error
            $table->string('policy_decision')->default('allowed'); // allowed, blocked, approval_required, escalated
            $table->json('request_metadata')->nullable();
            $table->json('result_metadata')->nullable();
            $table->string('actor_type')->default('system'); // system, agent, admin, user
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('status')->default('success'); // success, failure, pending, warning
            $table->string('severity')->default('info'); // info, warning, error, critical
            $table->timestamps();

            $table->index(['ai_agent_id', 'event_type']);
            $table->index('event_type');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_events');
    }
};
