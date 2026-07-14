<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class LoadSourceCredential extends Model
{
    protected $table = 'load_source_credentials';

    const STATUSES = ['active', 'expired', 'revoked'];

    protected $fillable = [
        'source_id', 'credential_key', 'encrypted_value', 'status', 'expires_at', 'last_validated_at',
    ];

    protected $hidden = ['encrypted_value'];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function source(): BelongsTo { return $this->belongsTo(LoadSource::class, 'source_id'); }

    public function getDecryptedValue(): ?string
    {
        try {
            return Crypt::decryptString($this->encrypted_value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
