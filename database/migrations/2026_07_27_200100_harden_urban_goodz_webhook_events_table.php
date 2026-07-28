<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_webhook_events')) {
            return;
        }

        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'payment_intent_id')) {
                $table->string('payment_intent_id')->nullable()->index();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'charge_id')) {
                $table->string('charge_id')->nullable()->index();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'internal_reference')) {
                $table->string('internal_reference')->nullable()->index();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'amount_cents')) {
                $table->bigInteger('amount_cents')->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'currency')) {
                $table->string('currency', 3)->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'processing_status')) {
                $table->string('processing_status')->default('received')->index();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'signature_valid')) {
                $table->boolean('signature_valid')->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'failure_type')) {
                $table->string('failure_type')->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'payload_hash')) {
                $table->char('payload_hash', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'duplicate_count')) {
                $table->unsignedInteger('duplicate_count')->default(0);
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'processing_latency_ms')) {
                $table->unsignedInteger('processing_latency_ms')->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'last_duplicate_at')) {
                $table->timestamp('last_duplicate_at')->nullable();
            }
        });

        $indexes = collect(Schema::getIndexes('urban_goodz_webhook_events'));
        if (! $indexes->contains(fn (array $index) => ($index['name'] ?? null) === 'ug_webhook_provider_event_unique')) {
            Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
                $table->unique(['provider', 'event_id'], 'ug_webhook_provider_event_unique');
            });
        }
    }

    public function down(): void
    {
        // Additive production hardening is intentionally not reversed separately.
    }
};
