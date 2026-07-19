<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_batch_package_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_package_id')->nullable()->constrained('urban_goodz_batch_packages')->nullOnDelete();
            $table->foreignId('intake_batch_id')->constrained('urban_goodz_intake_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('conflict_with_user_id')->nullable();
            $table->string('conflict_timestamp')->nullable();
            $table->string('device_session_id')->nullable();
            $table->integer('version_before')->nullable();
            $table->integer('version_after')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_package_id', 'created_at'], 'ug_pkg_audits_pkg_time_idx');
            $table->index(['intake_batch_id', 'action'], 'ug_pkg_audits_batch_action_idx');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_batch_package_audits');
    }
};
