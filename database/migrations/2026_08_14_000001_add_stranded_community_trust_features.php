<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stranded is "Find a Goodz Samaritan" -- a community help network, not a
 * priced roadside-services marketplace. This migration adds what a mutual,
 * accountable, in-person meetup actually needs: a short spoken code either
 * side can use to confirm they found the right person, the Samaritan's own
 * vehicle so the customer can recognise it on arrival, and durable records
 * for safety reports and post-assist ratings, neither of which existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_requests', 'help_code')) {
                $table->string('help_code', 8)->nullable()->after('request_number');
            }
        });

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'vehicle_make')) {
                $table->string('vehicle_make', 60)->nullable()->after('max_travel_miles');
                $table->string('vehicle_model', 60)->nullable()->after('vehicle_make');
                $table->string('vehicle_color', 40)->nullable()->after('vehicle_model');
                $table->string('vehicle_plate', 20)->nullable()->after('vehicle_color');
            }
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'capabilities')) {
                // e.g. ["battery","tire","fuel","vehicle","towing","general"] --
                // "I'm willing to try", never a claim of professional certification.
                $table->json('capabilities')->nullable()->after('vehicle_plate');
            }
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'safety_ack_at')) {
                $table->timestamp('safety_ack_at')->nullable()->after('capabilities');
            }
        });

        if (!Schema::hasTable('urban_goodz_stranded_safety_reports')) {
            Schema::create('urban_goodz_stranded_safety_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('urban_goodz_stranded_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('reporter_user_id');
                // customer | responder
                $table->string('reporter_role', 20);
                // not_safe, not_who_claimed, location_incorrect, suspicious_behavior,
                // harassment, threatening_behavior, fraud, other
                $table->string('reason_code', 40);
                $table->text('details')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['request_id'], 'ug_st_reports_request_idx');
                $table->index(['resolved_at'], 'ug_st_reports_resolved_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_stranded_ratings')) {
            Schema::create('urban_goodz_stranded_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('urban_goodz_stranded_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('rater_user_id');
                // customer | responder -- which side is doing the rating
                $table->string('rater_role', 20);
                $table->unsignedBigInteger('ratee_user_id');
                $table->unsignedTinyInteger('stars');
                $table->string('comment', 500)->nullable();
                $table->timestamps();

                // One rating per side per request -- no re-rating to inflate/deflate.
                $table->unique(['request_id', 'rater_role'], 'ug_st_ratings_unique_side');
                $table->index('ratee_user_id', 'ug_st_ratings_ratee_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_ratings');
        Schema::dropIfExists('urban_goodz_stranded_safety_reports');

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            $table->dropColumn(['vehicle_make', 'vehicle_model', 'vehicle_color', 'vehicle_plate', 'capabilities', 'safety_ack_at']);
        });

        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            $table->dropColumn('help_code');
        });
    }
};
