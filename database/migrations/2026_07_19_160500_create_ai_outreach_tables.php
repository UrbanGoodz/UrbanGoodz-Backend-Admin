<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_outreach_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // first_introduction, demand_introduction, followup_1, followup_2, final_followup, interested_reply, info_requested, onboarding_invitation, meeting_invitation, wrong_contact, opt_out_confirmation
            $table->string('name');
            $table->string('subject');
            $table->text('body'); // Blade-like template with {{variables}}
            $table->string('category')->default('outreach'); // outreach, reply, system
            $table->unsignedInteger('sequence_day')->nullable(); // 0, 3, 7, 12
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_prospect_id')->constrained('merchant_prospects')->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('ai_task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->foreignId('ai_outreach_template_id')->nullable()->constrained('ai_outreach_templates')->nullOnDelete();
            $table->string('direction')->default('outbound'); // outbound, inbound
            $table->string('channel')->default('email'); // email
            $table->string('to_email')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->text('personalization_context')->nullable(); // AI-generated personalization notes
            $table->string('status')->default('draft'); // draft, queued, sent, delivered, bounced, failed, opened, clicked
            $table->string('idempotency_key')->unique()->nullable();
            $table->unsignedInteger('sequence_step')->default(0); // 0=first, 1=followup1, 2=followup2, 3=final
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type')->nullable(); // hard, soft
            $table->string('reply_classification')->nullable(); // same as reply_status on prospect
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['merchant_prospect_id', 'status']);
            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_outreach_messages');
        Schema::dropIfExists('ai_outreach_templates');
    }
};
