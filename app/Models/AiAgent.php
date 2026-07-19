<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    // Autonomy levels
    public const LEVEL_OBSERVE = 1;
    public const LEVEL_RECOMMEND = 2;
    public const LEVEL_EXECUTE = 3;
    public const LEVEL_ESCALATE = 4;

    public const AUTONOMY_LABELS = [
        self::LEVEL_OBSERVE => 'Observe',
        self::LEVEL_RECOMMEND => 'Recommend',
        self::LEVEL_EXECUTE => 'Execute Within Policy',
        self::LEVEL_ESCALATE => 'Escalate',
    ];

    public const STATUSES = ['active', 'inactive', 'paused', 'error'];

    protected $fillable = [
        'name', 'slug', 'role', 'description', 'status', 'autonomy_level',
        'provider_config', 'allowed_tools', 'allowed_actions', 'prohibited_actions',
        'confidence_threshold', 'daily_task_limit', 'daily_message_limit', 'daily_token_limit',
        'assigned_market', 'assigned_categories', 'active_hours',
        'escalation_recipient_id', 'last_run_at', 'kill_switch', 'metadata',
    ];

    protected $casts = [
        'provider_config' => 'array',
        'allowed_tools' => 'array',
        'allowed_actions' => 'array',
        'prohibited_actions' => 'array',
        'assigned_categories' => 'array',
        'active_hours' => 'array',
        'metadata' => 'array',
        'kill_switch' => 'boolean',
        'confidence_threshold' => 'decimal:4',
        'last_run_at' => 'datetime',
        'autonomy_level' => 'integer',
        'daily_task_limit' => 'integer',
        'daily_message_limit' => 'integer',
        'daily_token_limit' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────────────

    public function tasks()
    {
        return $this->hasMany(AiTask::class);
    }

    public function actions()
    {
        return $this->hasMany(AiWorkforceAction::class);
    }

    public function prospects()
    {
        return $this->hasMany(MerchantProspect::class);
    }

    public function escalationRecipient()
    {
        return $this->belongsTo(Admin::class, 'escalation_recipient_id');
    }

    // ─── Autonomy Helpers ────────────────────────────────────────────────

    public function isKilled(): bool
    {
        return $this->kill_switch;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->kill_switch;
    }

    public function canExecute(string $actionType): bool
    {
        if ($this->isKilled()) return false;
        if (!$this->isActive()) return false;

        // Check prohibited actions
        if (is_array($this->prohibited_actions) && in_array($actionType, $this->prohibited_actions)) {
            return false;
        }

        // Check allowed actions
        if (is_array($this->allowed_actions) && !empty($this->allowed_actions)) {
            return in_array($actionType, $this->allowed_actions);
        }

        return true;
    }

    public function getAutonomyForAction(string $actionType): int
    {
        // Action-specific autonomy overrides from metadata
        $overrides = $this->metadata['autonomy_overrides'] ?? [];
        return $overrides[$actionType] ?? $this->autonomy_level;
    }

    public function requiresApproval(string $actionType): bool
    {
        $level = $this->getAutonomyForAction($actionType);
        return in_array($level, [self::LEVEL_RECOMMEND, self::LEVEL_ESCALATE]);
    }

    // ─── Daily Limit Checks ──────────────────────────────────────────────

    public function tasksToday(): int
    {
        return $this->tasks()->whereDate('created_at', today())->count();
    }

    public function messagesToday(): int
    {
        return AiOutreachMessage::where('ai_agent_id', $this->id)
            ->whereDate('created_at', today())
            ->whereIn('status', ['sent', 'delivered', 'queued'])
            ->count();
    }

    public function tokensToday(): int
    {
        return (int) $this->actions()
            ->whereDate('created_at', today())
            ->sum('tokens_used');
    }

    public function hasReachedTaskLimit(): bool
    {
        return $this->tasksToday() >= $this->daily_task_limit;
    }

    public function hasReachedMessageLimit(): bool
    {
        return $this->messagesToday() >= $this->daily_message_limit;
    }

    public function hasReachedTokenLimit(): bool
    {
        return $this->tokensToday() >= $this->daily_token_limit;
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('kill_switch', false);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
