<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRiskRule extends Model
{
    protected $table = 'ai_risk_rules';

    protected $fillable = [
        'rule_name',
        'trigger_type',
        'trigger_operator',
        'trigger_value',
        'risk_level',
        'requires_approval',
        'escalation_action',
        'enabled',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOfRiskLevel($query, $level)
    {
        return $query->where('risk_level', $level);
    }

    public function scopeByTriggerType($query, $type)
    {
        return $query->where('trigger_type', $type);
    }
}
