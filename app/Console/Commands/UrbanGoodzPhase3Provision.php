<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Store;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrbanGoodzPhase3Provision extends Command
{
    protected $signature = 'urban-goodz:phase3-provision
        {--batch-marker=urban_goodz_phase3_sourcing_20260917_1458 : Batch marker of staged rows}
        {--vendor-id= : Required. Vendor that owns the unclaimed stores until claimed. No default - IDs differ between databases.}
        {--fallback-zone-id= : Required. Zone for businesses with no zone_id. No default - IDs differ between databases.}
        {--module-remap=14:16 : from:to module remap for staged rows in an inactive module}
        {--dry-run : Simulate only (default behavior)}
        {--apply : Actually write stores and items to the live database}';

    protected $description = 'Provision verified Black-owned businesses and upcharged products into live stores and items tables.';

    public function handle()
    {
        $marker = $this->option('batch-marker');
        $apply = $this->option('apply');
        $dryRun = !$apply;

        $this->info("=== Urban Goodz Phase 3 Provisioning ===");
        $this->info("Batch marker: {$marker}");
        $this->info($dryRun ? "MODE: DRY-RUN (no database writes)" : "MODE: APPLY (writing to live stores & items)");

        // These IDs were hardcoded (vendor 1, zone 2, module 14->16) from the
        // local database. Production IDs are not the same rows, so the
        // operator must name them, and they are checked before anything runs.
        $vendorId = (int) $this->option('vendor-id');
        $fallbackZoneId = (int) $this->option('fallback-zone-id');
        [$remapFrom, $remapTo] = array_map('intval', explode(':', (string) $this->option('module-remap')) + [0, 0]);

        $vendor = $vendorId > 0 ? DB::table('vendors')->where('id', $vendorId)->first() : null;
        if (! $vendor) {
            $this->error('Refusing: --vendor-id must name an existing vendor.');
            return self::FAILURE;
        }
        if ($fallbackZoneId <= 0 || ! DB::table('zones')->where('id', $fallbackZoneId)->exists()) {
            $this->error('Refusing: --fallback-zone-id must name an existing zone.');
            return self::FAILURE;
        }
        if ($remapTo > 0 && ! DB::table('modules')->where('id', $remapTo)->where('status', 1)->exists()) {
            $this->error("Refusing: remap target module {$remapTo} does not exist or is inactive.");
            return self::FAILURE;
        }
        $this->info("Owner vendor: #{$vendor->id} {$vendor->f_name} {$vendor->l_name} <{$vendor->email}> (owns "
            . DB::table('stores')->where('vendor_id', $vendorId)->count() . ' stores today)');

        $businesses = UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->get();
        if ($businesses->isEmpty()) {
            $this->error("No staged businesses found for batch marker {$marker}.");
            return self::FAILURE;
        }

        $this->info("Staged businesses to evaluate: " . $businesses->count());

        // Load existing live stores for deduplication
        $liveStores = DB::table('stores')->get();
        $liveStoreByNorm = [];
        $liveStoreByPhone = [];

        foreach ($liveStores as $st) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower($st->name));
            $phone = preg_replace('/\D/', '', (string) $st->phone);
            if ($norm !== '') {
                $liveStoreByNorm[$norm] = $st;
            }
            if ($phone !== '' && strlen($phone) >= 7) {
                $liveStoreByPhone[$phone] = $st;
            }
        }

        // Module fallback: Module 14 (inactive) -> Module 16 (active Beauty Supply)
        $categoryCache = [];
        $categories = DB::table('categories')->get();
        foreach ($categories as $cat) {
            $categoryCache[$cat->module_id][] = $cat;
        }

        $storesToCreate = [];
        $storesToLink = [];
        $allocatedPhones = [];

        foreach ($businesses as $b) {
            $bNorm = preg_replace('/[^a-z0-9]/', '', strtolower($b->name));
            $rawPhone = preg_replace('/\D/', '', (string) $b->phone);

            // Check if already in live stores
            $matchedStore = null;
            if (isset($liveStoreByNorm[$bNorm])) {
                $matchedStore = $liveStoreByNorm[$bNorm];
            } elseif ($rawPhone !== '' && isset($liveStoreByPhone[$rawPhone])) {
                $matchedStore = $liveStoreByPhone[$rawPhone];
            }

            // Remap a staged inactive module (14 -> 16 locally) to an active one
            $effectiveModuleId = (int) $b->module_id;
            if ($remapFrom > 0 && $remapTo > 0 && $effectiveModuleId === $remapFrom) {
                $effectiveModuleId = $remapTo;
            }

            if ($matchedStore) {
                $storesToLink[] = [
                    'business' => $b,
                    'store_id' => $matchedStore->id,
                    'store_name' => $matchedStore->name,
                    'module_id' => $matchedStore->module_id,
                ];
            } else {
                // Determine clean phone
                $phone = $b->phone;
                if (!$phone || strlen($rawPhone) < 10 || isset($allocatedPhones[$rawPhone]) || isset($liveStoreByPhone[$rawPhone])) {
                    $phone = '1' . str_pad($b->id, 9, '0');
                }
                $allocatedPhones[preg_replace('/\D/', '', $phone)] = true;

                $storesToCreate[] = [
                    'business' => $b,
                    'phone' => $phone,
                    'module_id' => $effectiveModuleId,
                ];
            }
        }

        $this->info("New stores to create: " . count($storesToCreate));
        $this->info("Existing live stores to link: " . count($storesToLink));

        foreach ($storesToLink as $stl) {
            $this->line("  [LINK EXISTING] Business ID {$stl['business']->id} ({$stl['business']->name}) -> Store ID {$stl['store_id']} ({$stl['store_name']})");
        }

        // Audit products
        // A product with a store_id was provisioned by an earlier run. Skip it,
        // or a re-run (e.g. after a partial failure) duplicates every item.
        $allProducts = UrbanGoodzSourcedProduct::whereIn('sourced_business_id', $businesses->pluck('id'))->get();
        $stagedProducts = $allProducts->whereNull('store_id');
        $this->info("Total staged products to provision: " . $stagedProducts->count()
            . ' (' . ($allProducts->count() - $stagedProducts->count()) . ' already provisioned, skipped)');

        if ($dryRun) {
            $this->warn("\nDRY-RUN completed successfully. Re-run with --apply to commit these stores and items.");
            return self::SUCCESS;
        }

        // === APPLY MODE ===
        DB::beginTransaction();
        try {
            $now = now();
            $createdStoreMap = []; // sourced_business_id => store_id

            // 1. Link existing stores
            foreach ($storesToLink as $stl) {
                $createdStoreMap[$stl['business']->id] = $stl['store_id'];
                $stl['business']->update([
                    'onboarding_status' => 'active',
                    'admin_review_status' => 'approved',
                    'validation_status' => 'valid',
                    'source_verified' => 1,
                    'updated_at' => $now,
                ]);
            }

            // 2. Create new stores
            foreach ($storesToCreate as $stc) {
                $b = $stc['business'];
                $moduleId = $stc['module_id'];
                $phone = $stc['phone'];

                $store = Store::create([
                    'name' => $b->name,
                    'phone' => $phone,
                    'email' => $b->email ?: (Str::slug($b->name) . '-' . $b->id . '@urbangoodzdelivery.com'),
                    'logo' => 'default.png',
                    'latitude' => $b->latitude ?: 29.7604,
                    'longitude' => $b->longitude ?: -95.3698,
                    'address' => $b->address ?: "{$b->city}, {$b->state}",
                    'footer_text' => null,
                    'minimum_order' => 10.00,
                    'comission' => 23.00,
                    'schedule_order' => 0,
                    'status' => 1, // Live in app
                    'vendor_id' => $vendorId, // holds unclaimed stores until claimed
                    'created_at' => $now,
                    'updated_at' => $now,
                    'free_delivery' => 0,
                    'cover_photo' => 'default.png',
                    'delivery' => 1,
                    'take_away' => 1,
                    'item_section' => 1,
                    'tax' => 0.00,
                    'zone_id' => $b->zone_id ?: $fallbackZoneId,
                    'reviews_section' => 1,
                    'active' => 1, // Activated
                    'minimum_shipping_charge' => 5.99,
                    'delivery_time' => '30-45 min',
                    'veg' => 1,
                    'non_veg' => 1,
                    'module_id' => $moduleId,
                    'slug' => Str::slug($b->name) . '-ug' . $b->id,
                    'is_public_sourced' => 1,
                    'is_claimed' => 0,
                    'is_partner' => 0,
                    'can_direct_checkout' => 1,
                    'requires_admin_quote' => 0,
                    'business_status' => 'sourced_listing',
                    'contract_status' => 'pending_claim',
                    'vendor_admin_status' => 'unclaimed',
                    'banking_status' => 'pending',
                    'subscription_status' => 'active',
                    'admin_approval_status' => 'approved',
                    'badge_status' => 'verified_black_owned',
                    'fulfillment_mode' => 'direct_vendor_order',
                ]);

                $createdStoreMap[$b->id] = $store->id;

                $b->update([
                    'onboarding_status' => 'active',
                    'admin_review_status' => 'approved',
                    'validation_status' => 'valid',
                    'source_verified' => 1,
                    'module_id' => $moduleId,
                    'updated_at' => $now,
                ]);

                $this->line("  Created Store ID {$store->id} for '{$b->name}' (Zone {$b->zone_id}, Module {$moduleId})");
            }

            // 3. Create items for each product
            $itemsCreated = 0;
            foreach ($stagedProducts as $p) {
                $storeId = $createdStoreMap[$p->sourced_business_id] ?? null;
                if (!$storeId) {
                    $this->warn("  Warning: No store ID found for product {$p->id} (business {$p->sourced_business_id})");
                    continue;
                }

                $store = DB::table('stores')->where('id', $storeId)->first();
                $moduleId = $store ? $store->module_id : ($p->module_id ?: 4);

                // Select appropriate category ID
                $catId = $p->category_id;
                if (!$catId || !DB::table('categories')->where('id', $catId)->where('module_id', $moduleId)->exists()) {
                    // Pick default category for module
                    $modCats = $categoryCache[$moduleId] ?? [];
                    $catId = !empty($modCats) ? $modCats[0]->id : 1;
                }

                $image = 'default.png';
                $description = $p->full_description ?: ($p->short_description ?: $p->name);

                $item = Item::create([
                    'name' => $p->name,
                    'description' => $description,
                    'image' => $image,
                    'category_id' => $catId,
                    'category_ids' => json_encode([['id' => (string) $catId, 'position' => 0]]),
                    'variations' => '[]',
                    'add_ons' => '[]',
                    'attributes' => '[]',
                    'choice_options' => '[]',
                    'price' => (float) $p->price,
                    'tax' => 0.00,
                    'tax_type' => 'percent',
                    'discount' => 0.00,
                    'discount_type' => 'amount',
                    'available_time_starts' => '08:00:00',
                    'available_time_ends' => '23:00:00',
                    'veg' => 0,
                    'status' => 1, // Live in app
                    'store_id' => $storeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'order_count' => 0,
                    // No customer has reviewed these yet; a seeded 5.0 would be a fake review.
                    'avg_rating' => 0,
                    'rating_count' => 0,
                    'module_id' => $moduleId,
                    'stock' => 100,
                    'images' => json_encode([$image]),
                    'food_variations' => '[]',
                    'slug' => Str::slug($p->name) . '-it' . $p->id,
                    'recommended' => 1,
                    'organic' => 0,
                    'is_approved' => 1,
                    'is_halal' => 0,
                    'age_restricted' => 0,
                ]);

                $p->update([
                    'store_id' => $storeId,
                    'category_id' => $catId,
                    'admin_review_status' => 'approved',
                    'validation_status' => 'valid',
                    'is_active' => 1,
                    'is_public' => 1,
                    'api_visible' => 1,
                    'shopper_visible' => 1,
                    'updated_at' => $now,
                ]);

                $itemsCreated++;
            }

            DB::commit();

            $this->info("\n=== PROVISIONING SUCCESSFUL ===");
            $this->info("New stores created: " . count($storesToCreate));
            $this->info("Existing stores linked: " . count($storesToLink));
            $this->info("Total live items created: " . $itemsCreated);
            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nProvisioning failed and transaction rolled back: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
