<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCompensationRuleAudit extends Model
{
    protected $table = 'urban_goodz_compensation_rule_audits';

    protected $fillable = [
        'rule_id', 'rule_key', 'version', 'event',
        'old_values', 'new_values', 'actor_id', 'actor_type', 'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'version' => 'integer',
    ];

    public function rule()
    {
        return $this->belongsTo(UrbanGoodzCompensationRule::class, 'rule_id');
    }
}
