<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('vendors')->nullOnDelete();
            $table->string('approval_status', 24)->default('pending')->index();
            $table->json('location_modes')->nullable();
            $table->decimal('rating', 4, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unique('vendor_id', 'ug_service_provider_vendor_unique');
        });
        Schema::create('urban_goodz_provider_services', function (Blueprint $table) {
            $table->id(); $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->string('category', 64)->index(); $table->string('name'); $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes'); $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('deposit_minor')->default(0); $table->string('currency', 3)->default('USD');
            $table->boolean('requires_quote')->default(false); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('urban_goodz_provider_availability', function (Blueprint $table) {
            $table->id(); $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); $table->time('starts_at'); $table->time('ends_at');
            $table->string('timezone', 64)->default('America/Chicago'); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->index(['provider_id', 'day_of_week']);
        });
        Schema::table('urban_goodz_service_requests', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->after('assigned_vendor_id')->constrained('urban_goodz_service_providers')->nullOnDelete();
            $table->foreignId('provider_service_id')->nullable()->after('provider_id')->constrained('urban_goodz_provider_services')->nullOnDelete();
            $table->string('location_mode', 24)->default('in_person'); $table->text('location_details')->nullable();
            $table->dateTime('requested_start_at')->nullable()->index(); $table->dateTime('scheduled_at')->nullable()->index();
            $table->unsignedBigInteger('quoted_amount_minor')->nullable(); $table->unsignedBigInteger('deposit_amount_minor')->default(0);
            $table->string('currency', 3)->default('USD'); $table->text('provider_notes')->nullable();
            $table->text('cancellation_reason')->nullable(); $table->string('payment_status', 24)->default('not_required');
            $table->timestamp('accepted_at')->nullable(); $table->timestamp('completed_at')->nullable();
        });
        Schema::create('urban_goodz_service_booking_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_request_id')->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->string('actor_type', 24); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('urban_goodz_service_provider_earnings', function (Blueprint $table) {
            $table->id(); $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->foreignId('service_request_id')->unique()->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_amount_minor'); $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('provider_amount_minor'); $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('pending'); $table->timestamps();
        });
        Schema::create('urban_goodz_service_reviews', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_request_id')->unique()->constrained('urban_goodz_service_requests')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('urban_goodz_service_providers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_service_reviews'); Schema::dropIfExists('urban_goodz_service_provider_earnings');
        Schema::dropIfExists('urban_goodz_service_booking_events');
        Schema::table('urban_goodz_service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id'); $table->dropConstrainedForeignId('provider_id'); $table->dropConstrainedForeignId('provider_service_id');
            $table->dropColumn(['location_mode','location_details','requested_start_at','scheduled_at','quoted_amount_minor','deposit_amount_minor','currency','provider_notes','cancellation_reason','payment_status','accepted_at','completed_at']);
        });
        Schema::dropIfExists('urban_goodz_provider_availability'); Schema::dropIfExists('urban_goodz_provider_services');
        Schema::table('urban_goodz_service_providers', function (Blueprint $table) {
            $table->dropUnique('ug_service_provider_vendor_unique'); $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn(['approval_status','location_modes','rating','rating_count']);
        });
    }
};
