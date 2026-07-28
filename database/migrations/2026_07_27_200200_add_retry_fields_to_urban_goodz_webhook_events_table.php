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
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'amount_cents')) {
                $table->bigInteger('amount_cents')->nullable()->after('internal_reference');
            }
            if (! Schema::hasColumn('urban_goodz_webhook_events', 'currency')) {
                $table->string('currency', 3)->nullable()->after('amount_cents');
            }
        });
    }

    public function down(): void
    {
        // These receipt fields are additive audit data and intentionally retained.
    }
};
