<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Goodz Samaritan's photo and their registered vehicle's photo live on the
 * PROFILE, set once, so accepting a normal Stranded request needs no extra
 * step -- the customer sees who is coming from the verified profile alone.
 *
 * The exception is a Samaritan responding in a vehicle other than the one on
 * file. That case is per-offer, not per-profile: the override columns on
 * urban_goodz_stranded_offers hold it, nullable and unused on every normal
 * acceptance. UrbanGoodzStrandedOffer::effectiveVehicle() is what decides
 * which set of fields the customer actually sees.
 *
 * These photos are customer-facing (the whole point is recognising the
 * person and car that show up), unlike UrbanGoodzStrandedVerification's
 * license/selfie columns, which stay on the private disk and are never
 * turned into a URL. Different trust boundary, different storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'profile_photo_path')) {
                $table->string('profile_photo_path', 255)->nullable()->after('safety_ack_at');
                $table->string('vehicle_photo_path', 255)->nullable()->after('profile_photo_path');
            }
        });

        Schema::table('urban_goodz_stranded_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_offers', 'uses_alternate_vehicle')) {
                $table->boolean('uses_alternate_vehicle')->default(false)->after('responder_completed_jobs');
                // Populated only when uses_alternate_vehicle is true. Null
                // otherwise, meaning "use the responder's profile vehicle."
                $table->string('vehicle_make', 60)->nullable()->after('uses_alternate_vehicle');
                $table->string('vehicle_model', 60)->nullable()->after('vehicle_make');
                $table->string('vehicle_color', 40)->nullable()->after('vehicle_model');
                $table->string('vehicle_plate', 20)->nullable()->after('vehicle_color');
                $table->string('vehicle_photo_path', 255)->nullable()->after('vehicle_plate');
                // Set once the offer has everything the customer needs to see
                // (profile photo + a vehicle, whichever source). Null means
                // still waiting -- this is what isSelectable() checks in
                // addition to status, so an incomplete responder is never
                // choosable.
                $table->timestamp('identity_ready_at')->nullable()->after('vehicle_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_stranded_offers', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_stranded_offers', 'uses_alternate_vehicle')) {
                $table->dropColumn([
                    'uses_alternate_vehicle', 'vehicle_make', 'vehicle_model',
                    'vehicle_color', 'vehicle_plate', 'vehicle_photo_path', 'identity_ready_at',
                ]);
            }
        });

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_stranded_responders', 'profile_photo_path')) {
                $table->dropColumn(['profile_photo_path', 'vehicle_photo_path']);
            }
        });
    }
};
