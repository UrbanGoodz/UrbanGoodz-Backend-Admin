<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzStrandedNearbyPresence extends Model
{
    protected $table = 'urban_goodz_stranded_nearby_presence';

    protected $fillable = ['user_id', 'latitude', 'longitude', 'reported_at', 'expires_at'];

    protected $casts = ['reported_at' => 'datetime', 'expires_at' => 'datetime'];
}
