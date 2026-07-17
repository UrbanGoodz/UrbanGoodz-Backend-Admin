<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadImport extends Model
{
    protected $table = 'load_imports';

    const METHODS = ['single_form', 'csv', 'share_to_urban_goodz', 'external_url'];
    const STATUSES = ['pending', 'processing', 'completed', 'failed', 'partially_completed'];

    protected $fillable = [
        'source_id', 'imported_by', 'imported_by_type', 'import_method',
        'import_reference', 'original_filename',
        'total_rows', 'successful_rows', 'failed_rows', 'duplicate_rows',
        'status', 'error_message', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_rows === 0) return 0;
        return round(($this->successful_rows / $this->total_rows) * 100, 1);
    }
}
