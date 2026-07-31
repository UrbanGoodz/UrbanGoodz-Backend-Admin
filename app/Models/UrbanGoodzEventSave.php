<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEventSave extends Model
{
    protected $table = 'urban_goodz_event_saves';
    
    protected $fillable = [
        'user_id',
        'event_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(UrbanGoodzEvent::class);
    }
}
