<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatcherSavedSearch extends Model
{
    protected $table = 'dispatcher_saved_searches';

    protected $fillable = [
        'business_client_user_id', 'dispatch_company_id', 'name',
        'criteria', 'source_keys', 'auto_alert', 'alert_threshold_score',
        'last_run_result_count', 'last_run_at',
    ];

    protected $casts = [
        'criteria' => 'array',
        'source_keys' => 'array',
        'auto_alert' => 'boolean',
        'alert_threshold_score' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'business_client_user_id'); }
}
