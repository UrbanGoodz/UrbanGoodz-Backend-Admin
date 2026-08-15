<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side's rating of the other after a Stranded assist. Each side rates
 * once per request -- the unique index on (request_id, rater_role) is what
 * actually enforces that, this class just reflects the shape.
 */
class UrbanGoodzStrandedRating extends Model
{
    protected $table = 'urban_goodz_stranded_ratings';

    protected $fillable = [
        'request_id', 'rater_user_id', 'rater_role', 'ratee_user_id', 'stars', 'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedRequest::class, 'request_id');
    }
}
