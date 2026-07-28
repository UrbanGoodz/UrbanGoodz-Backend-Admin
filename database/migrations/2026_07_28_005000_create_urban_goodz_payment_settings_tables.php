<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_payment_settings')) {
            Schema::create('urban_goodz_payment_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('value');
                $table->string('value_type')->default('string');
                $table->string('source')->default('owner_payment_center');
                $table->unsignedBigInteger('last_changed_by_admin_id')->nullable();
                $table->timestamp('last_changed_at')->nullable();
                $table->timestamps();

                $table->index('last_changed_by_admin_id');
            });
        }

        if (! Schema::hasTable('urban_goodz_payment_setting_audits')) {
            Schema::create('urban_goodz_payment_setting_audits', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->index();
                $table->text('old_value')->nullable();
                $table->text('new_value');
                $table->string('old_source')->nullable();
                $table->string('new_source');
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->string('action');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_setting_audits');
        Schema::dropIfExists('urban_goodz_payment_settings');
    }
};
