<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->string('payment_intent_id')->nullable()->after('event_type');
            $table->string('charge_id')->nullable()->after('payment_intent_id');
            $table->timestamp('received_at')->nullable()->after('processed_at');
            $table->string('status')->nullable()->after('received_at');
            $table->string('failure_type')->nullable()->after('status');
        });

        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->unique(['provider', 'event_id'], 'ugwe_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            $table->dropIndex('ugwe_provider_event_unique');
            $table->dropColumn([
                'payment_intent_id',
                'charge_id',
                'received_at',
                'status',
                'failure_type',
            ]);
        });
    }
};
