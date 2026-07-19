<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_workforce_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->foreignId('ai_task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->string('action_type'); // research, score, draft_email, send_email, classify_reply, create_task, escalate, approve, reject
            $table->string('target_type')->nullable(); // merchant_prospect, order_anywhere_request, vendor, outreach_message
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('result')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->string('approval_status')->nullable(); // pending, approved, rejected
            $table->string('provider')->nullable(); // openai
            $table->string('model')->nullable(); // gpt-4o
            $table->unsignedInteger('tokens_used')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->string('actor_type')->nullable(); // ai_agent, admin
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();

            $table->index(['ai_agent_id', 'action_type']);
            $table->index(['target_type', 'target_id']);
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workforce_actions');
    }
};
