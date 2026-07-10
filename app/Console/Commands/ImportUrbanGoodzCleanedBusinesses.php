<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Module;
use App\Models\Store;
use App\Models\UrbanGoodzImportBatch;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportUrbanGoodzCleanedBusinesses extends Command
{
    protected $signature = 'urban-goodz:business-import-cleaned
        {--zip= : Absolute path to the cleaned Urban Goodz import ZIP}
        {--files=import_verified_ready.csv,import_partial_draft_review.csv : Comma-separated CSV files to import from the ZIP}
        {--mode=draft : Must remain draft for this importer}
        {--visibility=private : Must remain private for this importer}
        {--partnered=false : Must remain false for this importer}
        {--exclude-backlog=true : Must remain true for this importer}
        {--disable-age-restricted-fulfillment=true : Must remain true for this importer}
        {--batch-marker= : Unique marker such as phase_data_import_p2_YYYYMMDD_HHMM}
        {--dry-run : Validate and report without writing records}';

    protected $description = 'Safely validate/import cleaned Urban Goodz business CSVs into sourced businesses as private review-eligible listings only.';

    private const VERIFIED_FILE = 'import_verified_ready.csv';
    private const PARTIAL_FILE = 'import_partial_draft_review.csv';
    private const BACKLOG_FILE = 'do_not_import_manual_review_backlog.csv';
    private const SOURCING_FILE = 'sourcing_replacement_targets.csv';

    // Explicit, reviewable mapping from cleaned-CSV business category to an EXISTING
    // module id. This is the authoritative taxonomy resolution for the importer and
    // avoids fuzzy substring collisions (e.g. generic 'beauty' matching the wrong module).
    // Every key below was verified to exist in the modules table.
    private const CSV_CATEGORY_MODULE_MAP = [
        'Restaurants' => 4,                   // Restaurants/Brick and Mortar
        'Food Trucks' => 12,                  // Food Trucks
        'Grocery / Markets' => 5,             // Grocery/Markets
        'Retail / Shopping' => 13,            // Retail/Shopping
        'Beauty / Personal Care' => 14,       // Beauty/Personal Care
        'Beauty Supply / Hair Providerz' => 16, // Beauty Supply/Hair Providers
        'Pharmacy / Health' => 10,            // Pharmacy/Health
        'Home-Based Businessz' => 6,          // Home-Based Businesses
        'Local Events / Creators' => 11,      // Local Events/Creators
        'Liquor / Beverages' => 8,           // Liquor/Beverages
        'THC / Dispensary' => 7,              // THC/CBD
        'Courier / Parcel' => 9,             // Courier/Parcel Delivery
    ];

    // By-design exclusions that must NOT block a partial real import.
    // These rows are intentionally skipped/logged while valid candidates
    // are still staged. True fatal errors are everything else.
    private const NON_FATAL_EXCLUSION_REASONS = [
        'missing_or_invalid_source_url',
        'duplicate_candidate',
    ];

    // Defensive fuzzy fallback only for categories NOT present in the explicit map above.
    private array $categoryModuleMap = [
        'Restaurants' => ['restaurants', 'restaurant', 'food'],
        'Food Trucks' => ['food trucks', 'food truck', 'restaurant', 'food'],
        'Grocery / Markets' => ['grocery / markets', 'grocery', 'market'],
        'Retail / Shopping' => ['retail / shopping', 'retail', 'shopping', 'store'],
        'Beauty / Personal Care' => ['beauty / personal care', 'beauty', 'personal care'],
        'Beauty Supply / Hair Providerz' => ['beauty supply / hair providerz', 'beauty supply', 'hair', 'beauty'],
        'Pharmacy / Health' => ['pharmacy / health', 'pharmacy', 'health'],
        'Liquor / Beverages' => ['liquor / beverages', 'liquor', 'beverage'],
        'THC / Dispensary' => ['thc / dispensary', 'thc', 'cbd', 'dispensary'],
        'Local Events / Creators' => ['local events / creators', 'events', 'creator'],
        'Courier / Parcel' => ['courier / parcel', 'courier', 'parcel'],
        'Home-Based Businessz' => ['home-based businessz', 'home based', 'retail', 'shopping'],
    ];

    public function handle(): int
    {
        $startedAt = now();
        $dryRun = (bool) $this->option('dry-run');
        $zipPath = (string) $this->option('zip');
        $batchMarker = $this->option('batch-marker') ?: 'phase_data_import_p2_' . now()->format('Ymd_Hi');

        if (!$this->guardSafeOptions()) {
            return self::FAILURE;
        }

        $schemaReport = $this->schemaReport();
        $this->line('SCHEMA urban_goodz_sourced_businesses: ' . implode(', ', $schemaReport['urban_goodz_sourced_businesses']));
        $this->line('SCHEMA urban_goodz_import_batches: ' . implode(', ', $schemaReport['urban_goodz_import_batches']));

        $zipValidation = $this->validateZip($zipPath);
        if (!$zipValidation['ok']) {
            foreach ($zipValidation['errors'] as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        [$verifiedRows, $partialRows, $backlogRows] = $this->readZipRows($zipPath);
        $candidateRows = array_merge(
            $this->tagRows($verifiedRows, self::VERIFIED_FILE),
            $this->tagRows($partialRows, self::PARTIAL_FILE)
        );

        $context = $this->loadValidationContext();
        $report = $this->validateRows($candidateRows, $context);

        $this->printReport($report, count($verifiedRows), count($partialRows), count($backlogRows), $dryRun, $batchMarker);

        if ($dryRun) {
            $this->warn('DRY RUN ONLY: no records were written.');
            if ($report['fatal'] > 0) {
                $this->warn("Fatal validation failures remain ({$report['fatal']}); real import would be blocked.");
                return self::FAILURE;
            }
            $this->info("Dry run ready: real import would stage " . count($report['valid_rows']) . " candidate row(s) and skip {$report['excluded_non_fatal']} by-design exclusion(s).");
            return self::SUCCESS;
        }

        if ($report['fatal'] > 0) {
            $this->error("Import blocked: {$report['fatal']} fatal validation failure(s) remain (unresolved taxonomy, invalid/mismatched zone, or missing required fields). Re-run with --dry-run after resolving them.");
            return self::FAILURE;
        }

        DB::transaction(function () use ($report, $verifiedRows, $partialRows, $batchMarker, $startedAt) {
            $batch = UrbanGoodzImportBatch::create([
                'city' => 'multi-zone',
                'state' => 'multi-state',
                'category' => 'Urban Goodz cleaned business import',
                'module' => 'urban_goodz_sourced_businesses',
                'source_query' => $batchMarker,
                'source_platforms' => ['cleaned_csv_zip'],
                'total_found' => count($verifiedRows) + count($partialRows),
                'total_imported' => count($report['valid_rows']),
                'total_needs_review' => count($report['valid_rows']),
                'status' => 'completed',
                'completed_at' => $startedAt,
            ]);

            foreach ($report['valid_rows'] as $validated) {
                UrbanGoodzSourcedBusiness::create($this->mapRowToSourcedBusiness($validated, $batchMarker, $batch));
            }
        });

        $this->info('Import completed into urban_goodz_sourced_businesses only. '
            . count($report['valid_rows']) . ' candidate row(s) staged; '
            . $report['excluded_non_fatal'] . ' by-design exclusion(s) skipped. '
            . 'No stores, items, products, or vendors were created.');
        return self::SUCCESS;
    }

    private function guardSafeOptions(): bool
    {
        $checks = [
            'mode' => 'draft',
            'visibility' => 'private',
            'partnered' => 'false',
            'exclude-backlog' => 'true',
            'disable-age-restricted-fulfillment' => 'true',
        ];

        foreach ($checks as $option => $expected) {
            if (strtolower((string) $this->option($option)) !== $expected) {
                $this->error("Unsafe option --{$option}; expected {$expected}.");
                return false;
            }
        }

        $files = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('files')))));
        sort($files);
        $allowedFiles = [self::PARTIAL_FILE, self::VERIFIED_FILE];
        sort($allowedFiles);
        if ($files !== $allowedFiles) {
            $this->error('--files must include only import_verified_ready.csv and import_partial_draft_review.csv. Backlog/sourcing files are never importable.');
            return false;
        }

        return true;
    }

    private function schemaReport(): array
    {
        return [
            'urban_goodz_sourced_businesses' => Schema::hasTable('urban_goodz_sourced_businesses') ? Schema::getColumnListing('urban_goodz_sourced_businesses') : ['TABLE_MISSING'],
            'urban_goodz_import_batches' => Schema::hasTable('urban_goodz_import_batches') ? Schema::getColumnListing('urban_goodz_import_batches') : ['TABLE_MISSING'],
        ];
    }

    private function validateZip(string $zipPath): array
    {
        $errors = [];
        if ($zipPath === '' || !is_file($zipPath)) {
            return ['ok' => false, 'errors' => ["Cleaned ZIP not found: {$zipPath}"]];
        }

        $names = $this->zipEntryNames($zipPath);
        if ($names === null) {
            return ['ok' => false, 'errors' => ["Unable to inspect ZIP entries: {$zipPath}"]];
        }

        foreach ([self::VERIFIED_FILE, self::PARTIAL_FILE, self::BACKLOG_FILE] as $required) {
            if (!in_array($required, $names, true)) {
                $errors[] = "Required CSV missing from ZIP: {$required}";
            }
        }

        if (!in_array(self::SOURCING_FILE, $names, true)) {
            $this->warn('Optional sourcing replacement CSV missing; no sourcing rows will be imported either way.');
        }

        return ['ok' => count($errors) === 0, 'errors' => $errors];
    }

    private function readZipRows(string $zipPath): array
    {
        return [
            $this->readCsvFromZip($zipPath, self::VERIFIED_FILE),
            $this->readCsvFromZip($zipPath, self::PARTIAL_FILE),
            $this->readCsvFromZip($zipPath, self::BACKLOG_FILE),
        ];
    }

    private function zipEntryNames(string $zipPath): ?array
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return null;
            }
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (isset($stat['name'])) {
                    $names[] = $stat['name'];
                }
            }
            $zip->close();
            return $names;
        }

        $dir = $this->extractZipFallback($zipPath);
        if ($dir === null) {
            return null;
        }

        $names = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $names[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            }
        }

        return $names;
    }

    private function readCsvFromZip(string $zipPath, string $name): array
    {
        $content = false;
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $content = $zip->getFromName($name);
                $zip->close();
            }
        } else {
            $dir = $this->extractZipFallback($zipPath);
            if ($dir !== null) {
                $path = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (is_file($path)) {
                    $content = file_get_contents($path);
                }
            }
        }

        if ($content === false) {
            return [];
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (!$headers) {
            fclose($stream);
            return [];
        }
        $headers = array_map(fn ($header) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)), $headers);

        $rows = [];
        while (($data = fgetcsv($stream)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function extractZipFallback(string $zipPath): ?string
    {
        $target = storage_path('app/urban_goodz_import_tmp/' . md5($zipPath . '|' . filemtime($zipPath) . '|' . filesize($zipPath)));
        if (is_dir($target)) {
            return $target;
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        $command = 'tar -xf ' . escapeshellarg($zipPath) . ' -C ' . escapeshellarg($target);
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 && PHP_OS_FAMILY !== 'Windows') {
            $command = 'unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($target);
            exec($command, $output, $exitCode);
        }
        return $exitCode === 0 ? $target : null;
    }
    private function tagRows(array $rows, string $file): array
    {
        return array_map(function (array $row) use ($file) {
            $row['_source_file'] = $file;
            return $row;
        }, $rows);
    }

    private function loadValidationContext(): array
    {
        $zones = Zone::withoutGlobalScopes()->get(['id', 'name', 'display_name'])->mapWithKeys(function ($zone) {
            return [(int) $zone->id => [
                'name' => $this->normalize($zone->getRawOriginal('name') ?: $zone->name),
                'display_name' => $this->normalize($zone->getRawOriginal('display_name') ?: $zone->display_name),
            ]];
        })->all();

        $modules = Module::withoutGlobalScopes()->get(['id', 'module_name', 'module_type'])->map(function ($module) {
            return [
                'id' => (int) $module->id,
                'module_name' => (string) ($module->getRawOriginal('module_name') ?: $module->module_name),
                'module_type' => (string) $module->module_type,
                'normalized' => $this->normalize($module->getRawOriginal('module_name') ?: $module->module_name),
            ];
        })->values()->all();

        $categoryColumns = Schema::hasColumn('categories', 'name') ? ['id', 'module_id', 'name'] : ['id', 'module_id'];
        $categories = DB::table('categories')->select($categoryColumns)->get()->map(function ($category) {
            $name = property_exists($category, 'name') ? (string) $category->name : '';
            return [
                'id' => (int) $category->id,
                'module_id' => (int) $category->module_id,
                'name' => $name,
                'normalized' => $this->normalize($name),
            ];
        })->values()->all();

        return compact('zones', 'modules', 'categories');
    }

    private function validateRows(array $rows, array $context): array
    {
        $validRows = [];
        $excluded = [];
        $fatalExcluded = 0;
        $duplicates = [];
        $zoneReport = ['valid' => 0, 'invalid' => 0, 'mismatched' => 0];
        $categoryReport = ['mapped' => 0, 'held' => 0, 'category_ids_resolved' => 0, 'category_ids_pending' => 0, 'modules' => []];
        $ageRestricted = 0;
        $seenNameCityState = [];
        $seenWebsite = [];
        $seenPhone = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $reasons = [];

            $sourceUrl = trim((string) ($row['source_url'] ?? ''));
            if (!$this->isValidUrl($sourceUrl)) {
                $reasons[] = 'missing_or_invalid_source_url';
            }

            $zoneId = (int) ($row['zone_id'] ?? 0);
            $zoneName = (string) ($row['zone_name'] ?? '');
            if (!$zoneId || !isset($context['zones'][$zoneId])) {
                $reasons[] = 'unresolved_zone_id';
                $zoneReport['invalid']++;
            } elseif (!$this->zoneNameMatches($zoneName, $context['zones'][$zoneId])) {
                $reasons[] = 'zone_name_mismatch';
                $zoneReport['mismatched']++;
            } else {
                $zoneReport['valid']++;
            }

            $module = $this->resolveModule((string) ($row['category'] ?? ''), $context['modules']);
            $categoryIds = $module ? $this->resolveCategoryIds((string) ($row['category'] ?? ''), ($row['subcategory'] ?? null), $module['id'], $context['categories']) : [];
            if (!$module) {
                $reasons[] = 'unresolved_category_or_module';
                $categoryReport['held']++;
            } else {
                $categoryReport['mapped']++;
                $categoryReport['modules'][$module['module_name']] = ($categoryReport['modules'][$module['module_name']] ?? 0) + 1;
                if (!empty($categoryIds)) {
                    $categoryReport['category_ids_resolved']++;
                } else {
                    $categoryReport['category_ids_pending']++;
                }
            }

            $duplicateReasons = $this->detectDuplicates($row, $seenNameCityState, $seenWebsite, $seenPhone);
            $duplicateReasons = array_merge($duplicateReasons, $this->detectDatabaseDuplicates($row));
            if (!empty($duplicateReasons)) {
                $duplicates[] = [
                    'row' => $rowNumber,
                    'business_name' => $row['business_name'] ?? '',
                    'reasons' => $duplicateReasons,
                ];
                $reasons[] = 'duplicate_candidate';
            }

            $ageRestrictedRow = $this->isAgeRestricted($row);
            if ($ageRestrictedRow) {
                $ageRestricted++;
            }

            if (!empty($reasons)) {
                $excluded[] = [
                    'row' => $rowNumber,
                    'file' => $row['_source_file'] ?? '',
                    'business_name' => $row['business_name'] ?? '',
                    'reasons' => array_values(array_unique($reasons)),
                ];
                $fatalReasons = array_diff(array_unique($reasons), self::NON_FATAL_EXCLUSION_REASONS);
                if (!empty($fatalReasons)) {
                    $fatalExcluded++;
                }
                continue;
            }

            $validRows[] = [
                'row' => $row,
                'module' => $module,
                'category_ids' => $categoryIds,
                'age_restricted' => $ageRestrictedRow,
            ];
        }

        return [
            'valid_rows' => $validRows,
            'excluded' => $excluded,
            'duplicates' => $duplicates,
            'zone_report' => $zoneReport,
            'category_report' => $categoryReport,
            'age_restricted' => $ageRestricted,
            'failed' => count($excluded),
            'fatal' => $fatalExcluded,
            'excluded_non_fatal' => count($excluded) - $fatalExcluded,
        ];
    }

    private function detectDuplicates(array $row, array &$seenNameCityState, array &$seenWebsite, array &$seenPhone): array
    {
        $reasons = [];
        $nameKey = $this->normalize(($row['business_name'] ?? '') . '|' . ($row['city'] ?? '') . '|' . ($row['state'] ?? ''));
        if ($nameKey !== '||') {
            if (isset($seenNameCityState[$nameKey])) {
                $reasons[] = 'csv_duplicate_name_city_state';
            }
            $seenNameCityState[$nameKey] = true;
        }

        $website = $this->normalizeUrl((string) ($row['website'] ?? ''));
        if ($website !== '') {
            if (isset($seenWebsite[$website])) {
                $reasons[] = 'csv_duplicate_website';
            }
            $seenWebsite[$website] = true;
        }

        $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));
        if ($phone !== '') {
            if (isset($seenPhone[$phone])) {
                $reasons[] = 'csv_duplicate_phone';
            }
            $seenPhone[$phone] = true;
        }

        return $reasons;
    }

    private function detectDatabaseDuplicates(array $row): array
    {
        $reasons = [];
        $name = $this->normalize((string) ($row['business_name'] ?? ''));
        $city = $this->normalize((string) ($row['city'] ?? ''));
        $state = $this->normalize((string) ($row['state'] ?? ''));
        $website = $this->normalizeUrl((string) ($row['website'] ?? ''));
        $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));

        if ($name !== '') {
            $sourced = UrbanGoodzSourcedBusiness::query()
                ->whereRaw('LOWER(name) = ?', [$name])
                ->when($city !== '', fn ($query) => $query->whereRaw('LOWER(city) = ?', [$city]))
                ->when($state !== '', fn ($query) => $query->whereRaw('LOWER(state) = ?', [$state]))
                ->exists();
            if ($sourced) {
                $reasons[] = 'db_sourced_duplicate_name_city_state';
            }

            $store = Store::withoutGlobalScopes()
                ->whereRaw('LOWER(name) = ?', [$name])
                ->when($city !== '', fn ($query) => $query->whereRaw('LOWER(address) LIKE ?', ['%' . $city . '%']))
                ->exists();
            if ($store) {
                $reasons[] = 'db_store_duplicate_name_city_or_address';
            }
        }

        if ($website !== '') {
            if (UrbanGoodzSourcedBusiness::query()->whereRaw('LOWER(website) = ?', [$website])->exists()) {
                $reasons[] = 'db_sourced_duplicate_website';
            }
            if (Schema::hasColumn('stores', 'website') && Store::withoutGlobalScopes()->whereRaw('LOWER(website) = ?', [$website])->exists()) {
                $reasons[] = 'db_store_duplicate_website';
            }
        }

        if ($phone !== '') {
            if (UrbanGoodzSourcedBusiness::query()->where('phone', $row['phone'])->exists()) {
                $reasons[] = 'db_sourced_duplicate_phone';
            }
            if (Store::withoutGlobalScopes()->where('phone', $row['phone'])->exists()) {
                $reasons[] = 'db_store_duplicate_phone';
            }
        }

        return $reasons;
    }

    private function mapRowToSourcedBusiness(array $validated, string $batchMarker, UrbanGoodzImportBatch $batch): array
    {
        $row = $validated['row'];
        $ageRestricted = $validated['age_restricted'];
        $fulfillmentModes = $ageRestricted ? ['review_only'] : $this->fulfillmentModes($row);
        $tags = array_values(array_filter([
            $row['category'] ?? null,
            $row['subcategory'] ?? null,
            $row['review_status'] ?? null,
            $row['verification_status'] ?? null,
            $row['_source_file'] ?? null,
            'review_eligible_listing',
            'partnered_status_false',
            $ageRestricted ? 'age_restricted_review_only' : null,
            $batch->source_query,
        ]));

        return [
            'name' => $this->nullIfBlank($row['business_name'] ?? null),
            'slug' => Str::slug((string) ($row['business_name'] ?? 'business')) . '-' . Str::lower(Str::random(6)),
            'display_name' => $this->nullIfBlank($row['business_name'] ?? null),
            'description' => $this->nullIfBlank($row['description'] ?? null),
            'short_description' => $this->nullIfBlank(Str::limit((string) ($row['description'] ?? ''), 180, '')),
            'business_type' => $this->nullIfBlank($row['business_type'] ?? null),
            'module_id' => $validated['module']['id'],
            'module_name' => $validated['module']['module_name'],
            'category_ids' => $validated['category_ids'],
            'tags' => $tags,
            'phone' => $this->nullIfBlank($row['phone'] ?? null),
            'email' => $this->nullIfBlank($row['email'] ?? null),
            'website' => $this->nullIfBlank($row['website'] ?? null),
            'social_links' => array_filter([
                'instagram' => $this->nullIfBlank($row['instagram'] ?? null),
                'facebook' => $this->nullIfBlank($row['facebook'] ?? null),
                'tiktok' => $this->nullIfBlank($row['tiktok'] ?? null),
            ]),
            'address' => $this->nullIfBlank($row['address'] ?? null),
            'city' => $this->nullIfBlank($row['city'] ?? null),
            'state' => $this->nullIfBlank($row['state'] ?? null),
            'country_code' => 'US',
            'zip' => $this->nullIfBlank($row['zip'] ?? null),
            'latitude' => $this->nullIfBlank($row['latitude'] ?? null),
            'longitude' => $this->nullIfBlank($row['longitude'] ?? null),
            'zone_id' => (int) $row['zone_id'],
            'zone_name' => $this->nullIfBlank($row['zone_name'] ?? null),
            'is_launch_market' => false,
            'is_nationwide' => false,
            'is_worldwide' => false,
            'is_black_owned' => $this->nullableBool($row['black_owned'] ?? null),
            'is_woman_owned' => $this->nullableBool($row['women_owned'] ?? null),
            'is_local_business' => true,
            'fulfillment_modes' => $fulfillmentModes,
            'onboarding_status' => 'pending_review',
            'source_status' => 'admin_import',
            'source_urls' => [$row['source_url']],
            'data_confidence_score' => ($row['_source_file'] ?? '') === self::VERIFIED_FILE ? 80 : 55,
            'demand_score' => 0,
            'last_verified_at' => null,
            'admin_review_status' => 'pending',
            'created_by_source' => $batchMarker,
        ];
    }

    private function fulfillmentModes(array $row): array
    {
        $modes = ['review_only'];
        if ($this->truthy($row['pickup_available'] ?? null)) {
            $modes[] = 'pickup_review_required';
        }
        if ($this->truthy($row['order_anywhere_enabled'] ?? null)) {
            $modes[] = 'order_anywhere_review_required';
        }
        return array_values(array_unique($modes));
    }

    private function resolveModule(string $category, array $modules): ?array
    {
        $category = trim($category);

        // 1) Authoritative explicit mapping (deterministic, reviewable).
        if (isset(self::CSV_CATEGORY_MODULE_MAP[$category])) {
            $targetId = self::CSV_CATEGORY_MODULE_MAP[$category];
            foreach ($modules as $module) {
                if ($module['id'] === $targetId) {
                    return $module;
                }
            }
        }

        // 2) Defensive fuzzy fallback for any category not in the explicit map.
        //    Most-specific needles are tried first to avoid generic collisions
        //    (e.g. 'beauty' matching the wrong beauty module).
        $normalizedCategory = $this->normalize($category);
        $needles = $this->categoryModuleMap[$category] ?? [$normalizedCategory];
        $needles = array_map(fn ($value) => $this->normalize((string) $value), $needles);
        usort($needles, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($needles as $needle) {
            foreach ($modules as $module) {
                if ($needle !== '' && (str_contains($module['normalized'], $needle) || str_contains($needle, $module['normalized']))) {
                    return $module;
                }
            }
        }

        return null;
    }

    // Best-effort granular category resolution. Only assigns REAL existing
    // category ids via an EXACT normalized match against the subcategory or the
    // main category. Never invents, defaults to, or falls back to a fake id.
    // When nothing matches (the common case here), an empty array is returned,
    // which is honest for a review-eligible draft (granular taxonomy is
    // assigned later during admin review).
    private function resolveCategoryIds(string $category, ?string $subcategory, int $moduleId, array $categories): array
    {
        $normalizedCategory = $this->normalize($category);
        $normalizedSub = $this->normalize((string) ($subcategory ?? ''));
        $matches = [];
        foreach ($categories as $candidate) {
            if ($candidate['module_id'] !== $moduleId || $candidate['normalized'] === '') {
                continue;
            }
            if ($normalizedSub !== '' && $candidate['normalized'] === $normalizedSub) {
                $matches[] = $candidate['id'];
            } elseif ($normalizedCategory !== '' && $candidate['normalized'] === $normalizedCategory) {
                $matches[] = $candidate['id'];
            }
        }

        return array_values(array_unique($matches));
    }

    private function printReport(array $report, int $verified, int $partial, int $backlog, bool $dryRun, string $batchMarker): void
    {
        $this->info('Urban Goodz cleaned business import validation report');
        $this->line('Dry run: ' . ($dryRun ? 'yes' : 'no'));
        $this->line('Batch marker: ' . $batchMarker);
        $this->line('Verified rows: ' . $verified);
        $this->line('Partial rows: ' . $partial);
        $this->line('Backlog rows excluded: ' . $backlog);
        $this->line('Candidate rows scanned: ' . ($verified + $partial));
        $this->line('Candidate import count: ' . count($report['valid_rows']));
        $this->line('Excluded row count: ' . count($report['excluded']));
        $this->line('Fatal validation failures (blocks import): ' . $report['fatal']);
        $this->line('By-design exclusions skipped (non-fatal): ' . $report['excluded_non_fatal']);
        $this->line('Age-restricted review-only rows: ' . $report['age_restricted']);
        $this->line('Zone report: ' . json_encode($report['zone_report']));
        $this->line('Category/module report: ' . json_encode($report['category_report']));
        $this->line('Taxonomy: module-mapped=' . $report['category_report']['mapped']
            . ' held=' . $report['category_report']['held']
            . ' category_ids_resolved=' . $report['category_report']['category_ids_resolved']
            . ' category_ids_pending=' . $report['category_report']['category_ids_pending']);
        $this->line('Duplicate count: ' . count($report['duplicates']));

        if (!empty($report['excluded'])) {
            $this->warn('Excluded rows by reason:');
            $counts = [];
            foreach ($report['excluded'] as $excluded) {
                foreach ($excluded['reasons'] as $reason) {
                    $counts[$reason] = ($counts[$reason] ?? 0) + 1;
                }
            }
            foreach ($counts as $reason => $count) {
                $this->line("- {$reason}: {$count}");
            }
        }

        if (!empty($report['duplicates'])) {
            $this->warn('Duplicate samples:');
            foreach (array_slice($report['duplicates'], 0, 20) as $duplicate) {
                $this->line('- row ' . $duplicate['row'] . ' ' . $duplicate['business_name'] . ': ' . implode(', ', $duplicate['reasons']));
            }
        }

        $this->line('Live stores/items/vendors created: 0');
    }

    private function zoneNameMatches(string $csvName, array $zone): bool
    {
        $csv = $this->normalize($csvName);
        if ($csv === '') {
            return false;
        }
        $approvedAliases = [
            'greater houston area' => ['houston', 'greater houston'],
            'greater dallas area' => ['dallas', 'greater dallas'],
            'greater austin area' => ['austin', 'greater austin'],
            'greater atlanta area' => ['atlanta', 'greater atlanta'],
            'greater los angeles area' => ['los angeles', 'la', 'greater los angeles'],
            'greater nyc area' => ['new york', 'nyc', 'greater nyc'],
            'greater dmv area' => ['dmv', 'washington dc', 'greater dmv'],
            'brazoria matagorda galveston wharton jefferson county areas' => ['brazoria', 'galveston', 'jefferson'],
        ];
        $dbNames = array_filter([$zone['name'], $zone['display_name']]);
        foreach ($dbNames as $dbName) {
            if ($csv === $dbName || str_contains($csv, $dbName) || str_contains($dbName, $csv)) {
                return true;
            }
            foreach ($approvedAliases[$csv] ?? [] as $alias) {
                if ($dbName === $this->normalize($alias) || str_contains($dbName, $this->normalize($alias))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isAgeRestricted(array $row): bool
    {
        $text = $this->normalize(implode(' ', [
            $row['category'] ?? '',
            $row['subcategory'] ?? '',
            $row['business_type'] ?? '',
            $row['description'] ?? '',
        ]));
        foreach (['liquor', 'beverage', 'thc', 'cbd', 'dispensary', 'cannabis', 'vape', 'tobacco'] as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }
        return false;
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function normalize($value): string
    {
        $value = Str::lower(trim((string) $value));
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function normalizeUrl(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('#^https?://#', '', $value);
        return rtrim((string) $value, '/');
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableBool($value): ?bool
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }
        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    private function truthy($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }
}
