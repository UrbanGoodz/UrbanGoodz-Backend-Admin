<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiActionLog extends Model
{
    protected $table = 'ai_action_logs';

    protected $fillable = [
        'recommendation_id',
        'action_taken',
        'module',
        'affected_user_type',
        'affected_user_id',
        'before_value',
        'after_value',
        'reason',
        'automation_mode',
        'approved_by',
        'rollback_available',
    ];

    protected $casts = [
        'rollback_available' => 'boolean',
    ];

    public function recommendation()
    {
        return $this->belongsTo(AiCopilotRecommendation::class, 'recommendation_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeRecent($query, $limit = 50)
    {
        return $query->latest('created_at')->limit($limit);
    }
}
