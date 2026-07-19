<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_batch_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_batch_id')->constrained('urban_goodz_intake_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('intake_worker');
            $table->string('device_session_id')->nullable();
            $table->string('source_portal')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_active_at')->useCurrent();
            $table->integer('packages_created')->default(0);
            $table->integer('packages_edited')->default(0);
            $table->integer('validation_actions')->default(0);
            $table->integer('approval_actions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['intake_batch_id', 'user_id', 'device_session_id'], 'batch_participant_unique');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_batch_participants');
    }
};
