<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MerchantProspect extends Model
{
    public const STATUSES = [
        'new', 'researching', 'qualified', 'contacted', 'engaged',
        'applied', 'converted', 'disqualified', 'opted_out',
    ];

    public const CAMPAIGN_STATUSES = [
        'none', 'pending_approval', 'active', 'paused', 'completed', 'stopped',
    ];

    public const REPLY_CLASSIFICATIONS = [
        'interested', 'wants_info', 'wants_meeting', 'ready_to_apply',
        'not_now', 'wrong_contact', 'remove_me', 'complaint',
        'auto_reply', 'delivery_failure', 'unclear', 'human_review',
    ];

    protected $fillable = [
        'business_name', 'business_name_normalized', 'category', 'address',
        'city', 'zone', 'website', 'domain', 'public_email', 'public_phone',
        'contact_name', 'data_source', 'source_url', 'verified_at',
        'confidence_score', 'prospect_score', 'prospect_status',
        'order_anywhere_request_count', 'unique_customer_count',
        'requested_categories', 'first_demand_date', 'latest_demand_date',
        'estimated_demand_value', 'campaign_status', 'last_contacted_at',
        'next_followup_at', 'reply_status', 'opt_out', 'do_not_contact',
        'vendor_application_id', 'converted_vendor_id',
        'first_completed_order_id', 'attributed_revenue',
        'ai_agent_id', 'metadata',
    ];

    protected $casts = [
        'requested_categories' => 'array',
        'metadata' => 'array',
        'opt_out' => 'boolean',
        'do_not_contact' => 'boolean',
        'verified_at' => 'datetime',
        'first_demand_date' => 'date',
        'latest_demand_date' => 'date',
        'last_contacted_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'confidence_score' => 'decimal:4',
        'prospect_score' => 'decimal:2',
        'estimated_demand_value' => 'decimal:2',
        'attributed_revenue' => 'decimal:2',
    ];

    // ─── Relationships ───────────────────────────────────────────────────

    public function orderAnywhereRequests()
    {
        return $this->belongsToMany(
            OrderAnywhereRequest::class,
            'merchant_prospect_order_anywhere'
        )->withTimestamps();
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function outreachMessages()
    {
        return $this->hasMany(AiOutreachMessage::class);
    }

    public function convertedVendor()
    {
        return $this->belongsTo(Vendor::class, 'converted_vendor_id');
    }

    // ─── Business Normalization ──────────────────────────────────────────

    public static function normalizeName(string $name): string
    {
        $normalized = Str::lower(trim($name));
        // Remove common suffixes
        $normalized = preg_replace('/\b(inc|llc|ltd|corp|co|company|the|of)\b/i', '', $normalized);
        // Remove non-alphanumeric except spaces
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        return $normalized;
    }

    public static function extractDomain(?string $url): ?string
    {
        if (!$url) return null;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            // Maybe just a domain was provided
            $host = parse_url('https://' . ltrim($url, '/'), PHP_URL_HOST);
        }
        if (!$host) return null;
        return Str::lower(preg_replace('/^www\./', '', $host));
    }

    // ─── Contact Eligibility ─────────────────────────────────────────────

    public function isContactable(): bool
    {
        return !$this->opt_out
            && !$this->do_not_contact
            && !in_array($this->prospect_status, ['opted_out', 'disqualified', 'converted'])
            && $this->campaign_status !== 'stopped';
    }

    public function hasReachedMaxAttempts(int $maxAttempts): bool
    {
        return $this->outreachMessages()
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'delivered'])
            ->count() >= $maxAttempts;
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeContactable($query)
    {
        return $query->where('opt_out', false)
            ->where('do_not_contact', false)
            ->whereNotIn('prospect_status', ['opted_out', 'disqualified', 'converted']);
    }

    public function scopeQualified($query)
    {
        return $query->whereIn('prospect_status', ['qualified', 'new', 'researching']);
    }
}
