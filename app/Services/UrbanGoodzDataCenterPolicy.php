<?php

namespace App\Services;

use Illuminate\Support\Str;

class UrbanGoodzDataCenterPolicy
{
    public const CLASSIFICATIONS = ['production', 'demo', 'test', 'duplicate'];

    public const CATEGORY_PRIORITIES = [
        'shopping and retail' => 10,
        'retail / shopping' => 10,
        'boutiques and fashion' => 20,
        'beauty supply' => 30,
        'beauty supply / hair providerz' => 30,
        'grocery' => 40,
        'grocery / markets' => 40,
        'home-based businesses' => 50,
        'home-based businessz' => 50,
        'pharmacy and health' => 60,
        'pharmacy / health' => 60,
        'services' => 70,
        'professional services' => 70,
        'restaurants' => 90,
    ];

    public function priorityFor(string $category): int
    {
        return self::CATEGORY_PRIORITIES[strtolower(trim($category))] ?? 80;
    }

    public function fingerprint(array $candidate): string
    {
        $website = strtolower((string) ($candidate['website'] ?? ''));
        $host = parse_url($website, PHP_URL_HOST) ?: '';

        return hash('sha256', implode('|', [
            Str::slug((string) ($candidate['name'] ?? '')),
            Str::slug((string) ($candidate['city'] ?? '')),
            strtoupper(trim((string) ($candidate['state'] ?? ''))),
            strtolower((string) $host),
        ]));
    }

    public function classify(array $candidate, array $existingFingerprints = []): string
    {
        $explicit = strtolower(trim((string) ($candidate['record_classification'] ?? '')));
        if (in_array($explicit, self::CLASSIFICATIONS, true) && $explicit !== 'production') {
            return $explicit;
        }

        $sourceText = strtolower(implode(' ', array_filter([
            (string) ($candidate['source_name'] ?? ''),
            (string) ($candidate['created_by_source'] ?? ''),
            (string) ($candidate['name'] ?? ''),
            (string) ($candidate['email'] ?? ''),
        ])));

        if (preg_match('/(^|[^a-z])(test|qa|staging|sandbox)([^a-z]|$)/', $sourceText)) {
            return 'test';
        }

        if (preg_match('/(^|[^a-z])(demo|sample|fixture|placeholder)([^a-z]|$)/', $sourceText)) {
            return 'demo';
        }

        if (in_array($this->fingerprint($candidate), $existingFingerprints, true)) {
            return 'duplicate';
        }

        return 'production';
    }

    public function validateBusiness(array $candidate): array
    {
        $errors = [];

        foreach (['name', 'city', 'state', 'category'] as $field) {
            if (trim((string) ($candidate[$field] ?? '')) === '') {
                $errors[] = "{$field} is required";
            }
        }

        $sourceUrls = array_values(array_filter((array) ($candidate['source_urls'] ?? [])));
        if ($sourceUrls === []) {
            $errors[] = 'at least one source URL is required';
        }
        foreach ($sourceUrls as $url) {
            if (!$this->isHttpUrl((string) $url)) {
                $errors[] = 'source URL must use http or https';
                break;
            }
        }

        if (isset($candidate['data_confidence_score'])
            && ((int) $candidate['data_confidence_score'] < 0 || (int) $candidate['data_confidence_score'] > 100)) {
            $errors[] = 'data confidence score must be between 0 and 100';
        }

        return array_values(array_unique($errors));
    }

    public function validateProduct(array $product): array
    {
        $errors = [];
        if (trim((string) ($product['name'] ?? '')) === '') {
            $errors[] = 'product name is required';
        }
        if (isset($product['price']) && (!is_numeric($product['price']) || (float) $product['price'] < 0)) {
            $errors[] = 'product price must be a non-negative number';
        }
        if (($product['price_type'] ?? 'unknown') === 'fixed' && !isset($product['price'])) {
            $errors[] = 'fixed-price product requires a price';
        }

        return $errors;
    }

    public function exposureFailures(array $record, bool $shopper): array
    {
        $failures = [];

        if (($record['admin_review_status'] ?? null) !== 'approved') {
            $failures[] = 'admin review is not approved';
        }
        if (($record['validation_status'] ?? null) !== 'valid') {
            $failures[] = 'validation has not passed';
        }
        if (($record['record_classification'] ?? null) !== 'production') {
            $failures[] = 'record is not classified as production';
        }
        if (empty($record['source_verified'])) {
            $failures[] = 'source has not been verified';
        }
        if (!empty($record['duplicate_of_business_id'])) {
            $failures[] = 'record is classified as a duplicate';
        }
        if ($shopper && empty($record['approved_image_count'])) {
            $failures[] = 'shopper visibility requires an approved image';
        }

        return $failures;
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
