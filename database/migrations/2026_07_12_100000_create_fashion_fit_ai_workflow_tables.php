<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fashion_fit_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('name');
            $table->enum('units', ['in', 'cm']);
            $table->decimal('calibration_height', 8, 2);
            $table->json('fit_preferences')->nullable();
            $table->string('status')->default('draft')->index();
            $table->decimal('overall_confidence', 5, 4)->nullable();
            $table->string('analysis_provider')->nullable();
            $table->string('model_name')->nullable();
            $table->string('model_version')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sharing_revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fashion_fit_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('consent_version');
            $table->boolean('ai_processing_allowed');
            $table->boolean('measurement_sharing_allowed')->default(false);
            $table->boolean('photo_sharing_allowed')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fashion_fit_provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->unique();
            $table->string('status')->default('pending')->index();
            $table->text('bio')->nullable();
            $table->json('service_categories')->nullable();
            $table->json('credentials')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fashion_fit_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('file_id')->index();
            $table->enum('view', ['front', 'side', 'back']);
            $table->string('status')->default('uploaded')->index();
            $table->json('quality')->nullable();
            $table->json('retake_instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fashion_fit_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('status')->default('uploaded')->index();
            $table->string('provider')->nullable();
            $table->string('model_name')->nullable();
            $table->string('model_version')->nullable();
            $table->decimal('overall_confidence', 5, 4)->nullable();
            $table->json('retake_requirements')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_summary')->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fashion_fit_measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('analysis_id')->nullable()->index();
            $table->string('name');
            $table->decimal('value', 10, 3);
            $table->enum('unit', ['in', 'cm']);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->enum('source', ['ai', 'manual_correction', 'provider_adjustment']);
            $table->boolean('requires_confirmation')->default(false);
            $table->decimal('original_value', 10, 3)->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'name']);
        });

        Schema::create('fashion_fit_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->string('service_type');
            $table->string('garment_type');
            $table->text('notes')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->date('requested_completion_date')->nullable();
            $table->boolean('share_measurements')->default(true);
            $table->boolean('share_photos')->default(false);
            $table->string('status')->default('submitted')->index();
            $table->decimal('accepted_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('payment_status')->default('not_required')->index();
            $table->string('payment_reference')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('access_revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fashion_fit_estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('timeline_days');
            $table->text('notes')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->string('status')->default('submitted')->index();
            $table->timestamps();
        });

        Schema::create('fashion_fit_access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->boolean('measurements_allowed')->default(false);
            $table->boolean('photos_allowed')->default(false);
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'vendor_id']);
        });

        Schema::create('fashion_fit_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'fashion_fit_audit_events', 'fashion_fit_access_grants', 'fashion_fit_estimates',
            'fashion_fit_requests', 'fashion_fit_measurements', 'fashion_fit_analyses',
            'fashion_fit_photos', 'fashion_fit_provider_profiles', 'fashion_fit_consents', 'fashion_fit_profiles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
