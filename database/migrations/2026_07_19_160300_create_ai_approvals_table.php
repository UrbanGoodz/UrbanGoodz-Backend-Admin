<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_workforce_action_id')->constrained('ai_workforce_actions')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_approver_id')->nullable();
            $table->text('approval_reason')->nullable();
            $table->string('risk_level')->default('medium'); // low, medium, high, critical
            $table->string('decision')->default('pending'); // pending, approved, rejected
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('decision');
            $table->index('requested_approver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_approvals');
    }
};
