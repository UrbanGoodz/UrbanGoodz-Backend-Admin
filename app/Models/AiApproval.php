<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiApproval extends Model
{
    protected $fillable = [
        'ai_workforce_action_id', 'requested_approver_id',
        'approval_reason', 'risk_level', 'decision',
        'approver_id', 'decision_notes', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function action()
    {
        return $this->belongsTo(AiWorkforceAction::class, 'ai_workforce_action_id');
    }

    public function requestedApprover()
    {
        return $this->belongsTo(Admin::class, 'requested_approver_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approver_id');
    }

    public function approve(int $approverId, ?string $notes = null): void
    {
        $this->update([
            'decision' => 'approved',
            'approver_id' => $approverId,
            'decision_notes' => $notes,
            'decided_at' => now(),
        ]);

        $this->action->update(['approval_status' => 'approved']);
    }

    public function reject(int $approverId, ?string $notes = null): void
    {
        $this->update([
            'decision' => 'rejected',
            'approver_id' => $approverId,
            'decision_notes' => $notes,
            'decided_at' => now(),
        ]);

        $this->action->update(['approval_status' => 'rejected', 'status' => 'cancelled']);
    }

    public function scopePending($query)
    {
        return $query->where('decision', 'pending');
    }
}
