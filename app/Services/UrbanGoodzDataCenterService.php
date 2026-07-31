<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Module;
use App\Models\Store;
use App\Models\UrbanGoodzDataCenterRevision;
use App\Models\UrbanGoodzImportBatch;
use App\Models\UrbanGoodzSourcedBusiness;
use App\Models\UrbanGoodzSourcedImage;
use App\Models\UrbanGoodzSourcedProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UrbanGoodzDataCenterService
{
    public function __construct(private readonly UrbanGoodzDataCenterPolicy $policy)
    {
    }

    public function stage(array $manifest, ?int $adminId = null): UrbanGoodzImportBatch
    {
        $category = trim((string) ($manifest['category'] ?? ''));
        $location = (array) ($manifest['location'] ?? []);

        $batch = UrbanGoodzImportBatch::create([
            'city' => $location['city'] ?? null,
            'state' => $location['state'] ?? null,
            'category' => $category,
            'module' => $manifest['module'] ?? null,
            'queue_type' => $manifest['queue_type'] ?? 'import',
            'priority' => $this->policy->priorityFor($category),
            'source_query' => $manifest['source_query'] ?? null,
            'source_platforms' => array_values((array) ($manifest['source_platforms'] ?? [])),
            'source_payload' => $manifest,
            'status' => 'queued',
            'max_attempts' => min(5, max(1, (int) ($manifest['max_attempts'] ?? 3))),
            'admin_id' => $adminId,
        ]);

        return $this->process($batch);
    }

    public function retry(UrbanGoodzImportBatch $batch): UrbanGoodzImportBatch
    {
        if (!in_array($batch->status, ['failed', 'partially_failed'], true)) {
            throw new RuntimeException('Only failed or partially failed batches can be retried.');
        }
        if ($batch->attempt_count >= $batch->max_attempts) {
            throw new RuntimeException('Retry limit reached for this import batch.');
        }
        if ($batch->retry_after && $batch->retry_after->isFuture()) {
            throw new RuntimeException('Retry is not available until the bounded backoff expires.');
        }

        return $this->process($batch);
    }

    public function preview(UrbanGoodzImportBatch $batch): array
    {
        $businesses = $batch->sourcedBusinesses()
            ->with(['products', 'images'])
            ->orderBy('id')
            ->get();

        return [
            'batch_id' => $batch->id,
            'queue_type' => $batch->queue_type,
            'status' => $batch->status,
            'location' => [
                'city' => $batch->city,
                'state' => $batch->state,
            ],
            'category' => $batch->category,
            'priority' => $batch->priority,
            'classification_summary' => $batch->classification_summary ?? [],
            'validation_summary' => $batch->validation_summary ?? [],
            'businesses' => $businesses->map(fn (UrbanGoodzSourcedBusiness $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'classification' => $business->record_classification,
                'duplicate_of_business_id' => $business->duplicate_of_business_id,
                'validation_status' => $business->validation_status,
                'validation_errors' => $business->validation_errors ?? [],
                'review_status' => $business->admin_review_status,
                'source_verified' => $business->source_verified,
                'api_visible' => $business->api_visible,
                'shopper_visible' => $business->shopper_visible,
                'catalog' => [
                    'products' => $business->products->count(),
                    'approved_products' => $business->products->where('admin_review_status', 'approved')->count(),
                    'images' => $business->images->count(),
                    'approved_images' => $business->images->where('review_status', 'approved')->count(),
                ],
            ])->values()->all(),
        ];
    }

    public function reviewBusiness(
        UrbanGoodzSourcedBusiness $business,
        string $status,
        bool $sourceVerified,
        array $categoryIds,
        ?int $adminId
    ): UrbanGoodzSourcedBusiness {
        if (!in_array($status, ['pending', 'approved', 'rejected', 'merge_required'], true)) {
            throw new RuntimeException('Unsupported business review status.');
        }

        $categoryIds = $this->validCategoryIds($business->module_id, $categoryIds);
        $errors = array_values(array_filter((array) $business->validation_errors, fn ($error) => $error !== 'category mapping is required'));
        if ($categoryIds === []) {
            $errors[] = 'category mapping is required';
        }

        if ($status === 'approved') {
            $candidate = $business->toArray();
            $candidate['source_verified'] = $sourceVerified;
            $candidate['approved_image_count'] = 1;
            $candidate['validation_status'] = $errors === [] ? 'valid' : 'invalid';
            $failures = $this->policy->exposureFailures(array_merge($candidate, [
                'admin_review_status' => 'approved',
            ]), false);
            if ($failures !== []) {
                throw new RuntimeException('Approval blocked: ' . implode('; ', $failures));
            }
        }

        $business->update([
            'category_ids' => $categoryIds,
            'validation_status' => $errors === [] ? 'valid' : 'invalid',
            'validation_errors' => $errors,
            'source_verified' => $sourceVerified,
            'admin_review_status' => $status,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'api_visible' => false,
            'shopper_visible' => false,
        ]);

        return $business->fresh();
    }

    public function reviewProduct(
        UrbanGoodzSourcedProduct $product,
        string $status,
        ?int $categoryId = null
    ): UrbanGoodzSourcedProduct
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new RuntimeException('Unsupported product review status.');
        }
        if ($status === 'approved' && $product->validation_status !== 'valid') {
            throw new RuntimeException('Invalid products cannot be approved.');
        }
        $business = $product->sourcedBusiness;
        if ($status === 'approved'
            && (!$business || !$categoryId || !in_array($categoryId, (array) $business->category_ids, true))) {
            throw new RuntimeException('Product approval requires a category approved for its sourced business.');
        }

        $product->update([
            'category_id' => $categoryId ?? $product->category_id,
            'admin_review_status' => $status,
            'requires_admin_review' => $status !== 'approved',
            'api_visible' => false,
            'shopper_visible' => false,
        ]);

        return $product->fresh();
    }

    public function reviewImage(UrbanGoodzSourcedImage $image, string $status, string $rightsStatus): UrbanGoodzSourcedImage
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new RuntimeException('Unsupported image review status.');
        }
        $allowedRights = ['vendor_owned', 'public_official', 'customer_uploaded', 'generated_placeholder', 'unknown_review_required'];
        if (!in_array($rightsStatus, $allowedRights, true)) {
            throw new RuntimeException('Unsupported image rights status.');
        }
        if ($status === 'approved' && $rightsStatus === 'unknown_review_required') {
            throw new RuntimeException('Image rights must be resolved before approval.');
        }

        $image->update([
            'review_status' => $status,
            'rights_status' => $rightsStatus,
            'api_visible' => false,
            'shopper_visible' => false,
        ]);

        return $image->fresh();
    }

    public function approveBatch(UrbanGoodzImportBatch $batch, ?int $adminId): UrbanGoodzImportBatch
    {
        $production = $batch->sourcedBusinesses()->where('record_classification', 'production')->get();
        if ($production->isEmpty()) {
            throw new RuntimeException('Batch has no production records eligible for approval.');
        }

        $blocked = $production->filter(fn (UrbanGoodzSourcedBusiness $business) =>
            $business->admin_review_status !== 'approved'
            || $business->validation_status !== 'valid'
            || !$business->source_verified
        );
        if ($blocked->isNotEmpty()) {
            throw new RuntimeException('Every production record must pass validation, source verification, and admin review.');
        }

        $this->snapshot($batch, 'approve', $adminId);
        $batch->update([
            'status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);

        return $batch->fresh();
    }

    public function setVisibility(
        UrbanGoodzImportBatch $batch,
        bool $apiVisible,
        bool $shopperVisible,
        ?int $adminId
    ): UrbanGoodzImportBatch {
        if ($batch->status !== 'approved') {
            throw new RuntimeException('Batch approval is required before changing visibility.');
        }
        if ($shopperVisible && !$apiVisible) {
            throw new RuntimeException('Shopper visibility requires API visibility.');
        }

        $businesses = $batch->sourcedBusinesses()->with(['products', 'images'])->get();
        foreach ($businesses as $business) {
            if ($business->record_classification !== 'production') {
                continue;
            }

            $failures = $this->policy->exposureFailures(array_merge($business->toArray(), [
                'approved_image_count' => $business->images->where('review_status', 'approved')->count(),
            ]), $shopperVisible);
            if (($apiVisible || $shopperVisible) && $failures !== []) {
                throw new RuntimeException("Visibility blocked for {$business->name}: " . implode('; ', $failures));
            }

            if ($shopperVisible) {
                $approvedProducts = $business->products
                    ->where('admin_review_status', 'approved')
                    ->where('validation_status', 'valid');
                if ($approvedProducts->isEmpty()) {
                    throw new RuntimeException("Shopper visibility blocked for {$business->name}: no approved catalog product.");
                }
            }
        }

        return DB::transaction(function () use ($batch, $businesses, $apiVisible, $shopperVisible, $adminId) {
            $this->snapshot($batch, 'visibility', $adminId);

            foreach ($businesses as $business) {
                $eligible = $business->record_classification === 'production';
                $businessApiVisible = $eligible && $apiVisible;
                $businessShopperVisible = $eligible && $shopperVisible;

                $business->update([
                    'api_visible' => $businessApiVisible,
                    'shopper_visible' => $businessShopperVisible,
                ]);
                $business->products()
                    ->where('admin_review_status', 'approved')
                    ->where('validation_status', 'valid')
                    ->update([
                        'api_visible' => $businessApiVisible,
                        'shopper_visible' => $businessShopperVisible,
                    ]);
                $business->images()
                    ->where('review_status', 'approved')
                    ->update([
                        'api_visible' => $businessApiVisible,
                        'shopper_visible' => $businessShopperVisible,
                    ]);
                $approvedProductIds = $business->products()
                    ->where('admin_review_status', 'approved')
                    ->where('validation_status', 'valid')
                    ->pluck('id');
                UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)
                    ->where('entity_type', 'product')
                    ->whereIn('entity_id', $approvedProductIds)
                    ->where('review_status', 'approved')
                    ->update([
                        'api_visible' => $businessApiVisible,
                        'shopper_visible' => $businessShopperVisible,
                    ]);
            }

            return $batch->fresh();
        });
    }

    public function rollback(UrbanGoodzImportBatch $batch, string $reason, ?int $adminId): UrbanGoodzImportBatch
    {
        $revision = $batch->revisions()->latest('id')->first();
        if (!$revision) {
            throw new RuntimeException('No data center revision is available to roll back.');
        }

        DB::transaction(function () use ($batch, $revision, $reason, $adminId) {
            foreach ((array) ($revision->snapshot['businesses'] ?? []) as $row) {
                UrbanGoodzSourcedBusiness::whereKey($row['id'])->update($row['state']);
            }
            foreach ((array) ($revision->snapshot['products'] ?? []) as $row) {
                UrbanGoodzSourcedProduct::whereKey($row['id'])->update($row['state']);
            }
            foreach ((array) ($revision->snapshot['images'] ?? []) as $row) {
                UrbanGoodzSourcedImage::whereKey($row['id'])->update($row['state']);
            }

            $batch->update(array_merge((array) ($revision->snapshot['batch'] ?? []), [
                'rolled_back_by' => $adminId,
                'rolled_back_at' => now(),
                'rollback_reason' => $reason,
            ]));
        });

        return $batch->fresh();
    }

    private function process(UrbanGoodzImportBatch $batch): UrbanGoodzImportBatch
    {
        $batch->increment('attempt_count');
        $batch->update([
            'status' => 'validating',
            'failure_code' => null,
            'failure_message' => null,
            'retry_after' => null,
        ]);

        try {
            $manifest = (array) $batch->source_payload;
            $candidates = array_values((array) ($manifest['businesses'] ?? []));
            if ($candidates === []) {
                throw new RuntimeException('The source payload contains no business records.');
            }

            $summary = DB::transaction(function () use ($batch, $manifest, $candidates) {
                $classifications = array_fill_keys(UrbanGoodzDataCenterPolicy::CLASSIFICATIONS, 0);
                $existingRows = $batch->sourcedBusinesses()->get();
                foreach ($existingRows->countBy('record_classification') as $classification => $count) {
                    if (array_key_exists($classification, $classifications)) {
                        $classifications[$classification] = $count;
                    }
                }
                $valid = $existingRows->where('validation_status', 'valid')->count();
                $invalid = $existingRows->where('validation_status', 'invalid')->count();
                $created = $existingRows->count();

                foreach ($candidates as $candidate) {
                    $candidate = (array) $candidate;
                    $candidate['city'] = $candidate['city'] ?? $batch->city;
                    $candidate['state'] = $candidate['state'] ?? $batch->state;
                    $candidate['category'] = $candidate['category'] ?? $batch->category;

                    if (trim((string) ($candidate['name'] ?? '')) === '') {
                        $invalid++;
                        continue;
                    }
                    if ($batch->sourcedBusinesses()
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim($candidate['name']))])
                        ->whereRaw('LOWER(city) = ?', [strtolower(trim((string) $candidate['city']))])
                        ->exists()) {
                        continue;
                    }

                    $duplicate = UrbanGoodzSourcedBusiness::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim($candidate['name']))])
                        ->whereRaw('LOWER(city) = ?', [strtolower(trim((string) $candidate['city']))])
                        ->where(function ($query) use ($batch) {
                            $query->whereNull('import_batch_id')
                                ->orWhere('import_batch_id', '!=', $batch->id);
                        })
                        ->first();
                    $activeDuplicate = Store::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim($candidate['name']))])
                        ->whereRaw('LOWER(address) LIKE ?', ['%' . strtolower(trim((string) $candidate['city'])) . '%'])
                        ->exists();

                    $fingerprints = ($duplicate || $activeDuplicate) ? [$this->policy->fingerprint($candidate)] : [];
                    $classification = $this->policy->classify($candidate, $fingerprints);
                    $classifications[$classification]++;

                    $errors = $this->policy->validateBusiness($candidate);
                    $module = $this->resolveModule($candidate, $manifest);
                    if (!$module) {
                        $errors[] = 'module mapping is required';
                    }
                    $categoryIds = $this->validCategoryIds($module?->id, (array) ($candidate['category_ids'] ?? []));
                    if ($categoryIds === []) {
                        $errors[] = 'category mapping is required';
                    }
                    if ($classification !== 'production') {
                        $errors[] = "record classified as {$classification}";
                    }
                    $errors = array_values(array_unique($errors));
                    $validationStatus = $errors === [] ? 'valid' : 'invalid';
                    $validationStatus === 'valid' ? $valid++ : $invalid++;

                    $business = UrbanGoodzSourcedBusiness::create([
                        'import_batch_id' => $batch->id,
                        'name' => $candidate['name'],
                        'display_name' => $candidate['display_name'] ?? $candidate['name'],
                        'description' => $candidate['description'] ?? null,
                        'short_description' => $candidate['short_description'] ?? null,
                        'business_type' => $candidate['business_type'] ?? Str::slug((string) $candidate['category']),
                        'module_id' => $module?->id,
                        'module_name' => $module?->module_name,
                        'category_ids' => $categoryIds,
                        'tags' => array_values((array) ($candidate['tags'] ?? [])),
                        'phone' => $candidate['phone'] ?? null,
                        'email' => $candidate['email'] ?? null,
                        'website' => $candidate['website'] ?? null,
                        'social_links' => (array) ($candidate['social_links'] ?? []),
                        'address' => $candidate['address'] ?? null,
                        'city' => $candidate['city'],
                        'state' => $candidate['state'],
                        'country_code' => $candidate['country_code'] ?? 'US',
                        'zip' => $candidate['zip'] ?? null,
                        'latitude' => $candidate['latitude'] ?? null,
                        'longitude' => $candidate['longitude'] ?? null,
                        'zone_id' => $candidate['zone_id'] ?? null,
                        'zone_name' => $candidate['zone_name'] ?? null,
                        'fulfillment_modes' => array_values((array) ($candidate['fulfillment_modes'] ?? [])),
                        'onboarding_status' => 'pending_review',
                        'source_status' => 'admin_data_center',
                        'source_urls' => array_values((array) ($candidate['source_urls'] ?? [])),
                        'data_confidence_score' => (int) ($candidate['data_confidence_score'] ?? 0),
                        'admin_review_status' => $classification === 'duplicate' ? 'merge_required' : 'pending',
                        'created_by_source' => $manifest['source_name'] ?? "data_center_batch_{$batch->id}",
                        'record_classification' => $classification,
                        'duplicate_of_business_id' => $duplicate?->id,
                        'validation_status' => $validationStatus,
                        'validation_errors' => $errors,
                        'source_verified' => false,
                        'api_visible' => false,
                        'shopper_visible' => false,
                    ]);

                    foreach ((array) ($candidate['products'] ?? []) as $productData) {
                        $productData = (array) $productData;
                        $productErrors = $this->policy->validateProduct($productData);
                        $product = UrbanGoodzSourcedProduct::create([
                            'sourced_business_id' => $business->id,
                            'import_batch_id' => $batch->id,
                            'module_id' => $module?->id,
                            'category_id' => $categoryIds[0] ?? null,
                            'name' => $productData['name'] ?? 'Invalid product pending review',
                            'short_description' => $productData['short_description'] ?? null,
                            'full_description' => $productData['description'] ?? null,
                            'price' => $productData['price'] ?? null,
                            'price_type' => $productData['price_type'] ?? 'unknown',
                            'currency' => $productData['currency'] ?? 'USD',
                            'stock_status' => $productData['stock_status'] ?? 'unknown',
                            'item_type' => $productData['item_type'] ?? 'product',
                            'source_url' => $productData['source_url'] ?? null,
                            'source_type' => $productData['source_type'] ?? 'catalog',
                            'source_confidence' => (int) ($productData['source_confidence'] ?? 0),
                            'requires_quote' => (bool) ($productData['requires_quote'] ?? false),
                            'requires_admin_review' => true,
                            'admin_review_status' => 'pending',
                            'validation_status' => $productErrors === [] ? 'valid' : 'invalid',
                            'validation_errors' => $productErrors,
                            'is_active' => false,
                            'is_public' => false,
                            'api_visible' => false,
                            'shopper_visible' => false,
                        ]);

                        foreach ((array) ($productData['images'] ?? []) as $productImageData) {
                            $productImageData = is_string($productImageData)
                                ? ['image_url' => $productImageData]
                                : (array) $productImageData;
                            if (empty($productImageData['image_url'])) {
                                continue;
                            }
                            UrbanGoodzSourcedImage::create([
                                'import_batch_id' => $batch->id,
                                'entity_type' => 'product',
                                'entity_id' => $product->id,
                                'image_role' => 'product',
                                'image_url' => $productImageData['image_url'],
                                'source_url' => $productImageData['source_url'] ?? null,
                                'source_platform' => $productImageData['source_platform'] ?? null,
                                'alt_text' => $productImageData['alt_text'] ?? null,
                                'rights_status' => $productImageData['rights_status'] ?? 'unknown_review_required',
                                'review_status' => 'pending',
                                'api_visible' => false,
                                'shopper_visible' => false,
                            ]);
                        }
                    }

                    foreach ((array) ($candidate['images'] ?? []) as $imageData) {
                        $imageData = (array) $imageData;
                        if (empty($imageData['image_url'])) {
                            continue;
                        }
                        UrbanGoodzSourcedImage::create([
                            'import_batch_id' => $batch->id,
                            'entity_type' => 'business',
                            'entity_id' => $business->id,
                            'image_role' => $this->imageRole($imageData['image_role'] ?? 'gallery'),
                            'image_url' => $imageData['image_url'],
                            'source_url' => $imageData['source_url'] ?? null,
                            'source_platform' => $imageData['source_platform'] ?? null,
                            'alt_text' => $imageData['alt_text'] ?? null,
                            'rights_status' => $imageData['rights_status'] ?? 'unknown_review_required',
                            'review_status' => 'pending',
                            'api_visible' => false,
                            'shopper_visible' => false,
                        ]);
                    }

                    $created++;
                }

                return compact('classifications', 'valid', 'invalid', 'created');
            });

            $status = $summary['created'] === 0
                ? 'failed'
                : ($summary['invalid'] > 0 ? 'partially_failed' : 'review_required');
            $preview = [
                'locations' => array_values(array_unique(array_filter([
                    trim("{$batch->city}, {$batch->state}", ', '),
                ]))),
                'businesses' => $summary['created'],
                'catalog_products' => $batch->sourcedProducts()->count(),
                'logos' => UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)
                    ->where('image_role', 'logo')->count(),
                'covers' => UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)
                    ->where('image_role', 'cover')->count(),
                'gallery_images' => UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)
                    ->where('image_role', 'gallery')->count(),
                'product_images' => UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)
                    ->where('entity_type', 'product')
                    ->count(),
                'live_records_created' => 0,
            ];

            $batch->update([
                'total_found' => count($candidates),
                'total_imported' => $summary['created'],
                'total_needs_review' => $summary['created'],
                'total_failed' => $summary['invalid'],
                'classification_summary' => $summary['classifications'],
                'validation_summary' => [
                    'valid' => $summary['valid'],
                    'invalid' => $summary['invalid'],
                ],
                'preview_summary' => $preview,
                'status' => $status,
                'completed_at' => now(),
                'failure_code' => $status === 'failed' ? 'NO_STAGEABLE_RECORDS' : null,
                'failure_message' => $status === 'failed' ? 'No records could be staged for review.' : null,
                'retry_after' => in_array($status, ['failed', 'partially_failed'], true)
                    ? now()->addMinutes(min(15, 2 ** $batch->attempt_count))
                    : null,
            ]);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'failure_code' => 'PROCESSING_FAILED',
                'failure_message' => $this->sanitizedFailure($exception),
                'retry_after' => now()->addMinutes(min(15, 2 ** $batch->attempt_count)),
            ]);
        }

        return $batch->fresh();
    }

    private function resolveModule(array $candidate, array $manifest): ?Module
    {
        $moduleId = $candidate['module_id'] ?? null;
        if ($moduleId) {
            return Module::find($moduleId);
        }

        $name = $candidate['module'] ?? $candidate['module_name'] ?? $manifest['module'] ?? null;

        return $name ? Module::where('module_name', $name)->first() : null;
    }

    private function validCategoryIds(?int $moduleId, array $categoryIds): array
    {
        if (!$moduleId) {
            return [];
        }

        return Category::query()
            ->where('module_id', $moduleId)
            ->whereIn('id', array_values(array_unique(array_map('intval', $categoryIds))))
            ->where('id', '>', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function snapshot(UrbanGoodzImportBatch $batch, string $action, ?int $adminId): void
    {
        $businesses = $batch->sourcedBusinesses()->get();
        $businessIds = $businesses->pluck('id');

        UrbanGoodzDataCenterRevision::create([
            'import_batch_id' => $batch->id,
            'action' => $action,
            'admin_id' => $adminId,
            'snapshot' => [
                'batch' => $batch->only([
                    'status',
                    'approved_by',
                    'approved_at',
                ]),
                'businesses' => $businesses->map(fn ($row) => [
                    'id' => $row->id,
                    'state' => $row->only([
                        'admin_review_status',
                        'source_verified',
                        'api_visible',
                        'shopper_visible',
                        'reviewed_by',
                        'reviewed_at',
                    ]),
                ])->all(),
                'products' => UrbanGoodzSourcedProduct::whereIn('sourced_business_id', $businessIds)->get()
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'state' => $row->only([
                            'admin_review_status',
                            'requires_admin_review',
                            'api_visible',
                            'shopper_visible',
                        ]),
                    ])->all(),
                'images' => UrbanGoodzSourcedImage::where('import_batch_id', $batch->id)->get()
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'state' => $row->only([
                            'review_status',
                            'rights_status',
                            'api_visible',
                            'shopper_visible',
                        ]),
                    ])->all(),
            ],
        ]);
    }

    private function sanitizedFailure(Throwable $exception): string
    {
        $message = preg_replace(
            '/\\b(key|token|secret|password)\\s*=?\\s*[^\\s,;]+/i',
            '$1=[redacted]',
            $exception->getMessage()
        );

        return Str::limit((string) $message, 500, '');
    }

    private function imageRole(string $role): string
    {
        return in_array($role, ['logo', 'cover', 'gallery'], true) ? $role : 'gallery';
    }
}
