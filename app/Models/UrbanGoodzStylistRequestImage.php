<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inspiration imagery supplied by the Shopper. Body photos are never stored
 * here — they stay in fashion_fit_photos behind an explicit grant.
 */
class UrbanGoodzStylistRequestImage extends Model
{
    protected $table = 'urban_goodz_stylist_request_images';

    protected $guarded = ['id'];
}
