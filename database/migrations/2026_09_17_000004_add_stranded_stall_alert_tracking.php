<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Monique stall alert per assignment, not one per dispatch-tick run.
 * Without a marker, a request stuck past the threshold would page ops again
 * every single minute until someone acted on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_requests', 'stall_alert_sent_at')) {
                $table->timestamp('stall_alert_sent_at')->nullable()->after('escalated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_stranded_requests', 'stall_alert_sent_at')) {
                $table->dropColumn('stall_alert_sent_at');
            }
        });
    }
};
