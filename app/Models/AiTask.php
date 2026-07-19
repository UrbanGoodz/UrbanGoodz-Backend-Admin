<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    public const STATUSES = ['pending', 'scheduled', 'running', 'completed', 'failed', 'cancelled', 'awaiting_approval'];

    public const TYPES = [
        'research', 'score', 'draft_outreach', 'send_outreach',
        'classify_reply', 'create_followup', 'daily_brief',
        'verify_contact', 'create_prospect',
    ];

    protected $fillable = [
        'ai_agent_id', 'task_type', 'source_type', 'source_id',
        'objective', 'priority', 'status', 'scheduled_at', 'started_at',
        'completed_at', 'retry_count', 'idempotency_key',
        'input', 'output', 'confidence', 'failure_reason',
        'escalation_reason', 'assigned_approver_id',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'confidence' => 'decimal:4',
        'retry_count' => 'integer',
    ];

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function actions()
    {
        return $this->hasMany(AiWorkforceAction::class);
    }

    public function outreachMessages()
    {
        return $this->hasMany(AiOutreachMessage::class);
    }

    public function assignedApprover()
    {
        return $this->belongsTo(Admin::class, 'assigned_approver_id');
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markCompleted(array $output = [], ?float $confidence = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'output' => $output,
            'confidence' => $confidence,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAwaitingApproval(string $reason, ?int $approverId = null): void
    {
        $this->update([
            'status' => 'awaiting_approval',
            'escalation_reason' => $reason,
            'assigned_approver_id' => $approverId,
        ]);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduledNow($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->where('status', 'awaiting_approval');
    }
}
