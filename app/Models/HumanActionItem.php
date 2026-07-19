<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HumanActionItem extends Model
{
    protected $fillable = [
        'title', 'description', 'source_agent_id', 'source_task_id', 'source_action_id',
        'assigned_user_id', 'assigned_role', 'business_area', 'priority', 'due_date',
        'status', 'required_action', 'recommended_next_step', 'supporting_evidence',
        'confidence', 'financial_impact', 'risk_level', 'escalation_path', 'completion_notes',
        'completed_by', 'completion_time', 'follow_up_date',
    ];

    protected $casts = [
        'supporting_evidence' => 'array',
        'due_date' => 'datetime',
        'completion_time' => 'datetime',
        'follow_up_date' => 'datetime',
        'confidence' => 'decimal:4',
        'financial_impact' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'source_agent_id');
    }

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'source_task_id');
    }

    public function action()
    {
        return $this->belongsTo(AiWorkforceAction::class, 'source_action_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(Admin::class, 'assigned_user_id');
    }

    public function completedByUser()
    {
        return $this->belongsTo(Admin::class, 'completed_by');
    }
}
