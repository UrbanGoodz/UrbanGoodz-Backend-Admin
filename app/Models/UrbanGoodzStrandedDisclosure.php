<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Every release of a user's identity data to anybody outside the platform.
 *
 * This is what turns the privacy promise into something checkable. If the
 * product tells people their information is shared only on a lawful request,
 * there must be a durable record of each occasion, the authority that asked,
 * what was handed over, and whether the subject was told.
 */
class UrbanGoodzStrandedDisclosure extends Model
{
    protected $table = 'urban_goodz_stranded_disclosure_log';

    public const BASIS_LAW_ENFORCEMENT = 'law_enforcement';
    public const BASIS_SUBPOENA = 'subpoena';
    public const BASIS_COURT_ORDER = 'court_order';
    public const BASIS_SAFETY_INCIDENT = 'safety_incident';
    public const BASIS_USER_REQUEST = 'user_request';

    protected $fillable = [
        'subject_user_id', 'request_id', 'basis',
        'requesting_authority', 'reference_number', 'fields_disclosed', 'notes',
        'authorised_by', 'disclosed_at',
        'subject_notified', 'notification_withheld_reason',
    ];

    protected $casts = [
        'disclosed_at' => 'datetime',
        'subject_notified' => 'boolean',
    ];
}
