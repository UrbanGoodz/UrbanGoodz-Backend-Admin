<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzRouteClusteringAudit extends Model
{
    use SoftDeletes;

    const STATUSES = ['pending_review', 'reviewed', 'applied', 'discarded'];

    protected $fillable = [
        'business_client_id', 'manifest_id', 'planning_uuid',
        'clustering_params', 'original_plan', 'optimized_plan',
        'unrouteable_packages',
        'algorithm', 'distance_mode', 'status', 'admin_notes', 'metrics',
    ];

    protected $casts = [
        'clustering_params' => 'array',
        'original_plan' => 'array',
        'optimized_plan' => 'array',
        'unrouteable_packages' => 'array',
        'metrics' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function manifest()
    {
        return $this->belongsTo(UrbanGoodzManifest::class, 'manifest_id');
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('business_client_id', $clientId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('id');
    }

    public function isReviewable(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isAppliable(): bool
    {
        return in_array($this->status, ['pending_review', 'reviewed']);
    }
}
