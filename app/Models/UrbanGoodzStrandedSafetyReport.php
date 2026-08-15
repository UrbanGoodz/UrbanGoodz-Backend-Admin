<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A safety concern raised by either side of a Stranded assist.
 *
 * Filing one does not itself change the request's state -- callers decide
 * whether to also cancel -- but it always creates a durable, timestamped
 * record, because a safety complaint that only lives in a support ticket
 * cannot be audited later.
 */
class UrbanGoodzStrandedSafetyReport extends Model
{
    protected $table = 'urban_goodz_stranded_safety_reports';

    public const REASON_NOT_SAFE = 'not_safe';
    public const REASON_NOT_WHO_CLAIMED = 'not_who_claimed';
    public const REASON_LOCATION_INCORRECT = 'location_incorrect';
    public const REASON_SUSPICIOUS_BEHAVIOR = 'suspicious_behavior';
    public const REASON_HARASSMENT = 'harassment';
    public const REASON_THREATENING_BEHAVIOR = 'threatening_behavior';
    public const REASON_FRAUD = 'fraud';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'request_id', 'reporter_user_id', 'reporter_role',
        'reason_code', 'details', 'resolved_at', 'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedRequest::class, 'request_id');
    }
}
