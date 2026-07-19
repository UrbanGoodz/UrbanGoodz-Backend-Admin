<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzBatchParticipant extends Model
{
    const ROLES = ['intake_worker', 'intake_supervisor', 'dispatcher', 'admin'];

    protected $fillable = [
        'intake_batch_id', 'user_id', 'role',
        'device_session_id', 'source_portal',
        'joined_at', 'last_active_at',
        'packages_created', 'packages_edited',
        'validation_actions', 'approval_actions',
        'is_active',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_active_at' => 'datetime',
        'packages_created' => 'integer',
        'packages_edited' => 'integer',
        'validation_actions' => 'integer',
        'approval_actions' => 'integer',
        'is_active' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzIntakeBatch::class, 'intake_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function touchActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    public function incrementCreated(): void
    {
        $this->increment('packages_created');
        $this->touchActive();
    }

    public function incrementEdited(): void
    {
        $this->increment('packages_edited');
        $this->touchActive();
    }

    public function incrementValidation(): void
    {
        $this->increment('validation_actions');
        $this->touchActive();
    }

    public function incrementApproval(): void
    {
        $this->increment('approval_actions');
        $this->touchActive();
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
