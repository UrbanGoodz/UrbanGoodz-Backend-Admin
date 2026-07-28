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
            $table->string('event_id')->nullable();
            $table->string('event_type')->index();
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_webhook_events');
    }
};
