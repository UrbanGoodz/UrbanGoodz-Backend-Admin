<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_notifications')) {
            return;
        }

        Schema::create('urban_goodz_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type'); // customer, vendor, driver, business_client, dispatcher, admin, creator, service_provider
            $table->unsignedBigInteger('recipient_id');
            $table->string('event_type'); // registration_otp, order_created, order_accepted, etc.
            $table->string('channel'); // firebase_push, in_app, email, sms, websocket, admin_alert
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('pending'); // pending, sent, delivered, failed, read
            $table->timestamp('read_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index('event_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_notifications');
    }
};
