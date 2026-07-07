<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorApplication extends Model
{
    protected $table = 'urban_goodz_creator_applications';

    protected $fillable = [
        'creator_name', 'email', 'phone', 'platform', 'username',
        'follower_count', 'bio', 'status', 'admin_notes',
    ];

    protected $casts = ['follower_count' => 'integer'];
}
