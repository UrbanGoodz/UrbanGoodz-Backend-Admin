<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzStylistRequestInvite extends Model
{
    protected $table = 'urban_goodz_stylist_request_invites';

    protected $guarded = ['id'];

    protected $casts = ['responded_at' => 'datetime'];
}
