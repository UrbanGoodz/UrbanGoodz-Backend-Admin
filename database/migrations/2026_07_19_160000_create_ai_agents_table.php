<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role');
            $table->text('description')->nullable();
            $table->string('status')->default('inactive'); // active, inactive, paused, error
            $table->unsignedTinyInteger('autonomy_level')->default(2); // 1=observe,2=recommend,3=execute,4=escalate
            $table->json('provider_config')->nullable(); // {"provider":"openai","model":"gpt-4o","temperature":0.3}
            $table->json('allowed_tools')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->json('prohibited_actions')->nullable();
            $table->decimal('confidence_threshold', 5, 4)->default(0.7000);
            $table->unsignedInteger('daily_task_limit')->default(100);
            $table->unsignedInteger('daily_message_limit')->default(50);
            $table->unsignedInteger('daily_token_limit')->default(100000);
            $table->string('assigned_market')->nullable();
            $table->json('assigned_categories')->nullable();
            $table->json('active_hours')->nullable(); // {"start":"08:00","end":"18:00","timezone":"America/Chicago"}
            $table->unsignedBigInteger('escalation_recipient_id')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('kill_switch')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
