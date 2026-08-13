<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_import_batches', function (Blueprint $table) {
            $table->string('queue_type')->default('import')->after('module');
            $table->unsignedTinyInteger('priority')->default(100)->after('queue_type');
            $table->longText('source_payload')->nullable()->after('source_platforms');
            $table->longText('classification_summary')->nullable()->after('source_payload');
            $table->longText('validation_summary')->nullable()->after('classification_summary');
            $table->longText('preview_summary')->nullable()->after('validation_summary');
            $table->unsignedInteger('attempt_count')->default(0)->after('status');
            $table->unsignedInteger('max_attempts')->default(3)->after('attempt_count');
            $table->unsignedInteger('total_failed')->default(0)->after('total_needs_review');
            $table->string('failure_code')->nullable()->after('max_attempts');
            $table->text('failure_message')->nullable()->after('failure_code');
            $table->timestamp('retry_after')->nullable()->after('failure_message');
            $table->foreignId('approved_by')->nullable()->after('admin_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rolled_back_by')->nullable()->after('approved_at');
            $table->timestamp('rolled_back_at')->nullable()->after('rolled_back_by');
            $table->text('rollback_reason')->nullable()->after('rolled_back_at');
        });

        Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->after('id')->index();
            $table->string('record_classification')->default('production')->after('created_by_source');
            $table->unsignedBigInteger('duplicate_of_business_id')->nullable()->after('record_classification');
            $table->string('validation_status')->default('pending')->after('duplicate_of_business_id');
            $table->longText('validation_errors')->nullable()->after('validation_status');
            $table->boolean('source_verified')->default(false)->after('validation_errors');
            $table->boolean('api_visible')->default(false)->after('source_verified')->index();
            $table->boolean('shopper_visible')->default(false)->after('api_visible')->index();
            $table->foreignId('reviewed_by')->nullable()->after('shopper_visible');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::table('urban_goodz_sourced_products', function (Blueprint $table) {
            $table->string('admin_review_status')->default('pending')->after('requires_admin_review');
            $table->string('validation_status')->default('pending')->after('admin_review_status');
            $table->longText('validation_errors')->nullable()->after('validation_status');
            $table->boolean('api_visible')->default(false)->after('is_public')->index();
            $table->boolean('shopper_visible')->default(false)->after('api_visible')->index();
        });

        Schema::table('urban_goodz_sourced_images', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->after('id')->index();
            $table->string('image_role')->default('gallery')->after('entity_id');
            $table->boolean('api_visible')->default(false)->after('review_status')->index();
            $table->boolean('shopper_visible')->default(false)->after('api_visible')->index();
        });

        Schema::create('urban_goodz_data_center_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->index();
            $table->string('action');
            $table->longText('snapshot');
            $table->foreignId('admin_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_data_center_revisions');

        Schema::table('urban_goodz_sourced_images', function (Blueprint $table) {
            $table->dropColumn(['import_batch_id', 'image_role', 'api_visible', 'shopper_visible']);
        });

        Schema::table('urban_goodz_sourced_products', function (Blueprint $table) {
            $table->dropColumn([
                'admin_review_status',
                'validation_status',
                'validation_errors',
                'api_visible',
                'shopper_visible',
            ]);
        });

        Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
            $table->dropColumn([
                'import_batch_id',
                'record_classification',
                'duplicate_of_business_id',
                'validation_status',
                'validation_errors',
                'source_verified',
                'api_visible',
                'shopper_visible',
                'reviewed_by',
                'reviewed_at',
            ]);
        });

        Schema::table('urban_goodz_import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'queue_type',
                'priority',
                'source_payload',
                'classification_summary',
                'validation_summary',
                'preview_summary',
                'attempt_count',
                'max_attempts',
                'total_failed',
                'failure_code',
                'failure_message',
                'retry_after',
                'approved_by',
                'approved_at',
                'rolled_back_by',
                'rolled_back_at',
                'rollback_reason',
            ]);
        });
    }
};
