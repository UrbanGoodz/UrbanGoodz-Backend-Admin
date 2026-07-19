<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class UrbanGoodzNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getRecipientTypeAttribute(string $value): string
    {
        return $value;
    }

    public function scopeForRecipient(Builder $query, string $type, int $id): Builder
    {
        return $query->where('recipient_type', $type)->where('recipient_id', $id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): bool
    {
        return $this->update(['status' => 'read', 'read_at' => now()]);
    }
}
