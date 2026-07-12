<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzCommunityPost extends Model
{
    protected $table = 'urban_goodz_community_posts';

    protected $fillable = [
        'title', 'body', 'type', 'author_name', 'author_email',
        'is_published', 'published_at',
    ];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime'];

    public function comments(): HasMany
    {
        return $this->hasMany(UrbanGoodzCommunityComment::class, 'post_id');
    }
}
