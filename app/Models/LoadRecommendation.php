<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadRecommendation extends Model
{
    use SoftDeletes;

    protected $table = 'load_recommendations';

    const STATUSES = ['pending', 'viewed', 'saved', 'hidden', 'interested', 'bid_submitted', 'assigned', 'dismissed', 'expired'];
    const CONFIDENCE_LEVELS = ['low', 'medium', 'high'];

    protected $fillable = [
        'external_load_id', 'delivery_man_id', 'generated_by', 'generated_by_type',
        'score', 'confidence_level', 'estimated_driver_net', 'net_per_total_mile',
        'deadhead_miles', 'equipment_match', 'certification_match', 'schedule_feasible',
        'broker_risk', 'reasons_recommended', 'reasons_penalized',
        'status', 'driver_notified', 'viewed_at', 'saved_at', 'hidden_at', 'expires_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'estimated_driver_net' => 'decimal:2',
        'net_per_total_mile' => 'decimal:4',
        'deadhead_miles' => 'decimal:2',
        'equipment_match' => 'boolean',
        'certification_match' => 'boolean',
        'schedule_feasible' => 'boolean',
        'reasons_recommended' => 'array',
        'reasons_penalized' => 'array',
        'driver_notified' => 'boolean',
        'viewed_at' => 'datetime',
        'saved_at' => 'datetime',
        'hidden_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function externalLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'external_load_id'); }
    public function driver(): BelongsTo { return $this->belongsTo(DeliveryMan::class, 'delivery_man_id'); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }

    public function scopePending($query) { return $query->where('status', 'pending'); }
    public function scopeForDriver($query, int $driverId) { return $query->where('delivery_man_id', $driverId); }
    public function scopeActive($query) { return $query->whereIn('status', ['pending', 'viewed']); }
    public function scopeTopRanked($query, int $limit = 10) { return $query->orderByDesc('score')->limit($limit); }

    public function getConfidenceLabelAttribute(): string
    {
        return match($this->confidence_level) {
            'high' => 'High Confidence',
            'medium' => 'Medium Confidence',
            default => 'Low Confidence',
        };
    }

    public function isActionable(): bool
    {
        return in_array($this->status, ['pending', 'viewed']) && $this->expires_at && $this->expires_at->isFuture();
    }
}
