<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opt-in (never on by default), with the time it was given or withdrawn, like SMS and promotions.
        if (Schema::hasTable('urban_goodz_notification_preferences')) {
            Schema::table('urban_goodz_notification_preferences', function (Blueprint $table) {
                if (! Schema::hasColumn('urban_goodz_notification_preferences', 'nearby_help_opt_in')) {
                    $table->boolean('nearby_help_opt_in')->default(false);
                    $table->timestamp('nearby_help_opt_in_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('urban_goodz_stranded_nearby_presence')) {
            // Where an opted-in user roughly is, only so a nearby Stranded request can reach them. Coordinates
            // are rounded (about 100 m) before they are stored and every row expires on its own.
            Schema::create('urban_goodz_stranded_nearby_presence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->decimal('latitude', 9, 6);
                $table->decimal('longitude', 9, 6);
                $table->timestamp('reported_at');
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->index(['latitude', 'longitude'], 'ug_stranded_presence_geo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_nearby_presence');
        if (Schema::hasTable('urban_goodz_notification_preferences') && Schema::hasColumn('urban_goodz_notification_preferences', 'nearby_help_opt_in')) {
            Schema::table('urban_goodz_notification_preferences', function (Blueprint $table) {
                $table->dropColumn(['nearby_help_opt_in', 'nearby_help_opt_in_at']);
            });
        }
    }
};
