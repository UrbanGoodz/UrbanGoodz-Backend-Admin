<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalLoad extends Model
{
    use SoftDeletes;

    protected $table = 'external_loads';

    const STATUSES = ['sourced', 'pending_review', 'approved', 'available', 'bid_submitted', 'booked', 'expired', 'cancelled'];
    const COMPLIANCE_STATUSES = ['internal', 'business_client', 'authorized_partner', 'user_imported', 'email_sourced', 'external_link'];
    const BROKER_CREDIT_STATUSES = ['excellent', 'good', 'fair', 'poor', 'unknown'];

    protected $fillable = [
        'source_id', 'external_id', 'fingerprint', 'source_url',
        'broker_name', 'broker_contact', 'broker_reference', 'broker_rating', 'broker_credit_status',
        'origin_address', 'origin_city', 'origin_state', 'origin_zip', 'origin_latitude', 'origin_longitude',
        'destination_address', 'destination_city', 'destination_state', 'destination_zip', 'destination_latitude', 'destination_longitude',
        'pickup_start', 'pickup_end', 'delivery_start', 'delivery_end',
        'equipment_type', 'trailer_type', 'vehicle_requirements', 'certifications_required', 'commodity', 'weight',
        'distance_loaded', 'distance_deadhead',
        'gross_rate', 'rate_per_loaded_mile', 'estimated_fuel_cost', 'estimated_tolls',
        'estimated_platform_fee', 'estimated_driver_net', 'estimated_net_per_total_mile',
        'status', 'compliance_status', 'expires_at', 'is_duplicate', 'deduplicated_to_id',
        'raw_source_payload',
        'approved_by', 'approved_by_type', 'approved_at',
    ];

    protected $casts = [
        'certifications_required' => 'array',
        'raw_source_payload' => 'array',
        'origin_latitude' => 'decimal:7',
        'origin_longitude' => 'decimal:7',
        'destination_latitude' => 'decimal:7',
        'destination_longitude' => 'decimal:7',
        'weight' => 'decimal:2',
        'distance_loaded' => 'decimal:2',
        'distance_deadhead' => 'decimal:2',
        'gross_rate' => 'decimal:2',
        'rate_per_loaded_mile' => 'decimal:4',
        'estimated_fuel_cost' => 'decimal:2',
        'estimated_tolls' => 'decimal:2',
        'estimated_platform_fee' => 'decimal:2',
        'estimated_driver_net' => 'decimal:2',
        'estimated_net_per_total_mile' => 'decimal:4',
        'broker_rating' => 'decimal:2',
        'is_duplicate' => 'boolean',
        'pickup_start' => 'datetime',
        'pickup_end' => 'datetime',
        'delivery_start' => 'datetime',
        'delivery_end' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }
    public function recommendations(): HasMany { return $this->hasMany(LoadRecommendation::class, 'external_load_id'); }
    public function referrals(): HasMany { return $this->hasMany(LoadPartnerReferral::class, 'external_load_id'); }
    public function searchResults(): HasMany { return $this->hasMany(LoadSourceSearchResult::class, 'external_load_id'); }
    public function deduplicatedTo(): BelongsTo { return $this->belongsTo(ExternalLoad::class, 'deduplicated_to_id'); }
    public function duplicates(): HasMany { return $this->hasMany(LoadDuplicate::class, 'canonical_load_id'); }

    public function scopeAvailable($query) { return $query->where('status', 'available')->where('is_duplicate', false); }
    public function scopePendingReview($query) { return $query->where('status', 'pending_review'); }
    public function scopeForSource($query, int $sourceId) { return $query->where('source_id', $sourceId); }
    public function scopeNotDuplicate($query) { return $query->where('is_duplicate', false); }

    public function getOriginFullAttribute(): ?string
    {
        $parts = array_filter([$this->origin_city, $this->origin_state]);
        return $parts ? implode(', ', $parts) : null;
    }

    public function getDestinationFullAttribute(): ?string
    {
        $parts = array_filter([$this->destination_city, $this->destination_state]);
        return $parts ? implode(', ', $parts) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->status));
    }

    public function getComplianceLabelAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->compliance_status));
    }

    public static function generateFingerprint(array $data): string
    {
        $canonical = implode('|', [
            strtolower(trim($data['origin_city'] ?? '')),
            strtoupper(trim($data['origin_state'] ?? '')),
            strtolower(trim($data['destination_city'] ?? '')),
            strtoupper(trim($data['destination_state'] ?? '')),
            strtolower(trim($data['equipment_type'] ?? '')),
            round((float)($data['weight'] ?? 0)),
            strtolower(trim($data['broker_name'] ?? '')),
            round((float)($data['gross_rate'] ?? 0), 2),
            strtolower(trim($data['broker_reference'] ?? '')),
            isset($data['pickup_start']) ? substr($data['pickup_start'], 0, 10) : '',
        ]);
        return hash('sha256', $canonical);
    }
}
