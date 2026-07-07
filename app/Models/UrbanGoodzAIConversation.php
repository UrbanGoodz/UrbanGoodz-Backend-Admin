<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzAIConversation extends Model
{
    protected $table = 'urban_goodz_ai_conversations';

    protected $fillable = [
        'customer_id',
        'query_text',
        'detected_intent_id',
        'confidence_score',
        'response_text',
        'admin_notes',
        'status',
        'source',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'detected_intent_id' => 'integer',
        'confidence_score' => 'decimal:2',
    ];

    public function detectedIntent(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzAIIntent::class, 'detected_intent_id');
    }
}
