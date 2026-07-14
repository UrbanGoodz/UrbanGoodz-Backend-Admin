<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadSourceSyncRun extends Model
{
    protected $table = 'load_source_sync_runs';

    const STATUSES = ['running', 'completed', 'failed', 'partial'];

    protected $fillable = [
        'source_id', 'status', 'search_criteria', 'loads_found', 'loads_new',
        'loads_updated', 'loads_duplicate', 'loads_expired', 'duration_ms',
        'error_message', 'metadata',
    ];

    protected $casts = [
        'search_criteria' => 'array',
        'metadata' => 'array',
        'duration_ms' => 'decimal:2',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }
    public function errors(): HasMany { return $this->hasMany(LoadSourceError::class, 'sync_run_id'); }
}
