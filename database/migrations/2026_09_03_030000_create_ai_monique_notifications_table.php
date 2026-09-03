<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_monique_notifications')) {
            Schema::create('ai_monique_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('account_type', 32); // 'admin' | 'vendor'
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->string('category', 64); // 'delayed_orders', 'inventory', 'sales', 'onboarding', etc.
                $table->string('priority', 32)->default('medium'); // 'low', 'medium', 'high', 'urgent'
                $table->string('title', 255);
                $table->text('message');
                $table->json('actions')->nullable(); // Action buttons: Let Monique Handle It, Review, Dismiss
                $table->boolean('is_actionable')->default(true);
                $table->boolean('can_auto_resolve')->default(false);
                $table->boolean('auto_resolved')->default(false);
                $table->string('status', 32)->default('pending'); // 'pending', 'resolved', 'dismissed', 'actioned'
                $table->text('resolution_summary')->nullable();
                $table->json('delivered_channels')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_monique_notifications');
    }
};
