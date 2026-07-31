<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail for admin actions that are not attached to a single booking,
 * such as commission overrides and settlement batches.
 */
class UrbanGoodzServiceAdminAudit extends Model
{
    protected $table = 'urban_goodz_service_admin_audits';

    protected $fillable = ['subject_type', 'subject_id', 'action', 'actor_id', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public static function record(string $subjectType, ?int $subjectId, string $action, ?int $actorId, array $metadata = []): self
    {
        return self::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);
    }
}
