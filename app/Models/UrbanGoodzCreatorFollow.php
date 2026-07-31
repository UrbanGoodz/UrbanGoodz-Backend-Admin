<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorFollow extends Model
{
    protected $table = 'urban_goodz_creator_followers';
    
    protected $fillable = [
        'user_id',
        'creator_profile_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creatorProfile()
    {
        return $this->belongsTo(UrbanGoodzCreatorProfile::class, 'creator_profile_id');
    }
}
