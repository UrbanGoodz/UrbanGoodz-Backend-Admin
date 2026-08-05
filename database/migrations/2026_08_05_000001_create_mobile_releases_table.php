<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mobile_releases')) {
            Schema::create('mobile_releases', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('app_name', 50)->index(); // shopper, vendor, driver
                $table->string('platform', 20)->default('android')->index(); // android, ios
                $table->string('version_name', 30); // e.g. 1.2.0
                $table->unsignedBigInteger('build_number')->index(); // e.g. 10200
                $table->string('minimum_version_name', 30)->nullable();
                $table->unsignedBigInteger('minimum_build_number')->default(1);
                $table->boolean('required')->default(false); // Force Update
                $table->string('apk_url', 500)->nullable();
                $table->unsignedBigInteger('file_id')->nullable();
                $table->text('release_notes')->nullable();
                $table->string('sha256', 64)->nullable();
                $table->string('signing_fingerprint', 128)->nullable();
                $table->timestamp('release_date')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->unsignedTinyInteger('staged_rollout_percent')->default(100);
                $table->string('rollback_version', 30)->nullable();
                $table->unsignedBigInteger('download_count')->default(0);
                $table->unsignedBigInteger('install_count')->default(0);
                $table->unsignedBigInteger('crash_count')->default(0);
                $table->timestamps();

                $table->index(['app_name', 'platform', 'enabled', 'build_number'], 'app_platform_build_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_releases');
    }
};
