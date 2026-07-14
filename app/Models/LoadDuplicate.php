<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadDuplicate extends Model
{
    protected $table = 'load_duplicates';

    protected $fillable = ['fingerprint', 'canonical_load_id', 'duplicate_load_id', 'similarity_score'];

    protected $casts = ['similarity_score' => 'decimal:4'];

    public function canonicalLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'canonical_load_id'); }
    public function duplicateLoad(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'duplicate_load_id'); }
}
