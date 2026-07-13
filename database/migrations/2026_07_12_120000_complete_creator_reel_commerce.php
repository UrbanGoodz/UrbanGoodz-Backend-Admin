<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_creator_profiles', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('vendors')->nullOnDelete();
            $table->string('status', 24)->default('pending')->after('is_featured')->index();
            $table->unique('vendor_id', 'ug_creator_profiles_vendor_unique');
        });

        Schema::table('reels', function (Blueprint $table) {
            $table->foreignId('creator_profile_id')->nullable()->after('store_id')
                ->constrained('urban_goodz_creator_profiles')->nullOnDelete();
            $table->string('publication_status', 24)->default('draft')->index();
            $table->string('moderation_status', 24)->default('pending')->index();
            $table->timestamp('published_at')->nullable();
            $table->text('moderation_notes')->nullable();
        });

        Schema::create('creator_reel_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('taggable_type', 24);
            $table->unsignedBigInteger('taggable_id');
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['reel_id', 'taggable_type', 'taggable_id'], 'creator_reel_tag_unique');
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('creator_commerce_attributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignId('creator_profile_id')->constrained('urban_goodz_creator_profiles')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 24);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('commission_rate', 7, 4)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('initiated')->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id'], 'creator_attribution_source_unique');
        });

        Schema::create('creator_reel_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_id')->nullable();
            $table->string('reason', 64);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_reel_reports');
        Schema::dropIfExists('creator_commerce_attributions');
        Schema::dropIfExists('creator_reel_tags');

        Schema::table('reels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creator_profile_id');
            $table->dropColumn(['publication_status', 'moderation_status', 'published_at', 'moderation_notes']);
        });

        Schema::table('urban_goodz_creator_profiles', function (Blueprint $table) {
            $table->dropUnique('ug_creator_profiles_vendor_unique');
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn('status');
        });
    }
};
