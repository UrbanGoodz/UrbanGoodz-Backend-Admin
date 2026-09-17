<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrbanGoodzSourcedProductImport extends Command
{
    protected $signature = 'urban-goodz:sourced-product-import
        {--batch-marker= : Required. Batch marker of staged businesses.}
        {--json= : Path to catalog products JSON file.}
        {--upcharge=23.0 : Upcharge percentage to add to base prices (defaults to 23.0%).}
        {--replace : Replace existing staged products for the batch before inserting.}
        {--dry-run : Simulate only (default behavior).}
        {--apply : Perform the product import. Required for writing changes.}';

    protected $description = 'Import real product offerings and upcharged pricing into urban_goodz_sourced_products for staged businesses.';

    private function norm($s)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
    }

    public function handle()
    {
        $marker = $this->option('batch-marker');
        if (!$marker) {
            $this->error('Refusing: --batch-marker is required.');
            return self::FAILURE;
        }

        $upchargeRate = (float) $this->option('upcharge');
        $replace = (bool) $this->option('replace');

        $jsonPath = $this->option('json');
        if (!$jsonPath) {
            $home = getenv('USERPROFILE') ?: getenv('HOME');
            $jsonPath = $home . '/.gemini/antigravity/brain/e729ffa3-729e-4370-9447-6aa0245a6be7/scratch/catalog_products_export.json';
        }

        if (!file_exists($jsonPath)) {
            $this->error("Catalog JSON file not found at: {$jsonPath}");
            return self::FAILURE;
        }

        $apply = $this->option('apply');
        $dryRun = !$apply;

        $this->info("=== Urban Goodz Sourced Product Import (With Platform Upcharge) ===");
        $this->info("Batch marker: {$marker}");
        $this->info("Catalog file: {$jsonPath}");
        $this->info("Platform upcharge rate: {$upchargeRate}%");
        $this->info($dryRun ? 'MODE: DRY-RUN (no database records will be created)' : 'MODE: APPLY (writing product records)');

        $businesses = DB::table('urban_goodz_sourced_businesses')
            ->where('created_by_source', $marker)
            ->get();

        if ($businesses->isEmpty()) {
            $this->error("No businesses found in urban_goodz_sourced_businesses with created_by_source='{$marker}'.");
            return self::FAILURE;
        }

        $catalog = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($catalog)) {
            $this->error('Failed to parse catalog JSON.');
            return self::FAILURE;
        }

        $catalogMap = [];
        foreach ($catalog as $item) {
            $key = $this->norm($item['business_name'] ?? '') . '|' . $this->norm($item['city'] ?? '') . '|' . $this->norm($item['state'] ?? '');
            $catalogMap[$key] = $item;
        }

        $now = now();
        $recordsToInsert = [];
        $matchedBiz = 0;
        $missingBiz = [];

        foreach ($businesses as $b) {
            $key = $this->norm($b->name) . '|' . $this->norm($b->city) . '|' . $this->norm($b->state);
            if (!isset($catalogMap[$key])) {
                $missingBiz[] = "{$b->name} ({$b->city}, {$b->state})";
                continue;
            }

            $matchedBiz++;
            $item = $catalogMap[$key];
            $products = $item['products'] ?? [];

            foreach ($products as $p) {
                $name = trim((string) ($p['name'] ?? ''));
                $basePrice = (float) ($p['price'] ?? 0);
                $image = trim((string) ($p['image'] ?? ''));

                if ($name === '' || $basePrice <= 0) {
                    continue;
                }

                // Apply platform upcharge percentage: e.g. base $10 + 23% = $12.30
                $upchargedPrice = round($basePrice * (1 + ($upchargeRate / 100)), 2);

                $recordsToInsert[] = [
                    'sourced_business_id' => $b->id,
                    'store_id' => null,
                    'module_id' => $b->module_id,
                    'category_id' => null,
                    'subcategory_id' => null,
                    'name' => $name,
                    'slug' => Str::slug($name) . '-' . substr(md5($b->id . $name), 0, 6),
                    'short_description' => $name . ' ($' . number_format($upchargedPrice, 2) . ')',
                    'full_description' => $name . ' offered by ' . $b->name . ' in ' . $b->city . ', ' . $b->state . '. (Base price: $' . number_format($basePrice, 2) . ' + ' . $upchargeRate . '% platform upcharge = $' . number_format($upchargedPrice, 2) . ').',
                    'price' => $upchargedPrice,
                    'price_type' => 'fixed',
                    'currency' => 'USD',
                    'stock_status' => 'in_stock',
                    'item_type' => 'product',
                    'images' => json_encode([$image]),
                    'thumbnail' => $image,
                    'source_url' => $b->website ?: (is_array($b->source_urls) ? ($b->source_urls[0] ?? '') : $b->source_urls),
                    'source_type' => 'directory',
                    'source_confidence' => 90,
                    'fulfillment_type' => null,
                    'requires_quote' => 0,
                    'requires_admin_review' => 0,
                    'admin_review_status' => 'pending',
                    'validation_status' => 'valid',
                    'validation_errors' => null,
                    'is_active' => 1,
                    'is_public' => 0,
                    'api_visible' => 0,
                    'shopper_visible' => 0,
                    'import_batch_id' => $b->import_batch_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->info("Matched businesses: {$matchedBiz} / " . count($businesses));
        $this->info("Total products prepared: " . count($recordsToInsert));

        if (!empty($missingBiz)) {
            $this->warn("Unmatched businesses (" . count($missingBiz) . "): " . implode(', ', array_slice($missingBiz, 0, 5)));
        }

        if ($dryRun) {
            $this->warn("DRY-RUN ONLY: " . count($recordsToInsert) . " product rows would be inserted into urban_goodz_sourced_products. No changes made.");
            if (!empty($recordsToInsert)) {
                $sample = array_intersect_key($recordsToInsert[0], array_flip(['sourced_business_id', 'name', 'price', 'full_description', 'thumbnail']));
                $this->line("Sample upcharged product: " . json_encode($sample, JSON_PRETTY_PRINT));
            }
            return self::SUCCESS;
        }

        DB::transaction(function () use ($businesses, $recordsToInsert, $replace) {
            if ($replace) {
                DB::table('urban_goodz_sourced_products')
                    ->whereIn('sourced_business_id', $businesses->pluck('id'))
                    ->delete();
            }

            // Insert in chunks of 50
            foreach (array_chunk($recordsToInsert, 50) as $chunk) {
                DB::table('urban_goodz_sourced_products')->insert($chunk);
            }
        });

        $this->info("Successfully inserted " . count($recordsToInsert) . " upcharged products into urban_goodz_sourced_products!");
        return self::SUCCESS;
    }
}
