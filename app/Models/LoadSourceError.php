<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadSourceError extends Model
{
    protected $table = 'load_source_errors';

    protected $fillable = [
        'source_id', 'sync_run_id', 'error_code', 'error_message',
        'context', 'resolved', 'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }
    public function syncRun(): BelongsTo { return $this->belongsTo(LoadSourceSyncRun::class, 'sync_run_id'); }
}
