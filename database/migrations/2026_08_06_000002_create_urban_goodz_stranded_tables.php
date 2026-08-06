<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urban Goodz Stranded.
 *
 * Replaces the `urban_goodz_roadside_*` tables created earlier the same day.
 * "Roadside Assistance" is a service *category*; the product is Stranded. The
 * old tables are dropped rather than renamed because the shape changes
 * materially and they hold zero rows -- this is the last moment the rename is
 * free.
 *
 * The important model change is in `offers`. The previous design was a race:
 * first provider to accept won, enforced by a conditional update on one row.
 * Stranded is a marketplace instead -- several responders may accept, each
 * naming their own terms (volunteer, tips only, or a requested amount), and
 * the *customer* picks one. Selection, not acceptance, is what assigns a job.
 *
 * Every index is named explicitly; auto-generated names on these table
 * prefixes exceed MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Zero-row predecessors from 2026_08_06_000001.
        Schema::dropIfExists('urban_goodz_roadside_offers');
        Schema::dropIfExists('urban_goodz_roadside_requests');
        Schema::dropIfExists('urban_goodz_roadside_services');

        if (!Schema::hasTable('urban_goodz_stranded_services')) {
            Schema::create('urban_goodz_stranded_services', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('icon', 60)->nullable();

                $table->unsignedBigInteger('base_price_min_minor')->default(0);
                $table->unsignedBigInteger('base_price_max_minor')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('pricing_note', 160)->nullable();

                // Safety rule, not a preference: a Goodz Samaritan may take a
                // jump start, but never a tow, a recovery or an accident scene.
                $table->boolean('samaritan_eligible')->default(false);
                $table->unsignedSmallInteger('typical_duration_minutes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index(['enabled', 'sort_order'], 'ug_st_services_enabled_sort_idx');
                $table->index('samaritan_eligible', 'ug_st_services_samaritan_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_stranded_requests')) {
            Schema::create('urban_goodz_stranded_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('request_number', 40)->unique();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('service_slug', 60)->nullable();

                // draft, awaiting_fee, broadcasting, awaiting_selection,
                // assigned, en_route, on_scene, completed, cancelled, expired,
                // escalated_professional, no_responders
                $table->string('status', 30)->default('draft');

                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('address', 500)->nullable();
                $table->text('location_notes')->nullable();

                // Tows and recoveries need somewhere to go.
                $table->decimal('destination_latitude', 10, 7)->nullable();
                $table->decimal('destination_longitude', 10, 7)->nullable();
                $table->string('destination_address', 500)->nullable();

                $table->string('vehicle_type', 40)->nullable();
                $table->string('vehicle_make', 60)->nullable();
                $table->string('vehicle_model', 60)->nullable();
                $table->string('vehicle_year', 8)->nullable();
                $table->string('vehicle_color', 40)->nullable();
                $table->string('vehicle_plate', 20)->nullable();

                $table->text('notes')->nullable();
                $table->longText('photos')->nullable();

                $table->unsignedTinyInteger('passenger_count')->nullable();
                // safe, unsafe_location, injury_reported
                $table->string('safety_status', 30)->nullable();
                $table->boolean('is_emergency')->default(false);
                $table->boolean('allow_samaritans')->default(true);

                // $5 Help Request Fee. Belongs entirely to Urban Goodz and is
                // non-refundable once the request has been broadcast, so the
                // moment of broadcast is recorded alongside it.
                $table->unsignedBigInteger('help_request_fee_minor')->default(500);
                $table->string('help_request_fee_status', 20)->default('unpaid');
                $table->unsignedBigInteger('help_request_fee_transaction_id')->nullable();
                $table->timestamp('broadcast_at')->nullable();

                // Reward the customer offers up front. Held in escrow; zero is
                // valid and means "volunteers only".
                $table->unsignedBigInteger('reward_offer_minor')->default(0);
                // none, held, released, refunded, disputed
                $table->string('escrow_status', 20)->default('none');
                $table->unsignedBigInteger('escrow_transaction_id')->nullable();
                $table->timestamp('escrow_released_at')->nullable();
                $table->unsignedBigInteger('tip_minor')->default(0);
                $table->string('currency', 3)->default('USD');

                // Optional paid upgrade for faster dispatch.
                $table->unsignedBigInteger('priority_upgrade_minor')->default(0);

                $table->string('assigned_responder_type', 20)->nullable();
                $table->unsignedBigInteger('assigned_responder_id')->nullable();
                $table->unsignedBigInteger('selected_offer_id')->nullable();
                $table->timestamp('assigned_at')->nullable();

                // Radius ladder: 10 -> 15 -> 20 -> 25 miles.
                $table->unsignedSmallInteger('broadcast_radius_miles')->default(10);
                $table->timestamp('broadcast_expires_at')->nullable();
                $table->unsignedSmallInteger('broadcast_round')->default(0);

                // When the community window lapses, professionals are offered
                // without the customer having to raise a new request.
                $table->timestamp('escalation_due_at')->nullable();
                $table->timestamp('escalated_at')->nullable();

                $table->timestamp('en_route_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('customer_confirmed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'broadcast_expires_at'], 'ug_st_req_status_expiry_idx');
                $table->index(['status', 'escalation_due_at'], 'ug_st_req_escalation_idx');
                $table->index(['user_id', 'status'], 'ug_st_req_user_status_idx');
                $table->index(['zone_id', 'status'], 'ug_st_req_zone_status_idx');
                $table->index(['latitude', 'longitude'], 'ug_st_req_latlng_idx');
                $table->index('is_emergency', 'ug_st_req_emergency_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_stranded_offers')) {
            Schema::create('urban_goodz_stranded_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('responder_id');
                // samaritan, professional, mobile_mechanic, tow, fleet
                $table->string('responder_type', 20)->default('samaritan');

                $table->decimal('distance_miles', 6, 2)->nullable();
                $table->unsignedSmallInteger('eta_minutes')->nullable();
                $table->unsignedSmallInteger('broadcast_round')->default(0);

                // How the responder wants to be paid:
                // volunteer | tips_only | paid
                $table->string('response_mode', 20)->nullable();
                // Only meaningful when response_mode is `paid`.
                $table->unsignedBigInteger('requested_amount_minor')->default(0);

                // offered  -> broadcast reached them
                // accepted -> they are willing, awaiting the customer's choice
                // selected -> the customer chose them; this is the assignment
                // declined | expired | passed_over
                $table->string('status', 20)->default('offered');

                // Denormalised trust snapshot, so the customer's choice screen
                // reflects what was true at decision time.
                $table->decimal('responder_rating', 3, 2)->nullable();
                $table->unsignedSmallInteger('responder_trust_score')->nullable();
                $table->unsignedInteger('responder_completed_jobs')->nullable();

                $table->timestamp('offered_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('selected_at')->nullable();
                $table->timestamps();

                $table->index(['request_id', 'status'], 'ug_st_offers_request_status_idx');
                $table->index(['responder_id', 'status'], 'ug_st_offers_responder_status_idx');
                $table->index('expires_at', 'ug_st_offers_expiry_idx');
                // One offer per responder per request per round.
                $table->unique(['request_id', 'responder_id', 'broadcast_round'], 'ug_st_offers_unique_round');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_offers');
        Schema::dropIfExists('urban_goodz_stranded_requests');
        Schema::dropIfExists('urban_goodz_stranded_services');
    }
};
