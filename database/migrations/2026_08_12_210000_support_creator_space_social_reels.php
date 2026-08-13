<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision (Lane 1 certification, 2026-08-12): Urban Goodz Creator
 * Space is a social-first content platform, not a traditional storefront.
 * A creator must be able to publish a Reel without owning a store/product.
 *
 * The `reels` table (Modules\ReelsModule\Entities\Reel) is already the
 * correct, single source of truth for both vendor-commerce reels AND
 * creator-space social reels - it was already extended with
 * creator_profile_id/publication_status/moderation_status/published_at by
 * 2026_07_12_120000_complete_creator_reel_commerce.php, and
 * Modules\ReelsModule\Entities\Reel::creatorProfile()/commerceTags() already
 * assume a creator can own a reel independently of a store. The one real gap
 * blocking a store-less creator from posting is that `store_id` was created
 * as a required (non-nullable) foreign key in the original ReelsModule
 * migration, which only ever anticipated vendor-authored reels.
 *
 * This migration does NOT create a new reels table (that would duplicate an
 * existing, actively-used table - see App\Models\UrbanGoodzReel, which binds
 * to this same `reels` table). It only:
 *   1. Makes reels.store_id nullable, so a pure creator (no vendor_id on
 *      their urban_goodz_creator_profiles row) can post without a store.
 *   2. Restores the reel_id foreign key on urban_goodz_reel_comments, which
 *      2026_08_11(-ish) 231707f deliberately dropped because no reels target
 *      existed yet at that point in the audit. It exists now (it always did
 *      - `reels`), so the FK can be safely restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reels') && Schema::hasColumn('reels', 'store_id')) {
            Schema::table('reels', function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable()->change();
            });
        }

        // Engagement counters already exist for views/likes/store-visits
        // (total_views/total_likes/total_store_visits); "shares" was
        // referenced by ReelSocialController::shareReel() but never had a
        // backing column. Added following the same total_* convention.
        if (Schema::hasTable('reels') && ! Schema::hasColumn('reels', 'total_shares')) {
            Schema::table('reels', function (Blueprint $table) {
                $table->unsignedBigInteger('total_shares')->default(0)->after('total_store_visits');
            });
        }

        if (Schema::hasTable('urban_goodz_reel_comments') && Schema::hasTable('reels')) {
            $fkExists = ! empty(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'urban_goodz_reel_comments'
                   AND COLUMN_NAME = 'reel_id'
                   AND REFERENCED_TABLE_NAME = 'reels'"
            ));

            if (! $fkExists) {
                Schema::table('urban_goodz_reel_comments', function (Blueprint $table) {
                    $table->foreign('reel_id', 'ug_reel_comments_reel_fk')
                        ->references('id')->on('reels')
                        ->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Defensive: never drop a constraint/nullability relaxation that
        // other migrations or data may now depend on.
    }
};
