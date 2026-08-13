<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_copilot_recommendations')) {
            Schema::create('ai_copilot_recommendations', function (Blueprint $table) {
                $table->id();
                $table->string('recommendation_type', 100);
                $table->string('recommendation_subtype', 100)->nullable();
                $table->nullableMorphs('relatable');
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->unsignedBigInteger('route_id')->nullable()->index();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->text('suggested_action');
                $table->text('reason');
                $table->decimal('confidence_score', 5, 2)->nullable();
                $table->string('status', 50)->default('pending');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('reviewed_by')->references('id')->on('admins')->onDelete('set null');
                $table->index('recommendation_type');
                $table->index('status');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_copilot_recommendations')) {
            Schema::table('ai_copilot_recommendations', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['reviewed_by']);
                }
            });
            Schema::dropIfExists('ai_copilot_recommendations');
        }
    }
};
