<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_notification_preferences')) {
            return;
        }

        Schema::create('urban_goodz_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type', 20);
            $table->unsignedBigInteger('recipient_id');
            // Service messages (order updates, driver assigned) by push/email: on unless switched off.
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            // Opt-in only. SMS needs explicit consent; promotions are never sent unless the person asked.
            $table->boolean('sms_consent')->default(false);
            $table->timestamp('sms_consent_at')->nullable();
            $table->boolean('promotions_opt_in')->default(false);
            $table->timestamp('promotions_opt_in_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_type', 'recipient_id'], 'ug_notif_pref_recipient_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_notification_preferences');
    }
};
