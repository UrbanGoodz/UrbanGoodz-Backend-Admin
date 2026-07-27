<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class UrbanGoodzCompensationResult extends Model
{
    protected $table = 'urban_goodz_compensation_results';

    protected $fillable = [
        'rule_id', 'rule_key', 'rule_version',
        'subject_type', 'subject_id', 'driver_id',
        'context', 'breakdown', 'splits', 'explanation',
        'gross_cents', 'driver_cents', 'is_final', 'finalized_at',
    ];

    protected $casts = [
        'context' => 'array',
        'breakdown' => 'array',
        'splits' => 'array',
        'gross_cents' => 'integer',
        'driver_cents' => 'integer',
        'rule_version' => 'integer',
        'is_final' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A finalized result is the record of what a driver was actually paid for
        // completed work. Rule changes must never rewrite history, so the row is
        // sealed once finalized; a correction has to be a new, separate result.
        static::updating(function (self $result) {
            if ($result->getOriginal('is_final')) {
                throw new RuntimeException(
                    'Finalized compensation results are immutable; record a correcting result instead.'
                );
            }
        });

        static::deleting(function (self $result) {
            if ($result->is_final) {
                throw new RuntimeException('Finalized compensation results may not be deleted.');
            }
        });
    }
}
