<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCopilotRecommendation extends Model
{
    protected $fillable = [
        'recommendation_type',
        'recommendation_subtype',
        'relatable_type',
        'relatable_id',
        'order_id',
        'package_id',
        'route_id',
        'request_id',
        'suggested_action',
        'reason',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
        'metadata',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'metadata' => 'json',
    ];

    const RECOMMENDATION_TYPES = [
        'dispatch_suggestion',
        'stuck_order',
        'order_anywhere_triage',
        'package_monitoring',
        'age_verification_alert',
    ];

    const STATUSES = [
        'pending', 'accepted', 'dismissed', 'expired',
    ];

    public function relatable()
    {
        return $this->morphTo();
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('recommendation_type', $type);
    }
}
