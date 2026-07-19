<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiWorkforceAction extends Model
{
    protected $fillable = [
        'ai_agent_id', 'ai_task_id', 'action_type',
        'target_type', 'target_id', 'request_payload', 'result',
        'status', 'approval_status', 'provider', 'model',
        'tokens_used', 'estimated_cost', 'actor_type', 'actor_id',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'result' => 'array',
        'tokens_used' => 'integer',
        'estimated_cost' => 'decimal:6',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'ai_task_id');
    }

    public function approval()
    {
        return $this->hasOne(AiApproval::class);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('ai_agent_id', $agentId);
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->latest()->limit($limit);
    }
}
