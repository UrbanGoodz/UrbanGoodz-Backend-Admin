<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCompanionContext extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'zone_id', 'current_page',
        'conversation_context', 'active_workflow', 'allowed_actions',
        'dismissal_history', 'snooze_until', 'promotion_preferences',
    ];

    protected $casts = [
        'conversation_context' => 'array',
        'allowed_actions' => 'array',
        'dismissal_history' => 'array',
        'promotion_preferences' => 'array',
        'snooze_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
