<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_action_logs')) {
            Schema::create('ai_action_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recommendation_id')->nullable()->constrained('ai_copilot_recommendations')->nullOnDelete();
                $table->string('action_taken');
                $table->string('module')->nullable();
                $table->string('affected_user_type')->nullable();
                $table->unsignedBigInteger('affected_user_id')->nullable();
                $table->text('before_value')->nullable();
                $table->text('after_value')->nullable();
                $table->text('reason')->nullable();
                $table->string('automation_mode', 50)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->boolean('rollback_available')->default(false);
                $table->timestamps();

                $table->index(['affected_user_type', 'affected_user_id']);
                $table->index('action_taken');
                $table->index('module');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_action_logs')) {
            Schema::dropIfExists('ai_action_logs');
        }
    }
};
