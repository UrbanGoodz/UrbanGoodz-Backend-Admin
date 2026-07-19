<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_intake_batch_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_batch_id')->constrained('urban_goodz_intake_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('device_session_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['intake_batch_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_intake_batch_audits');
    }
};
