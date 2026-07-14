<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadSourceSearch extends Model
{
    protected $table = 'load_source_searches';

    protected $fillable = [
        'source_id', 'searched_by', 'searched_by_type', 'search_scope',
        'criteria', 'result_count', 'duration_ms', 'completed', 'error_message',
    ];

    protected $casts = [
        'criteria' => 'array',
        'duration_ms' => 'decimal:2',
        'completed' => 'boolean',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }
    public function results(): HasMany { return $this->hasMany(LoadSourceSearchResult::class, 'search_id'); }
}
