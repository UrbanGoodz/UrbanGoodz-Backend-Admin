<?php

namespace Tests\Feature;

use App\Services\Translations\RuntimeTranslationRepository;
use App\Traits\Processor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class RuntimeTranslationSourceSafetyTest extends TestCase
{
    public function test_normal_translation_calls_never_modify_source_language_files(): void
    {
        $repository = app(RuntimeTranslationRepository::class);
        $messageKey = 'ug_runtime_source_guard_'.bin2hex(random_bytes(4));
        $processorKey = 'ug_processor_source_guard_'.bin2hex(random_bytes(4));
        $before = $this->sourceHashes();

        try {
            app('translator')->setLoaded([]);
            $result = translate($messageKey);
            $processor = new class {
                use Processor;
            };
            $processorResult = $processor->translate($processorKey);

            $this->assertSame(ucfirst(str_replace('_', ' ', $messageKey)), $result);
            $this->assertSame(ucfirst(str_replace('_', ' ', $processorKey)), $processorResult);
            $this->assertSame($result, $repository->all('en', 'messages')[$messageKey] ?? null);
            $this->assertSame($processorResult, $repository->all('en', 'lang')[$processorKey] ?? null);
            $this->assertSame($before, $this->sourceHashes());
        } finally {
            $repository->forget('en', 'messages', $messageKey);
            $repository->forget('en', 'lang', $processorKey);
            app('translator')->setLoaded([]);
        }
    }

    public function test_runtime_catalog_is_merged_by_the_laravel_translation_loader(): void
    {
        $repository = app(RuntimeTranslationRepository::class);
        $locale = 'zz-runtime-test';
        $key = 'loader_contract';

        try {
            $repository->put($locale, 'messages', $key, 'Storage-backed translation');
            app('translator')->setLoaded([]);

            $this->assertSame(
                'Storage-backed translation',
                app('translator')->get("messages.{$key}", [], $locale, false)
            );
        } finally {
            $repository->deleteLocale($locale);
            app('translator')->setLoaded([]);
        }
    }

    public function test_import_merges_without_rewriting_static_catalogs(): void
    {
        $repository = app(RuntimeTranslationRepository::class);
        $locale = 'zz-import-test';
        $before = $this->sourceHashes();

        try {
            $count = $repository->import($locale, 'messages', [
                'first' => 'First runtime value',
                'second' => 'Second runtime value',
            ]);

            $this->assertSame(2, $count);
            $this->assertSame('First runtime value', $repository->all($locale)['first']);
            $this->assertSame($before, $this->sourceHashes());
        } finally {
            $repository->deleteLocale($locale);
        }
    }

    private function sourceHashes(): array
    {
        $root = resource_path('lang');
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $hashes[str_replace('\\', '/', $file->getPathname())] = hash_file('sha256', $file->getPathname());
            }
        }

        ksort($hashes);

        return $hashes;
    }
}
