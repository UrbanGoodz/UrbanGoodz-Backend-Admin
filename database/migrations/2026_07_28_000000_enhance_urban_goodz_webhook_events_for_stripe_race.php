<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
<<<<<<< HEAD
use Illuminate\Support\Facades\DB;
=======
>>>>>>> origin/fix-load-sourcing-admin-pages-20260729
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
<<<<<<< HEAD
        if (! Schema::hasTable('urban_goodz_webhook_events')) {
            return;
        }

        // Additive and idempotent: an environment may already own some of these
        // columns from an earlier webhook lane, and re-adding one aborts the
        // whole migration.
        Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'payment_intent_id')) {
                $table->string('payment_intent_id')->nullable()->after('event_type');
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'charge_id')) {
                $table->string('charge_id')->nullable()->after('payment_intent_id');
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('processed_at');
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'status')) {
                $table->string('status')->nullable()->after('received_at');
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'failure_type')) {
                $table->string('failure_type')->nullable()->after('status');
            }
        });

        if (! $this->hasIndex('ugwe_provider_event_unique')) {
            Schema::table('urban_goodz_webhook_events', function (Blueprint $table) {
                $table->unique(['provider', 'event_id'], 'ugwe_provider_event_unique');
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(DB::select(
            'SHOW INDEX FROM urban_goodz_webhook_events WHERE Key_name = ?',
            [$name]
        ))->isNotEmpty();
=======
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
>>>>>>> origin/fix-load-sourcing-admin-pages-20260729
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
