<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzCommunityComment extends Model
{
    protected $table = 'urban_goodz_community_comments';

    protected $fillable = ['post_id', 'author_name', 'body', 'is_approved'];

    protected $casts = ['is_approved' => 'boolean'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzCommunityPost::class, 'post_id');
    }
}
