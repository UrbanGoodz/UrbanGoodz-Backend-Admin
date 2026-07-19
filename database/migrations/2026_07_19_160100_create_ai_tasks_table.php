<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->string('task_type'); // research, score, draft_outreach, send_outreach, classify_reply, create_followup, daily_brief
            $table->string('source_type')->nullable(); // order_anywhere_request, merchant_prospect, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('objective')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('pending'); // pending, scheduled, running, completed, failed, cancelled, awaiting_approval
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('idempotency_key')->unique()->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->unsignedBigInteger('assigned_approver_id')->nullable();
            $table->timestamps();

            $table->index(['ai_agent_id', 'status']);
            $table->index(['task_type', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
