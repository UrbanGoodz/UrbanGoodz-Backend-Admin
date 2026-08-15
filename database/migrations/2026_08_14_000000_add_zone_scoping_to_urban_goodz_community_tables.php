<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_community_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_community_posts', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('id')->constrained('zones')->nullOnDelete();
            }
            if (!Schema::hasColumn('urban_goodz_community_posts', 'is_nationwide')) {
                $table->boolean('is_nationwide')->default(false)->after('zone_id');
            }
            if (!Schema::hasColumn('urban_goodz_community_posts', 'is_worldwide')) {
                $table->boolean('is_worldwide')->default(false)->after('is_nationwide');
            }
            if (!Schema::hasColumn('urban_goodz_community_posts', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('is_worldwide')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('urban_goodz_community_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_community_comments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('post_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('urban_goodz_community_marketplace_items', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_community_marketplace_items', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('id')->constrained('zones')->nullOnDelete();
            }
            if (!Schema::hasColumn('urban_goodz_community_marketplace_items', 'is_nationwide')) {
                $table->boolean('is_nationwide')->default(false)->after('zone_id');
            }
            if (!Schema::hasColumn('urban_goodz_community_marketplace_items', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('is_nationwide')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_community_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['is_nationwide', 'is_worldwide']);
        });

        Schema::table('urban_goodz_community_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('urban_goodz_community_marketplace_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_nationwide');
        });
    }
};
