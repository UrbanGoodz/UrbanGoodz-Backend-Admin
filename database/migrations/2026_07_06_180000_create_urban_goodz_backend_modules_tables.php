<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_service_requests')) {
            Schema::create('urban_goodz_service_requests', function (Blueprint $table) {
                $table->id();
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone')->nullable();
                $table->string('service_type')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->text('admin_notes')->nullable();
                $table->json('preferred_dates')->nullable();
                $table->string('location')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_service_providers')) {
            Schema::create('urban_goodz_service_providers', function (Blueprint $table) {
                $table->id();
                $table->string('business_name');
                $table->string('slug')->unique();
                $table->string('contact_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('service_category')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('service_areas')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_appointments')) {
            Schema::create('urban_goodz_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_request_id')->nullable()->constrained('urban_goodz_service_requests')->nullOnDelete();
                $table->foreignId('service_provider_id')->nullable()->constrained('urban_goodz_service_providers')->nullOnDelete();
                $table->dateTime('scheduled_at');
                $table->dateTime('completed_at')->nullable();
                $table->string('status')->default('scheduled');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_community_posts')) {
            Schema::create('urban_goodz_community_posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('type')->default('general');
                $table->string('author_name')->nullable();
                $table->string('author_email')->nullable();
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_community_comments')) {
            Schema::create('urban_goodz_community_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('urban_goodz_community_posts')->cascadeOnDelete();
                $table->string('author_name')->nullable();
                $table->text('body');
                $table->boolean('is_approved')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_community_marketplace_items')) {
            Schema::create('urban_goodz_community_marketplace_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('condition')->nullable();
                $table->string('seller_name')->nullable();
                $table->string('seller_contact')->nullable();
                $table->string('location')->nullable();
                $table->string('status')->default('available');
                $table->string('image_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_applications')) {
            Schema::create('urban_goodz_creator_applications', function (Blueprint $table) {
                $table->id();
                $table->string('creator_name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('platform')->nullable();
                $table->string('username')->nullable();
                $table->integer('follower_count')->nullable();
                $table->text('bio')->nullable();
                $table->string('status')->default('pending');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_creator_products')) {
            Schema::create('urban_goodz_creator_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creator_application_id')->nullable()->constrained('urban_goodz_creator_applications')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('draft');
                $table->boolean('is_active')->default(false);
                $table->json('media_urls')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_logistics_jobs')) {
            Schema::create('urban_goodz_logistics_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_number')->unique();
                $table->string('pickup_location');
                $table->string('delivery_location');
                $table->dateTime('pickup_by')->nullable();
                $table->dateTime('deliver_by')->nullable();
                $table->text('description')->nullable();
                $table->decimal('weight_kg', 10, 2)->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('assigned_driver_id')->nullable()->constrained('delivery_men')->nullOnDelete();
                $table->decimal('offer_amount', 12, 2)->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_medical_courier_jobs')) {
            Schema::create('urban_goodz_medical_courier_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_number')->unique();
                $table->string('pickup_location');
                $table->string('delivery_location');
                $table->string('specimen_type')->nullable();
                $table->boolean('requires_refrigeration')->default(false);
                $table->boolean('is_biological_hazard')->default(false);
                $table->string('status')->default('pending');
                $table->foreignId('assigned_driver_id')->nullable()->constrained('delivery_men')->nullOnDelete();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_medical_courier_custody_logs')) {
            Schema::create('urban_goodz_medical_courier_custody_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_id')->constrained('urban_goodz_medical_courier_jobs')->cascadeOnDelete();
                $table->string('action');
                $table->string('handler_name');
                $table->text('notes')->nullable();
                $table->timestamp('logged_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_earn_money_opportunities')) {
            Schema::create('urban_goodz_earn_money_opportunities', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('type')->default('referral');
                $table->decimal('reward_amount', 12, 2)->nullable();
                $table->string('reward_type')->default('fixed');
                $table->string('status')->default('active');
                $table->text('terms')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_earn_money_applications')) {
            Schema::create('urban_goodz_earn_money_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('opportunity_id')->constrained('urban_goodz_earn_money_opportunities')->cascadeOnDelete();
                $table->string('applicant_name');
                $table->string('applicant_email')->nullable();
                $table->string('status')->default('pending');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_events')) {
            Schema::create('urban_goodz_events', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('organizer_name')->nullable();
                $table->string('organizer_contact')->nullable();
                $table->decimal('ticket_price', 12, 2)->nullable();
                $table->integer('capacity')->nullable();
                $table->string('status')->default('draft');
                $table->string('image_url')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_plus_memberships')) {
            Schema::create('urban_goodz_plus_memberships', function (Blueprint $table) {
                $table->id();
                $table->string('member_name');
                $table->string('member_email');
                $table->string('tier')->default('basic');
                $table->string('status')->default('active');
                $table->decimal('monthly_fee', 12, 2)->default(0);
                $table->dateTime('subscribed_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->json('benefits')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_spotlight_businesses')) {
            Schema::create('urban_goodz_spotlight_businesses', function (Blueprint $table) {
                $table->id();
                $table->string('business_name');
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->dateTime('featured_until')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('urban_goodz_discovery_searches')) {
            Schema::create('urban_goodz_discovery_searches', function (Blueprint $table) {
                $table->id();
                $table->string('query');
                $table->string('customer_ip')->nullable();
                $table->string('source')->nullable();
                $table->integer('result_count')->nullable();
                $table->boolean('was_fulfilled')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'urban_goodz_discovery_searches',
            'urban_goodz_spotlight_businesses',
            'urban_goodz_plus_memberships',
            'urban_goodz_events',
            'urban_goodz_earn_money_applications',
            'urban_goodz_earn_money_opportunities',
            'urban_goodz_medical_courier_custody_logs',
            'urban_goodz_medical_courier_jobs',
            'urban_goodz_logistics_jobs',
            'urban_goodz_creator_products',
            'urban_goodz_creator_applications',
            'urban_goodz_community_marketplace_items',
            'urban_goodz_community_comments',
            'urban_goodz_community_posts',
            'urban_goodz_appointments',
            'urban_goodz_service_providers',
            'urban_goodz_service_requests',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
