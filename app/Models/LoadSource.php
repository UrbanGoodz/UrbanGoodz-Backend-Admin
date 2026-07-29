<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class LoadSource extends Model
{
    use SoftDeletes;

    protected $table = 'load_sources';

    const TYPES = ['api', 'email', 'manual', 'internal', 'referral', 'deep_link'];
    const API_STATUSES = ['awaiting_credentials', 'configured', 'connected', 'error', 'disabled'];
    const PARTNERSHIP_STATUSES = ['pending', 'applied', 'active', 'inactive', 'terminated'];

    protected $fillable = [
        'source_key', 'name', 'type', 'enabled', 'api_status', 'partnership_status',
        'supports_bidding', 'supports_booking', 'supports_automation',
        'description', 'source_url', 'deep_link_template', 'rate_limit_per_minute',
        'last_sync_at', 'last_success_at', 'last_error_at', 'last_error_message',
        'total_syncs', 'total_loads_sourced', 'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'supports_bidding' => 'boolean',
        'supports_booking' => 'boolean',
        'supports_automation' => 'boolean',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function externalLoads(): HasMany { return $this->hasMany(ExternalLoad::class, 'source_id'); }
    public function credentials(): HasMany { return $this->hasMany(LoadSourceCredential::class, 'source_id'); }
    public function syncRuns(): HasMany { return $this->hasMany(LoadSourceSyncRun::class, 'source_id'); }
    public function errors(): HasMany { return $this->hasMany(LoadSourceError::class, 'source_id'); }
    public function searches(): HasMany { return $this->hasMany(LoadSourceSearch::class, 'source_id'); }

    public function scopeEnabled($query) { return $query->where('enabled', true); }
    public function scopeWithApiAccess($query) { return $query->where('api_status', 'connected'); }

    public function isFullyConfigured(): bool
    {
        return $this->enabled && $this->api_status === 'connected';
    }

    public function recordSync(int $loadsFound, int $new, int $updated, int $duplicates, float $durationMs): void
    {
        $this->update([
            'last_sync_at' => now(),
            'last_success_at' => now(),
            'total_syncs' => $this->total_syncs + 1,
            'total_loads_sourced' => $this->total_loads_sourced + $loadsFound,
        ]);
    }

    public function recordError(string $message): void
    {
        $this->update([
            'last_error_at' => now(),
            'last_error_message' => $message,
            'api_status' => 'error',
        ]);
    }

    public function getCredentialValue(string $key): ?string
    {
        $cred = $this->credentials()->where('credential_key', $key)->where('status', 'active')->first();
        if (!$cred) return null;
        try {
            return Crypt::decryptString($cred->encrypted_value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setCredential(string $key, string $value): void
    {
        $this->credentials()->updateOrCreate(
            ['credential_key' => $key],
            [
                'encrypted_value' => Crypt::encryptString($value),
                'status' => 'active',
            ]
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->api_status) {
            'connected' => 'Live',
            'configured' => 'Configured',
            'error' => 'Error: ' . ($this->last_error_message ?? 'Unknown'),
            'disabled' => 'Disabled',
            'awaiting_credentials' => 'Awaiting Partner API Access',
            default => ucfirst($this->api_status),
        };
    }

    public function getCredentialStatusAttribute(): string
    {
        $credentials = $this->relationLoaded('credentials') ? $this->credentials : $this->credentials()->get();

        if ($credentials->contains('status', 'active')) {
            return 'active';
        }
        if ($credentials->contains('status', 'expired')) {
            return 'expired';
        }

        return 'not_configured';
    }

    public function getLastSuccessfulSyncAtAttribute()
    {
        return $this->last_success_at;
    }

    public function getRecordsImportedCountAttribute(): int
    {
        return $this->external_loads_count ?? $this->total_loads_sourced ?? 0;
    }
}
