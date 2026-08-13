<?php

namespace App\Models;

use Modules\ReelsModule\Entities\Reel;

/**
 * Creator Space alias for the shared `reels` table.
 *
 * Product decision (Lane 1 certification, 2026-08-12): Urban Goodz Creator
 * Space is a social-first content platform ("Creator -> Reel -> Social
 * Engagement"), not a traditional storefront - a creator does not need a
 * store or product to publish a Reel.
 *
 * The `reels` table (owned by Modules\ReelsModule\Entities\Reel) is already
 * the single source of truth for reels, shared by vendor-authored commerce
 * reels (store_id set) and creator-space social reels (creator_profile_id
 * set, store_id optional/null - see the accompanying migration
 * 2026_08_12_210000_support_creator_space_social_reels.php, which makes
 * store_id nullable for exactly this reason). It already carries
 * creator_profile_id/publication_status/moderation_status/published_at
 * (2026_07_12_120000_complete_creator_reel_commerce.php) and already has
 * creatorProfile()/commerceTags() relations wired up.
 *
 * App\Http\Controllers\Api\V1\CreatorSpaceController and
 * App\Http\Controllers\Api\V1\ReelSocialController (and
 * App\Models\UrbanGoodzCreatorProfile::reels()/reelComments()) reference
 * App\Models\UrbanGoodzReel by name, so this class exists as the
 * Creator-Space-facing name for that same table/model rather than a
 * duplicate table+model pair. It inherits all of Reel's casts, scopes
 * (active(), moduleWise()), relations, and media/translation handling.
 */
class UrbanGoodzReel extends Reel
{
    protected $table = 'reels';

    /**
     * Creator Space uses its own social comment table
     * (urban_goodz_reel_comments / App\Models\UrbanGoodzReelComment) rather
     * than the vendor/customer-commerce comment table
     * (creator_reel_comments / Modules\ReelsModule\Entities\ReelComment)
     * that the parent Reel::comments() points at. Overridden here so
     * ReelSocialController's read (comments()) and write (postComment())
     * paths hit the same table instead of silently diverging.
     */
    public function comments()
    {
        return $this->hasMany(UrbanGoodzReelComment::class, 'reel_id');
    }
}
