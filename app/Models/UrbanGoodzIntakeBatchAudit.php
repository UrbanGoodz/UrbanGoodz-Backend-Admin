<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzIntakeBatchAudit extends Model
{
    protected $table = 'urban_goodz_intake_batch_audits';

    protected $fillable = [
        'intake_batch_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'device_session_id',
        'notes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzIntakeBatch::class, 'intake_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function log(
        int $batchId,
        string $action,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $deviceId = null,
        ?string $notes = null
    ): self {
        return static::create([
            'intake_batch_id' => $batchId,
            'action' => $action,
            'user_id' => $userId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'device_session_id' => $deviceId,
            'notes' => $notes,
        ]);
    }
}
