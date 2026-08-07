<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responder presence.
 *
 * Broadcasting to "nearby" responders is impossible without knowing where
 * they are, so this is the prerequisite for dispatch rather than a nicety.
 *
 * A row exists per responder per type. Going offline sets a flag rather than
 * deleting the row, so ratings, trust score and completion history survive.
 *
 * Location is a coarse last-known fix, refreshed while a responder is online.
 * It is never exposed to customers -- only the derived distance is, and only
 * once the responder has accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_stranded_responders')) {
            Schema::create('urban_goodz_stranded_responders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                // samaritan | professional | mobile_mechanic | tow | fleet
                $table->string('responder_type', 20)->default('samaritan');

                $table->boolean('is_online')->default(false);
                $table->decimal('last_latitude', 10, 7)->nullable();
                $table->decimal('last_longitude', 10, 7)->nullable();
                $table->timestamp('last_seen_at')->nullable();

                // How far this responder is willing to travel. Broadcasts
                // respect it, so nobody is pestered about jobs across town.
                $table->unsignedSmallInteger('max_travel_miles')->default(25);

                $table->decimal('rating', 3, 2)->nullable();
                $table->unsignedSmallInteger('trust_score')->nullable();
                $table->unsignedInteger('completed_jobs')->default(0);
                $table->unsignedInteger('declined_jobs')->default(0);
                $table->unsignedInteger('missed_jobs')->default(0);

                // Set while a responder holds a live assignment, so dispatch
                // does not offer them a second rescue mid-job.
                $table->unsignedBigInteger('active_request_id')->nullable();

                $table->timestamps();

                $table->unique(['user_id', 'responder_type'], 'ug_st_resp_user_type_unique');
                $table->index(['is_online', 'responder_type'], 'ug_st_resp_online_type_idx');
                $table->index(['last_latitude', 'last_longitude'], 'ug_st_resp_latlng_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_responders');
    }
};
