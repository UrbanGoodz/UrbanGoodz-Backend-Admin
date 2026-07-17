<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzLoadBoardAuditLog extends Model
{
    protected $table = 'urban_goodz_load_board_audit_logs';

    protected $fillable = [
        'load_id', 'event_type', 'old_value', 'new_value',
        'context', 'actor_id', 'actor_type', 'notes',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function loadBoardLoad(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function actor()
    {
        if ($this->actor_type === 'admin') {
            return $this->belongsTo(Admin::class, 'actor_id');
        }
        if ($this->actor_type === 'dispatcher') {
            return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'actor_id');
        }
        return null;
    }
}
