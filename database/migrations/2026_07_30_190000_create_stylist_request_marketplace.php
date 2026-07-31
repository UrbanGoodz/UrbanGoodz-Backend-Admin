<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stylist Request marketplace.
 *
 * Measurement and photo sharing deliberately has no tables here: it reuses the
 * existing fashion_fit_access_grants / fashion_fit_audit_events stack so that
 * there is exactly one place where Fashion Fit body data can be unlocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_stylist_requests')) {
            Schema::create('urban_goodz_stylist_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('request_type', 32);
                $table->string('title');
                $table->text('description');
                $table->string('garment_type', 64)->nullable();
                $table->string('occasion', 64)->nullable();
                $table->unsignedBigInteger('budget_min_minor')->nullable();
                $table->unsignedBigInteger('budget_max_minor')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->dateTime('deadline_at')->nullable();
                $table->string('service_preference', 16)->default('either');
                $table->string('city')->nullable();
                $table->string('region_code', 16)->nullable();
                $table->string('postal_code', 24)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('visibility', 24)->default('qualified_stylists');
                $table->string('status', 24)->default('draft');
                // The Fashion Fit profile whose approved measurements may be shared.
                $table->unsignedBigInteger('fashion_fit_profile_id')->nullable()->index();
                $table->unsignedBigInteger('awarded_bid_id')->nullable()->index();
                $table->unsignedBigInteger('awarded_provider_id')->nullable()->index();
                $table->unsignedBigInteger('deposit_paid_minor')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'visibility']);
                $table->index(['request_type', 'status']);
            });
        }

        if (!Schema::hasTable('urban_goodz_stylist_request_images')) {
            Schema::create('urban_goodz_stylist_request_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stylist_request_id')->constrained('urban_goodz_stylist_requests')->cascadeOnDelete();
                // Inspiration imagery only. Body photos live in fashion_fit_photos
                // and are never copied here.
                $table->string('media_path', 2048);
                $table->string('caption')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_stylist_request_invites')) {
            Schema::create('urban_goodz_stylist_request_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stylist_request_id')->constrained('urban_goodz_stylist_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('provider_id')->index();
                $table->string('status', 16)->default('invited');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                // Explicit name: the generated one exceeds MySQL's 64-char limit.
                $table->unique(['stylist_request_id', 'provider_id'], 'ug_stylist_invite_request_provider_uq');
            });
        }

        if (!Schema::hasTable('urban_goodz_stylist_bids')) {
            Schema::create('urban_goodz_stylist_bids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stylist_request_id')->constrained('urban_goodz_stylist_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('provider_id')->index();
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('deposit_minor')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->text('message')->nullable();
                $table->unsignedSmallInteger('estimated_days')->nullable();
                $table->boolean('fitting_required')->default(false);
                $table->unsignedTinyInteger('fittings_count')->default(0);
                $table->string('status', 16)->default('submitted');
                // Revisions supersede rather than overwrite, so the Shopper can
                // always see what was originally offered.
                $table->unsignedBigInteger('supersedes_bid_id')->nullable()->index();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->index(['stylist_request_id', 'status']);
            });
        }

        if (!Schema::hasTable('urban_goodz_stylist_bid_milestones')) {
            Schema::create('urban_goodz_stylist_bid_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stylist_bid_id')->constrained('urban_goodz_stylist_bids')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('amount_minor')->default(0);
                $table->dateTime('due_at')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('status', 16)->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Stylist grants are kept separate from fashion_fit_access_grants on
        // purpose: that table is unique on (request_id, vendor_id) where
        // request_id means a *Fashion Fit* request, so reusing it would let a
        // stylist request id collide with a Fashion Fit request id and
        // cross-authorize body data. Auditing still reuses fashion_fit_audit_events.
        if (!Schema::hasTable('urban_goodz_stylist_measurement_grants')) {
            Schema::create('urban_goodz_stylist_measurement_grants', function (Blueprint $table) {
                $table->id();
                // Explicit FK/index names: the generated ones exceed MySQL's 64-char limit.
                $table->foreignId('stylist_request_id')
                    ->constrained('urban_goodz_stylist_requests', 'id', 'ug_stylist_grant_request_fk')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('provider_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('fashion_fit_profile_id')->index('ug_stylist_grant_profile_idx');
                $table->boolean('measurements_allowed')->default(true);
                // Body photos are never shared automatically.
                $table->boolean('photos_allowed')->default(false);
                $table->timestamp('granted_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['stylist_request_id', 'provider_id'], 'ug_stylist_grant_request_provider_uq');
            });
        }

        if (!Schema::hasTable('urban_goodz_stylist_request_messages')) {
            Schema::create('urban_goodz_stylist_request_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stylist_request_id')->constrained('urban_goodz_stylist_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('provider_id')->index();
                $table->string('sender_type', 16);
                $table->unsignedBigInteger('sender_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['stylist_request_id', 'provider_id'], 'ug_stylist_msg_request_provider_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stylist_request_messages');
        Schema::dropIfExists('urban_goodz_stylist_measurement_grants');
        Schema::dropIfExists('urban_goodz_stylist_bid_milestones');
        Schema::dropIfExists('urban_goodz_stylist_bids');
        Schema::dropIfExists('urban_goodz_stylist_request_invites');
        Schema::dropIfExists('urban_goodz_stylist_request_images');
        Schema::dropIfExists('urban_goodz_stylist_requests');
    }
};
