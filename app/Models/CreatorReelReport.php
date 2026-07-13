<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorReelReport extends Model
{
    protected $fillable = [
        'reel_id', 'user_id', 'guest_id', 'reason', 'details', 'status',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function reel()
    {
        return $this->belongsTo(\Modules\ReelsModule\Entities\Reel::class, 'reel_id');
    }
}
