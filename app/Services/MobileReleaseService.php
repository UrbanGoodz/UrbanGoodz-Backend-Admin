<?php

namespace App\Services;

use App\Models\MobileRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MobileReleaseService
{
    public function createRelease(array $data, ?UploadedFile $apkFile = null): MobileRelease
    {
        $appName = strtolower($data['app_name']);
        $platform = strtolower($data['platform'] ?? 'android');
        $versionName = $data['version_name'];
        $buildNumber = (int)$data['build_number'];

        $sha256 = $data['sha256'] ?? null;
        $apkUrl = $data['apk_url'] ?? null;

        if ($apkFile) {
            $sha256 = hash_file('sha256', $apkFile->getRealPath());
            $filename = "releases/{$appName}/{$platform}/v{$versionName}_{$buildNumber}_" . Str::random(8) . '.apk';
            $path = Storage::disk('public')->putFileAs('', $apkFile, $filename);
            $apkUrl = asset('storage/' . $filename);
        }

        if (empty($sha256)) {
            $sha256 = hash('sha256', $appName . $versionName . $buildNumber . time());
        }

        // Disable older releases if publishing new active build
        if (!empty($data['enabled'])) {
            MobileRelease::forApp($appName, $platform)
                ->where('enabled', true)
                ->update(['enabled' => false]);
        }

        return MobileRelease::create([
            'uuid' => (string) Str::uuid(),
            'app_name' => $appName,
            'platform' => $platform,
            'version_name' => $versionName,
            'build_number' => $buildNumber,
            'minimum_version_name' => $data['minimum_version_name'] ?? $versionName,
            'minimum_build_number' => (int)($data['minimum_build_number'] ?? $buildNumber),
            'required' => (bool)($data['required'] ?? false),
            'apk_url' => $apkUrl,
            'release_notes' => $data['release_notes'] ?? "Release v{$versionName} build {$buildNumber}",
            'sha256' => $sha256,
            'signing_fingerprint' => $data['signing_fingerprint'] ?? 'SHA256:7B:88:9C:2A:EF:12:34:56:78:90:AB:CD:EF:FE:DC:BA',
            'release_date' => now(),
            'enabled' => (bool)($data['enabled'] ?? true),
            'staged_rollout_percent' => (int)($data['staged_rollout_percent'] ?? 100),
            'rollback_version' => $data['rollback_version'] ?? null,
        ]);
    }

    public function getLatestRelease(string $appName, string $platform = 'android'): ?MobileRelease
    {
        return MobileRelease::forApp($appName, $platform)
            ->active()
            ->orderBy('build_number', 'desc')
            ->first();
    }

    public function checkVersion(string $appName, string $platform, int $currentBuildNumber): array
    {
        $latest = $this->getLatestRelease($appName, $platform);

        if (!$latest) {
            return [
                'has_update' => false,
                'required' => false,
                'current_version' => null,
                'latest_release' => null,
            ];
        }

        $hasUpdate = $latest->build_number > $currentBuildNumber;
        $required = $hasUpdate && ($latest->required || $currentBuildNumber < $latest->minimum_build_number);

        return [
            'has_update' => $hasUpdate,
            'required' => $required,
            'application' => $latest->app_name,
            'platform' => $latest->platform,
            'current_build' => $currentBuildNumber,
            'latest_version' => $latest->version_name,
            'latest_build' => $latest->build_number,
            'minimum_version' => $latest->minimum_version_name,
            'minimum_build' => $latest->minimum_build_number,
            'apk_url' => $latest->apk_url,
            'release_notes' => $latest->release_notes,
            'sha256' => $latest->sha256,
            'signing_fingerprint' => $latest->signing_fingerprint,
            'release_date' => $latest->release_date?->toIso8601String(),
        ];
    }

    public function rollback(string $appName, string $platform = 'android'): MobileRelease
    {
        $current = $this->getLatestRelease($appName, $platform);
        if (!$current) {
            throw new RuntimeException("No active release to roll back for {$appName}");
        }

        $previous = MobileRelease::forApp($appName, $platform)
            ->where('build_number', '<', $current->build_number)
            ->orderBy('build_number', 'desc')
            ->first();

        if (!$previous) {
            throw new RuntimeException("No previous build found to roll back to for {$appName}");
        }

        $current->update(['enabled' => false]);
        $previous->update(['enabled' => true, 'rollback_version' => $current->version_name]);

        return $previous;
    }

    public function incrementDownload(string $uuid): void
    {
        MobileRelease::where('uuid', $uuid)->increment('download_count');
    }

    public function incrementInstall(string $uuid): void
    {
        MobileRelease::where('uuid', $uuid)->increment('install_count');
    }
}
