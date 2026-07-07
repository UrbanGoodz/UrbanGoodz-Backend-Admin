<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UrbanGoodzAIIntent extends Model
{
    protected $table = 'urban_goodz_ai_intents';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'keywords',
        'response_template',
        'capability_slug',
        'admin_section_key',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(UrbanGoodzAIConversation::class, 'detected_intent_id');
    }
}
