<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stranded Roadside Assistance.
 *
 * Three tables:
 *   services  -- the admin-editable catalogue and its base pricing
 *   requests  -- one stranded motorist asking for help
 *   offers    -- one row per provider a request was broadcast to
 *
 * The offer row is what makes "first qualified provider to accept wins"
 * enforceable: acceptance is a conditional update against a single offer row,
 * so two providers tapping Accept at the same instant cannot both win.
 *
 * Every index is named explicitly. Auto-generated names on a 29-character
 * table prefix run past MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_roadside_services')) {
            Schema::create('urban_goodz_roadside_services', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('icon', 60)->nullable();

                // Money is stored in minor units, matching
                // urban_goodz_service_requests.quoted_amount_minor.
                $table->unsignedBigInteger('base_price_min_minor')->default(0);
                $table->unsignedBigInteger('base_price_max_minor')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('pricing_note', 160)->nullable();

                // Whether a verified community member may take this job, or
                // whether it demands a licensed professional. Towing, recovery
                // and hazardous scenes must never reach a Samaritan.
                $table->boolean('samaritan_eligible')->default(false);
                $table->unsignedSmallInteger('typical_duration_minutes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index(['enabled', 'sort_order'], 'ug_rs_services_enabled_sort_idx');
                $table->index('samaritan_eligible', 'ug_rs_services_samaritan_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_roadside_requests')) {
            Schema::create('urban_goodz_roadside_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('request_number', 40)->unique();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('service_slug', 60)->nullable();

                // draft, awaiting_payment, broadcasting, assigned, en_route,
                // on_scene, completed, cancelled, expired, no_providers
                $table->string('status', 30)->default('draft');

                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('address', 500)->nullable();
                $table->text('location_notes')->nullable();

                $table->string('vehicle_make', 60)->nullable();
                $table->string('vehicle_model', 60)->nullable();
                $table->string('vehicle_year', 8)->nullable();
                $table->string('vehicle_color', 40)->nullable();
                $table->string('vehicle_plate', 20)->nullable();

                $table->text('notes')->nullable();
                $table->longText('photos')->nullable();

                // "I am in an unsafe location" -- highest dispatch priority.
                $table->boolean('is_emergency')->default(false);
                // Customer choice: professionals only, or allow Samaritans.
                $table->boolean('allow_samaritans')->default(true);

                $table->string('assigned_provider_type', 20)->nullable();
                $table->unsignedBigInteger('assigned_provider_id')->nullable();
                $table->timestamp('assigned_at')->nullable();

                $table->unsignedBigInteger('quoted_amount_minor')->default(0);
                $table->unsignedBigInteger('platform_fee_minor')->default(0);
                $table->unsignedBigInteger('tip_minor')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('payment_status', 30)->default('unpaid');
                $table->unsignedBigInteger('payment_transaction_id')->nullable();

                // Radius-expanding broadcast: 10 -> 20 -> 35 -> 50 miles.
                $table->unsignedSmallInteger('broadcast_radius_miles')->default(10);
                $table->timestamp('broadcast_expires_at')->nullable();
                $table->unsignedSmallInteger('broadcast_round')->default(0);

                $table->timestamp('en_route_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'broadcast_expires_at'], 'ug_rs_req_status_expiry_idx');
                $table->index(['user_id', 'status'], 'ug_rs_req_user_status_idx');
                $table->index(['zone_id', 'status'], 'ug_rs_req_zone_status_idx');
                $table->index(['latitude', 'longitude'], 'ug_rs_req_latlng_idx');
                $table->index('is_emergency', 'ug_rs_req_emergency_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_roadside_offers')) {
            Schema::create('urban_goodz_roadside_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('provider_id');
                // professional, samaritan, mobile_mechanic, tow, fleet
                $table->string('provider_type', 20)->default('professional');

                $table->decimal('distance_miles', 6, 2)->nullable();
                $table->unsignedBigInteger('payout_minor')->default(0);
                $table->unsignedSmallInteger('broadcast_round')->default(0);

                // offered, accepted, declined, expired, superseded
                $table->string('status', 20)->default('offered');
                $table->timestamp('offered_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['request_id', 'status'], 'ug_rs_offers_request_status_idx');
                $table->index(['provider_id', 'status'], 'ug_rs_offers_provider_status_idx');
                $table->index('expires_at', 'ug_rs_offers_expiry_idx');
                // A provider is offered a given request at most once per round.
                $table->unique(['request_id', 'provider_id', 'broadcast_round'], 'ug_rs_offers_unique_round');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_roadside_offers');
        Schema::dropIfExists('urban_goodz_roadside_requests');
        Schema::dropIfExists('urban_goodz_roadside_services');
    }
};
