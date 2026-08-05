<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\MobileRelease;
use App\Models\RemoteConfig;
use App\Services\MobileReleaseService;
use Illuminate\Http\Request;

class MobileReleaseController extends Controller
{
    public function __construct(private readonly MobileReleaseService $releaseService) {}

    public function index(Request $request)
    {
        $app = strtolower($request->query('app', 'shopper'));
        $platform = strtolower($request->query('platform', 'android'));

        $releases = MobileRelease::forApp($app, $platform)
            ->orderBy('build_number', 'desc')
            ->get();

        $latest = $releases->firstWhere('enabled', true) ?? $releases->first();

        return view('admin-views.mobile-releases.index', compact('app', 'platform', 'releases', 'latest'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'in:shopper,vendor,driver'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'version_name' => ['required', 'string', 'max:30'],
            'build_number' => ['required', 'integer', 'min:1'],
            'minimum_version_name' => ['nullable', 'string', 'max:30'],
            'minimum_build_number' => ['nullable', 'integer', 'min:1'],
            'required' => ['sometimes', 'boolean'],
            'apk_file' => ['nullable', 'file', 'max:153600'], // max 150MB
            'apk_url' => ['nullable', 'url'],
            'release_notes' => ['nullable', 'string'],
            'staged_rollout_percent' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $this->releaseService->createRelease($data, $request->file('apk_file'));

        return redirect()->back()->with('success', 'Mobile release published successfully.');
    }

    public function toggle(int $id)
    {
        $release = MobileRelease::findOrFail($id);
        $release->update(['enabled' => !$release->enabled]);

        return redirect()->back()->with('success', "Release v{$release->version_name} " . ($release->enabled ? 'enabled' : 'disabled') . '.');
    }

    public function rollback(Request $request)
    {
        $app = strtolower($request->input('app_name', 'shopper'));
        $platform = strtolower($request->input('platform', 'android'));

        try {
            $previous = $this->releaseService->rollback($app, $platform);
            return redirect()->back()->with('success', "Successfully rolled back {$app} to v{$previous->version_name} (build {$previous->build_number}).");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $release = MobileRelease::findOrFail($id);
        $release->delete();

        return redirect()->back()->with('success', 'Release record deleted.');
    }

    public function configIndex()
    {
        $configs = RemoteConfig::orderBy('key')->get();
        return view('admin-views.mobile-releases.config', compact('configs'));
    }

    public function configUpdate(Request $request, int $id)
    {
        $config = RemoteConfig::findOrFail($id);
        $value = $request->input('value');
        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }
        $config->update([
            'value' => $value,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', "Remote config key '{$config->key}' updated.");
    }

    public function flagsIndex()
    {
        $flags = FeatureFlag::orderBy('key')->get();
        return view('admin-views.mobile-releases.flags', compact('flags'));
    }

    public function flagToggle(int $id)
    {
        $flag = FeatureFlag::findOrFail($id);
        $flag->update(['enabled_globally' => !$flag->enabled_globally]);

        return redirect()->back()->with('success', "Feature flag '{$flag->name}' " . ($flag->enabled_globally ? 'enabled' : 'disabled') . '.');
    }
}
