<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MobileRelease extends Model
{
    use HasFactory;

    protected $table = 'mobile_releases';

    protected $fillable = [
        'uuid',
        'app_name',
        'platform',
        'version_name',
        'build_number',
        'minimum_version_name',
        'minimum_build_number',
        'required',
        'apk_url',
        'file_id',
        'release_notes',
        'sha256',
        'signing_fingerprint',
        'release_date',
        'enabled',
        'staged_rollout_percent',
        'rollback_version',
        'download_count',
        'install_count',
        'crash_count',
    ];

    protected $casts = [
        'build_number' => 'integer',
        'minimum_build_number' => 'integer',
        'required' => 'boolean',
        'enabled' => 'boolean',
        'staged_rollout_percent' => 'integer',
        'download_count' => 'integer',
        'install_count' => 'integer',
        'crash_count' => 'integer',
        'release_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->release_date)) {
                $model->release_date = now();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForApp($query, string $appName, string $platform = 'android')
    {
        return $query->where('app_name', strtolower($appName))
            ->where('platform', strtolower($platform));
    }

    public function isNewerThan(int $currentBuildNumber): bool
    {
        return $this->build_number > $currentBuildNumber;
    }

    public function isRequiredFor(int $currentBuildNumber): bool
    {
        if ($this->required) {
            return true;
        }
        return $currentBuildNumber < $this->minimum_build_number;
    }
}
