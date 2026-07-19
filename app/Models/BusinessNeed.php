<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessNeed extends Model
{
    protected $fillable = [
        'type', 'title', 'description', 'evidence', 'priority', 'severity',
        'recommended_action', 'assigned_ai_agent_id', 'assigned_human_role',
        'due_date', 'completion_criteria', 'status', 'result',
    ];

    protected $casts = [
        'evidence' => 'array',
        'result' => 'array',
        'due_date' => 'datetime',
    ];

    public function assignedAgent()
    {
        return $this->belongsTo(AiAgent::class, 'assigned_ai_agent_id');
    }
}
