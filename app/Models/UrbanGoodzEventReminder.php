<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEventReminder extends Model
{
    protected $table = 'urban_goodz_event_reminders';
    
    protected $fillable = [
        'user_id',
        'event_id',
        'remind_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
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
