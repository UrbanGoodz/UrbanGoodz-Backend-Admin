<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzReelComment extends Model
{
    protected $table = 'urban_goodz_reel_comments';
    
    protected $fillable = [
        'reel_id',
        'user_id',
        'parent_id',
        'content',
        'status',
    ];

    public function reel()
    {
        return $this->belongsTo(UrbanGoodzReel::class, 'reel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
