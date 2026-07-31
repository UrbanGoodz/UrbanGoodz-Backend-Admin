<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_events')) {
            Schema::table('urban_goodz_events', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_events', 'slug')) $table->string('slug')->unique()->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'category')) $table->string('category')->nullable()->index();
                if (!Schema::hasColumn('urban_goodz_events', 'venue_name')) $table->string('venue_name')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'venue_address')) $table->text('venue_address')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'latitude')) $table->decimal('latitude', 10, 7)->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'longitude')) $table->decimal('longitude', 10, 7)->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'city')) $table->string('city')->nullable()->index();
                if (!Schema::hasColumn('urban_goodz_events', 'zone_id')) $table->unsignedBigInteger('zone_id')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'recurrence_rule')) $table->string('recurrence_rule')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'recurrence_end')) $table->datetime('recurrence_end')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'is_free')) $table->boolean('is_free')->default(false);
                if (!Schema::hasColumn('urban_goodz_events', 'ticket_url')) $table->string('ticket_url')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'age_restriction')) $table->string('age_restriction')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'images')) $table->json('images')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'creator_appearances')) $table->json('creator_appearances')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'participating_businesses')) $table->json('participating_businesses')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'organiser_user_id')) $table->unsignedBigInteger('organiser_user_id')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'organiser_type')) $table->string('organiser_type')->default('admin');
                if (!Schema::hasColumn('urban_goodz_events', 'source')) $table->string('source')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'source_url')) $table->string('source_url')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'source_date')) $table->datetime('source_date')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'validation_state')) $table->string('validation_state')->default('pending');
                if (!Schema::hasColumn('urban_goodz_events', 'duplicate_state')) $table->string('duplicate_state')->default('none');
                if (!Schema::hasColumn('urban_goodz_events', 'approval_state')) $table->string('approval_state')->default('pending');
                if (!Schema::hasColumn('urban_goodz_events', 'visibility_state')) $table->string('visibility_state')->default('hidden');
                if (!Schema::hasColumn('urban_goodz_events', 'failure_reason')) $table->text('failure_reason')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'retry_count')) $table->integer('retry_count')->default(0);
                if (!Schema::hasColumn('urban_goodz_events', 'last_verified_at')) $table->datetime('last_verified_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'published_at')) $table->datetime('published_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'cancelled_at')) $table->datetime('cancelled_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'expires_at')) $table->datetime('expires_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'featured_at')) $table->datetime('featured_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'admin_notes')) $table->text('admin_notes')->nullable();
                if (!Schema::hasColumn('urban_goodz_events', 'moderation_notes')) $table->text('moderation_notes')->nullable();
            });
        }

        if (Schema::hasTable('urban_goodz_creator_profiles')) {
            Schema::table('urban_goodz_creator_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'user_id')) $table->unsignedBigInteger('user_id')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'categories')) $table->json('categories')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'audience_info')) $table->json('audience_info')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'portfolio')) $table->json('portfolio')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'verification_status')) $table->string('verification_status')->default('unverified');
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'verified_at')) $table->datetime('verified_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'follower_count')) $table->integer('follower_count')->default(0);
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'following_count')) $table->integer('following_count')->default(0);
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'reel_count')) $table->integer('reel_count')->default(0);
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'source')) $table->string('source')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'source_url')) $table->string('source_url')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'source_date')) $table->datetime('source_date')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'validation_state')) $table->string('validation_state')->default('valid');
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'duplicate_state')) $table->string('duplicate_state')->default('none');
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'approval_state')) $table->string('approval_state')->default('pending');
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'visibility_state')) $table->string('visibility_state')->default('hidden');
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'failure_reason')) $table->text('failure_reason')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'retry_count')) $table->integer('retry_count')->default(0);
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'last_verified_at')) $table->datetime('last_verified_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_creator_profiles', 'payout_setup_status')) $table->string('payout_setup_status')->default('not_started');
            });
        }

        if (!Schema::hasTable('urban_goodz_event_saves')) {
            Schema::create('urban_goodz_event_saves', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('event_id');
                $table->timestamps();
                
                $table->unique(['user_id', 'event_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('event_id')->references('id')->on('urban_goodz_events')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('urban_goodz_event_reminders')) {
            Schema::create('urban_goodz_event_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('event_id');
                $table->datetime('remind_at');
                $table->timestamps();
                
                $table->unique(['user_id', 'event_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('event_id')->references('id')->on('urban_goodz_events')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('urban_goodz_reel_comments')) {
            Schema::create('urban_goodz_reel_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reel_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('content');
                $table->string('status')->default('active');
                $table->timestamps();
                
                $table->index(['reel_id', 'status']);
                $table->foreign('reel_id')->references('id')->on('urban_goodz_reels')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('parent_id')->references('id')->on('urban_goodz_reel_comments')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_followers')) {
            Schema::create('urban_goodz_creator_followers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('creator_profile_id');
                $table->timestamps();
                
                $table->unique(['user_id', 'creator_profile_id'], 'creator_followers_unique');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('creator_profile_id')->references('id')->on('urban_goodz_creator_profiles')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_blocks')) {
            Schema::create('urban_goodz_creator_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('blocker_user_id');
                $table->unsignedBigInteger('blocked_user_id');
                $table->timestamps();
                
                $table->unique(['blocker_user_id', 'blocked_user_id'], 'creator_blocks_unique');
                $table->foreign('blocker_user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('blocked_user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('urban_goodz_sourcing_records')) {
            Schema::create('urban_goodz_sourcing_records', function (Blueprint $table) {
                $table->id();
                $table->string('sourceable_type');
                $table->unsignedBigInteger('sourceable_id');
                $table->string('source');
                $table->string('source_url')->nullable();
                $table->datetime('source_date')->nullable();
                $table->string('validation_state')->default('pending');
                $table->string('duplicate_state')->default('none');
                $table->string('approval_state')->default('pending');
                $table->string('visibility_state')->default('hidden');
                $table->text('failure_reason')->nullable();
                $table->string('retry_state')->default('none');
                $table->integer('retry_count')->default(0);
                $table->datetime('last_verified_at')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->json('audit_log')->nullable();
                $table->timestamps();
                
                $table->index(['sourceable_type', 'sourceable_id'], 'sourcing_records_morph_index');
            });
        }

        if (!Schema::hasTable('urban_goodz_sourcing_leads')) {
            Schema::create('urban_goodz_sourcing_leads', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('social_url')->nullable();
                $table->string('website_url')->nullable();
                $table->string('category')->nullable();
                $table->string('city')->nullable();
                $table->string('zone')->nullable();
                $table->text('notes')->nullable();
                $table->string('outreach_status')->default('new');
                $table->unsignedBigInteger('outreach_assigned_to')->nullable();
                $table->text('outreach_notes')->nullable();
                $table->string('converted_record_type')->nullable();
                $table->unsignedBigInteger('converted_record_id')->nullable();
                $table->string('source');
                $table->string('source_url')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_event_venues')) {
            Schema::create('urban_goodz_event_venues', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('city')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->integer('capacity')->nullable();
                $table->text('description')->nullable();
                $table->json('images')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('contact_email')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Add down logic if necessary
    }
};
