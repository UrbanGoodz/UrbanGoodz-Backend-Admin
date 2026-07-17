<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadSourceSearchResult extends Model
{
    protected $table = 'load_source_search_results';

    protected $fillable = ['search_id', 'external_load_id'];

    public function search(): BelongsTo { return $this->belongsTo(LoadSourceSearch::class, 'search_id'); }
    public function externalLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'external_load_id'); }
}
