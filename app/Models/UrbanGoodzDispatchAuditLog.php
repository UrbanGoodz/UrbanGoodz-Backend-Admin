<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzDispatchAuditLog extends Model
{
    protected $table = 'urban_goodz_dispatch_audit_logs';

    protected $fillable = [
        'dispatch_company_id', 'dispatcher_id', 'load_id',
        'event_type', 'old_value', 'new_value', 'context',
        'actor_id', 'actor_type', 'notes', 'ip_address',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'dispatch_company_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'dispatcher_id');
    }

    public function loadBoardLoad()
    {
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('dispatch_company_id', $companyId);
    }

    public function scopeForLoad($query, int $loadId)
    {
        return $query->where('load_id', $loadId);
    }

    public static function log(
        int $companyId,
        ?int $dispatcherId,
        ?int $loadId,
        string $eventType,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $context = null,
        ?string $notes = null
    ): self {
        return static::create([
            'dispatch_company_id' => $companyId,
            'dispatcher_id' => $dispatcherId,
            'load_id' => $loadId,
            'event_type' => $eventType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'context' => $context,
            'notes' => $notes,
            'ip_address' => request()->ip(),
        ]);
    }
}
