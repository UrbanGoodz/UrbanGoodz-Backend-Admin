<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAuditEvent extends Model
{
    protected $fillable = [
        'ai_agent_id', 'ai_task_id', 'ai_workforce_action_id',
        'event_type', 'policy_decision', 'request_metadata',
        'result_metadata', 'actor_type', 'actor_id', 'status', 'severity',
    ];

    protected $casts = [
        'request_metadata' => 'array',
        'result_metadata' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'ai_task_id');
    }

    public function action()
    {
        return $this->belongsTo(AiWorkforceAction::class, 'ai_workforce_action_id');
    }
}
