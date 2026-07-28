<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_payment_settings')) {
            return;
        }

        Schema::create('urban_goodz_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('source')->default('env');
            $table->string('value_type')->default('string');
            $table->unsignedBigInteger('last_changed_by_admin_id')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('urban_goodz_payment_setting_audits', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('old_source')->nullable();
            $table->string('new_source')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action')->default('update');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('admin_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_setting_audits');
        Schema::dropIfExists('urban_goodz_payment_settings');
    }
};
