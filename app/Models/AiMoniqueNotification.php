<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiMoniqueNotification extends Model
{
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTIONED = 'actioned';

    protected $fillable = [
        'account_type',
        'account_id',
        'store_id',
        'category',
        'priority',
        'title',
        'message',
        'actions',
        'is_actionable',
        'can_auto_resolve',
        'auto_resolved',
        'status',
        'resolution_summary',
        'delivered_channels',
        'read_at',
    ];

    protected $casts = [
        'actions' => 'array',
        'delivered_channels' => 'array',
        'is_actionable' => 'boolean',
        'can_auto_resolve' => 'boolean',
        'auto_resolved' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function scopeForAccount(Builder $query, string $type, int $id): Builder
    {
        return $query->where('account_type', $type)->where('account_id', $id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }

    public function markAsDismissed(): bool
    {
        return $this->update(['status' => self::STATUS_DISMISSED]);
    }

    public function markAsResolved(string $summary): bool
    {
        return $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolution_summary' => $summary,
            'auto_resolved' => true,
        ]);
    }
}
