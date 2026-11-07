<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every Blade view must compile to valid PHP.
 *
 * A single stray parenthesis turns a whole admin page into an HTTP 500 (this
 * is how the Sourced Businesses page was broken). Compiling and linting each
 * template catches that without needing data, a browser or a route.
 * Known-bad views live in tests/Fixtures/known-uncompilable-views.txt so the
 * list can only shrink.
 */
class BladeViewsCompileTest extends TestCase
{
    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $known = array_filter(array_map('trim', file(
            base_path('tests/Fixtures/known-uncompilable-views.txt'),
            FILE_IGNORE_NEW_LINES
        ) ?: []));

        $finder = (new Finder)->files()
            ->in([resource_path('views'), base_path('Modules')])
            ->name('*.blade.php')
            ->notPath('node_modules');

        $tmp = tempnam(sys_get_temp_dir(), 'blade');
        $broken = [];
        $count = 0;

        foreach ($finder as $file) {
            // Module view folders other than "resources/views" (assets etc.)
            if (str_contains($file->getPathname(), '/Modules/') && ! str_contains($file->getPathname(), '/resources/views/') && ! str_contains($file->getPathname(), '/Resources/views/')) {
                continue;
            }

            $count++;
            $relative = ltrim(str_replace(base_path(), '', $file->getPathname()), '/');

            try {
                $php = Blade::compileString((string) file_get_contents($file->getPathname()));
            } catch (\Throwable $e) {
                $broken[$relative] = 'compile: '.$e->getMessage();

                continue;
            }

            file_put_contents($tmp, $php);
            $output = [];
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1', $output, $code);
            if ($code !== 0) {
                $broken[$relative] = trim(preg_replace('#in /\S+ on line#', 'on line', implode(' ', $output)));
            }
        }
        @unlink($tmp);

        $this->assertGreaterThan(100, $count, 'View discovery found suspiciously few templates.');

        $new = array_diff_key($broken, array_flip($known));
        $this->assertSame([], $new, "Blade views that do not compile:\n".json_encode($new, JSON_PRETTY_PRINT));

        $fixed = array_diff($known, array_keys($broken));
        $this->assertSame([], array_values($fixed), 'These views now compile - remove them from tests/Fixtures/known-uncompilable-views.txt');
    }
}
