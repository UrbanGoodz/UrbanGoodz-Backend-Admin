<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fashion_fit_measurements', function (Blueprint $table) {
            $table->string('provenance')->nullable()->index()->after('source');
            $table->enum('status', ['measured', 'needs_better_photo', 'customer_confirmation_required'])
                ->default('measured')->index()->after('provenance');
            $table->json('landmark_sources')->nullable()->after('status');
            $table->json('calculation')->nullable()->after('landmark_sources');
        });

        Schema::table('fashion_fit_analyses', function (Blueprint $table) {
            $table->json('quality_warnings')->nullable()->after('retake_requirements');
            $table->json('unavailable_measurements')->nullable()->after('quality_warnings');
            $table->string('source_hash', 64)->nullable()->after('response_hash');
            $table->json('source_files')->nullable()->after('source_hash');
            $table->index(['profile_id', 'source_hash']);
        });

        Schema::create('fashion_fit_measurement_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('measurement_id')->index();
            $table->unsignedBigInteger('analysis_id')->nullable()->index();
            $table->decimal('value', 10, 3);
            $table->enum('unit', ['in', 'cm']);
            $table->enum('source', ['ai', 'manual_correction', 'provider_adjustment']);
            $table->string('provenance')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->boolean('corrected')->default(false);
            $table->boolean('approved')->default(false);
            $table->timestamps();
            $table->index(['measurement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fashion_fit_measurement_versions');

        Schema::table('fashion_fit_analyses', function (Blueprint $table) {
            $table->dropIndex(['profile_id', 'source_hash']);
            $table->dropColumn(['source_files', 'source_hash', 'unavailable_measurements', 'quality_warnings']);
        });

        Schema::table('fashion_fit_measurements', function (Blueprint $table) {
            $table->dropColumn(['calculation', 'landmark_sources', 'status', 'provenance']);
        });
    }
};
