<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_webhook_events')) {
            return;
        }

        Schema::create('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('event_id');
            $table->string('event_type')->index();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('charge_id')->nullable()->index();
            $table->string('internal_reference')->nullable()->index();
            $table->bigInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('processing_status')->default('received')->index();
            $table->boolean('signature_valid')->nullable();
            $table->string('failure_type')->nullable();
            $table->char('payload_hash', 64)->nullable()->index();
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('processing_latency_ms')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_duplicate_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'ug_webhook_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_webhook_events');
    }
};
