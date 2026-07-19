<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_companion_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Customer/User
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('current_page')->nullable(); // viewed page/module
            $table->json('conversation_context')->nullable();
            $table->string('active_workflow')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->json('dismissal_history')->nullable();
            $table->dateTime('snooze_until')->nullable();
            $table->json('promotion_preferences')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_companion_contexts');
    }
};
