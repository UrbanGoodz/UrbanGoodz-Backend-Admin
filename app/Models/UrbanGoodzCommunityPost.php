<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzCommunityPost extends Model
{
    protected $table = 'urban_goodz_community_posts';

    protected $fillable = [
        'title', 'body', 'type', 'author_name', 'author_email',
        'is_published', 'published_at', 'zone_id', 'is_nationwide', 'is_worldwide', 'user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'is_nationwide' => 'boolean',
        'is_worldwide' => 'boolean',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(UrbanGoodzCommunityComment::class, 'post_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForGroup($query, ?int $zoneId, bool $nationwide, bool $worldwide)
    {
        if ($worldwide) {
            return $query->where('is_worldwide', true);
        }
        if ($nationwide) {
            return $query->where('is_nationwide', true);
        }
        return $query->where('zone_id', $zoneId);
    }
}
