<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_creator_applications')) {
            Schema::table('urban_goodz_creator_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_creator_applications', 'niche')) {
                    $table->string('niche')->nullable()->after('follower_count');
                }
                if (!Schema::hasColumn('urban_goodz_creator_applications', 'city')) {
                    $table->string('city')->nullable()->after('niche');
                }
                if (!Schema::hasColumn('urban_goodz_creator_applications', 'market')) {
                    $table->string('market')->nullable()->after('city');
                }
                if (!Schema::hasColumn('urban_goodz_creator_applications', 'social_links')) {
                    $table->json('social_links')->nullable()->after('market');
                }
                if (!Schema::hasColumn('urban_goodz_creator_applications', 'content_samples')) {
                    $table->json('content_samples')->nullable()->after('social_links');
                }
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_profiles')) {
            Schema::create('urban_goodz_creator_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_profiles_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->string('handle')->unique()->nullable();
                $table->string('display_name')->nullable();
                $table->text('bio')->nullable();
                $table->string('avatar_url')->nullable();
                $table->string('banner_url')->nullable();
                $table->string('city')->nullable();
                $table->string('zone')->nullable();
                $table->json('niches')->nullable();
                $table->json('social_links')->nullable();
                $table->json('content_samples')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('featured_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_campaigns')) {
            Schema::create('urban_goodz_creator_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('type');
                $table->string('category')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->foreign('vendor_id', 'ug_creator_campaigns_vendor_fk')
                      ->references('id')->on('vendors')
                      ->nullOnDelete();
                $table->string('city')->nullable();
                $table->string('zone')->nullable();
                $table->string('pay_type')->default('flat');
                $table->decimal('flat_payout', 12, 2)->nullable();
                $table->decimal('commission_rate', 5, 2)->nullable();
                $table->dateTime('deadline')->nullable();
                $table->text('deliverables')->nullable();
                $table->text('brief')->nullable();
                $table->string('status')->default('draft');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_campaign_assignments')) {
            Schema::create('urban_goodz_creator_campaign_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->foreign('campaign_id', 'ug_creator_assign_camp_fk')
                      ->references('id')->on('urban_goodz_creator_campaigns')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('creator_profile_id')->nullable();
                $table->foreign('creator_profile_id', 'ug_creator_assign_profile_fk')
                      ->references('id')->on('urban_goodz_creator_profiles')
                      ->nullOnDelete();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_assign_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->string('approval_status')->default('pending');
                $table->text('creator_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_content')) {
            Schema::create('urban_goodz_creator_content', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_profile_id')->nullable();
                $table->foreign('creator_profile_id', 'ug_creator_content_profile_fk')
                      ->references('id')->on('urban_goodz_creator_profiles')
                      ->nullOnDelete();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_content_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->foreign('campaign_id', 'ug_creator_content_camp_fk')
                      ->references('id')->on('urban_goodz_creator_campaigns')
                      ->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('content_type')->default('video');
                $table->json('media_urls')->nullable();
                $table->string('linked_vendor_type')->nullable();
                $table->unsignedBigInteger('linked_vendor_id')->nullable();
                $table->string('linked_vendor_name')->nullable();
                $table->string('cta_label')->nullable();
                $table->string('cta_url')->nullable();
                $table->integer('likes_count')->default(0);
                $table->integer('shares_count')->default(0);
                $table->integer('saves_count')->default(0);
                $table->integer('clicks_count')->default(0);
                $table->boolean('is_published')->default(false);
                $table->boolean('is_shoppable')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->string('status')->default('draft');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_earnings')) {
            Schema::create('urban_goodz_creator_earnings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_profile_id')->nullable();
                $table->foreign('creator_profile_id', 'ug_creator_earnings_profile_fk')
                      ->references('id')->on('urban_goodz_creator_profiles')
                      ->nullOnDelete();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_earnings_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->foreign('campaign_id', 'ug_creator_earnings_camp_fk')
                      ->references('id')->on('urban_goodz_creator_campaigns')
                      ->nullOnDelete();
                $table->unsignedBigInteger('content_id')->nullable();
                $table->foreign('content_id', 'ug_creator_earnings_content_fk')
                      ->references('id')->on('urban_goodz_creator_content')
                      ->nullOnDelete();
                $table->string('type')->default('commission');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('pending');
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_business_leads')) {
            Schema::create('urban_goodz_creator_business_leads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_profile_id')->nullable();
                $table->foreign('creator_profile_id', 'ug_creator_leads_profile_fk')
                      ->references('id')->on('urban_goodz_creator_profiles')
                      ->nullOnDelete();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_leads_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->string('business_name');
                $table->string('category')->nullable();
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('social_link')->nullable();
                $table->string('city')->nullable();
                $table->string('zone')->nullable();
                $table->json('photos')->nullable();
                $table->string('video_url')->nullable();
                $table->text('notes')->nullable();
                $table->string('suggested_module')->nullable();
                $table->string('status')->default('new');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_event_promotions')) {
            Schema::create('urban_goodz_creator_event_promotions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_profile_id')->nullable();
                $table->foreign('creator_profile_id', 'ug_creator_events_profile_fk')
                      ->references('id')->on('urban_goodz_creator_profiles')
                      ->nullOnDelete();
                $table->unsignedBigInteger('creator_application_id')->nullable();
                $table->foreign('creator_application_id', 'ug_creator_events_app_fk')
                      ->references('id')->on('urban_goodz_creator_applications')
                      ->nullOnDelete();
                $table->unsignedBigInteger('event_id');
                $table->foreign('event_id', 'ug_creator_events_event_fk')
                      ->references('id')->on('urban_goodz_events')
                      ->cascadeOnDelete();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->foreign('campaign_id', 'ug_creator_events_camp_fk')
                      ->references('id')->on('urban_goodz_creator_campaigns')
                      ->nullOnDelete();
                $table->string('promo_type')->default('social_post');
                $table->text('promo_content')->nullable();
                $table->string('ticket_link')->nullable();
                $table->string('reservation_link')->nullable();
                $table->string('vendor_booth_name')->nullable();
                $table->string('status')->default('draft');
                $table->decimal('commission_earned', 12, 2)->default(0);
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'urban_goodz_creator_event_promotions',
            'urban_goodz_creator_business_leads',
            'urban_goodz_creator_earnings',
            'urban_goodz_creator_content',
            'urban_goodz_creator_campaign_assignments',
            'urban_goodz_creator_campaigns',
            'urban_goodz_creator_profiles',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
