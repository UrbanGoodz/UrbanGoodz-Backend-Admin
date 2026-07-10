<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModuleAutomationSetting extends Model
{
    protected $table = 'ai_module_automation_settings';

    protected $fillable = [
        'module',
        'enabled',
        'automation_mode',
        'min_confidence_score',
        'max_auto_action_amount',
        'allowed_zones',
        'allowed_categories',
        'max_risk_level',
        'escalation_rules',
        'approval_required_rules',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'min_confidence_score' => 'decimal:2',
        'max_auto_action_amount' => 'decimal:2',
        'allowed_zones' => 'json',
        'allowed_categories' => 'json',
        'escalation_rules' => 'json',
        'approval_required_rules' => 'json',
    ];

    public function scopeEnabledForModule($query, $module)
    {
        return $query->where('module', $module)->where('enabled', true);
    }
}
