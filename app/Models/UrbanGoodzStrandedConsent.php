<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record of exactly what somebody agreed to, and when.
 *
 * The version is stored rather than assumed. Consent to v1 of the safety
 * terms is not consent to v2, so re-publishing a document requires re-asking
 * -- and this table is what proves which one a given user actually saw.
 */
class UrbanGoodzStrandedConsent extends Model
{
    protected $table = 'urban_goodz_stranded_consents';

    protected $fillable = [
        'user_id', 'role', 'document', 'version',
        'accepted_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];
}
