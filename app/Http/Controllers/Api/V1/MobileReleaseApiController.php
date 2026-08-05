<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\MobileRelease;
use App\Models\RemoteConfig;
use App\Services\MobileReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReleaseApiController extends Controller
{
    public function __construct(private readonly MobileReleaseService $releaseService) {}

    public function version(Request $request): JsonResponse
    {
        $app = strtolower($request->query('app', 'shopper'));
        $platform = strtolower($request->query('platform', 'android'));
        $buildNumber = (int) $request->query('build_number', 1);

        $result = $this->releaseService->checkVersion($app, $platform, $buildNumber);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        $app = strtolower($request->query('app', 'shopper'));
        $platform = strtolower($request->query('platform', 'android'));

        $fashionFit = RemoteConfig::getValue('fashion_fit', []);
        $marketplaceModules = RemoteConfig::getValue('marketplace_modules', []);

        $configs = RemoteConfig::where('is_active', true)
            ->whereIn('app_name', [$app, 'all'])
            ->whereIn('platform', [$platform, 'all'])
            ->get()
            ->pluck('value', 'key');

        return response()->json([
            'status' => 'success',
            'app' => $app,
            'platform' => $platform,
            'fashion_fit' => $fashionFit,
            'marketplace_modules' => $marketplaceModules,
            'configs' => $configs,
        ]);
    }

    public function featureFlags(Request $request): JsonResponse
    {
        $app = strtolower($request->query('app', 'shopper'));
        $platform = strtolower($request->query('platform', 'android'));

        $flags = FeatureFlag::all();
        $evaluated = [];

        foreach ($flags as $flag) {
            $evaluated[$flag->key] = FeatureFlag::isEnabled($flag->key, ['app_name' => $app, 'platform' => $platform]);
        }

        return response()->json([
            'status' => 'success',
            'app' => $app,
            'platform' => $platform,
            'flags' => $evaluated,
        ]);
    }

    public function downloadCount(Request $request): JsonResponse
    {
        $uuid = $request->input('uuid');
        if ($uuid) {
            $this->releaseService->incrementDownload($uuid);
        }
        return response()->json(['status' => 'success']);
    }

    public function installCount(Request $request): JsonResponse
    {
        $uuid = $request->input('uuid');
        if ($uuid) {
            $this->releaseService->incrementInstall($uuid);
        }
        return response()->json(['status' => 'success']);
    }
}
