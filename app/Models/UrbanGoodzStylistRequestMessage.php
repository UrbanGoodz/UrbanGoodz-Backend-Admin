<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzStylistRequestMessage extends Model
{
    protected $table = 'urban_goodz_stylist_request_messages';

    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];
}
