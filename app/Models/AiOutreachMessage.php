<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiOutreachMessage extends Model
{
    protected $fillable = [
        'merchant_prospect_id', 'ai_agent_id', 'ai_task_id',
        'ai_outreach_template_id', 'direction', 'channel',
        'to_email', 'from_email', 'subject', 'body',
        'personalization_context', 'status', 'idempotency_key',
        'sequence_step', 'scheduled_at', 'sent_at', 'delivered_at',
        'opened_at', 'bounced_at', 'bounce_type',
        'reply_classification', 'failure_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'bounced_at' => 'datetime',
        'sequence_step' => 'integer',
    ];

    public function prospect()
    {
        return $this->belongsTo(MerchantProspect::class, 'merchant_prospect_id');
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function task()
    {
        return $this->belongsTo(AiTask::class, 'ai_task_id');
    }

    public function template()
    {
        return $this->belongsTo(AiOutreachTemplate::class, 'ai_outreach_template_id');
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'opened', 'clicked']);
    }

    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered', 'opened', 'clicked']);
    }
}
