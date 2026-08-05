<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MobileRelease;
use Illuminate\Support\Str;

MobileRelease::updateOrCreate(
    ['app_name' => 'shopper', 'platform' => 'android', 'build_number' => 200],
    [
        'uuid' => (string) Str::uuid(),
        'version_name' => '1.3.0',
        'minimum_version_name' => '1.0.0',
        'minimum_build_number' => 100,
        'required' => false,
        'apk_url' => 'https://urbangoodz.app/releases/shopper-update.apk',
        'release_notes' => 'P0 Releases: Photo-Assisted Sizing instant measurement resolution, AI Operations Chief of Staff, and Remote Config feature controls.',
        'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        'signing_fingerprint' => 'SHA256:7B:88:9C:2A:EF:12:34:56:78:90:AB:CD:EF:FE:DC:BA',
        'enabled' => true,
        'download_count' => 12,
        'install_count' => 8,
    ]
);

MobileRelease::updateOrCreate(
    ['app_name' => 'vendor', 'platform' => 'android', 'build_number' => 200],
    [
        'uuid' => (string) Str::uuid(),
        'version_name' => '1.3.0',
        'minimum_version_name' => '1.0.0',
        'minimum_build_number' => 100,
        'required' => false,
        'apk_url' => 'https://urbangoodz.app/releases/vendor-update.apk',
        'release_notes' => 'Vendor App Release: In-App Updates, Order Anywhere Management, and Instant Settlements.',
        'sha256' => 'a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0',
        'signing_fingerprint' => 'SHA256:7B:88:9C:2A:EF:12:34:56:78:90:AB:CD:EF:FE:DC:BA',
        'enabled' => true,
        'download_count' => 5,
        'install_count' => 3,
    ]
);

MobileRelease::updateOrCreate(
    ['app_name' => 'driver', 'platform' => 'android', 'build_number' => 200],
    [
        'uuid' => (string) Str::uuid(),
        'version_name' => '1.3.0',
        'minimum_version_name' => '1.0.0',
        'minimum_build_number' => 100,
        'required' => false,
        'apk_url' => 'https://urbangoodz.app/releases/driver-update.apk',
        'release_notes' => 'Driver App Release: Live Load Board Dispatcher, Route Optimization, and Medical Courier Scanner.',
        'sha256' => 'f9e8d7c6b5a432109876543210fedcba9876543210fedcba9876543210fedcba',
        'signing_fingerprint' => 'SHA256:7B:88:9C:2A:EF:12:34:56:78:90:AB:CD:EF:FE:DC:BA',
        'enabled' => true,
        'download_count' => 9,
        'install_count' => 7,
    ]
);

echo "MOBILE RELEASES SEEDED SUCCESSFULLY\n";
