<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Mixing `@php(...)` with `@php ... @endphp` in one file can make Blade leave the block form as raw text
 * (the route page printed code and lost its variables, then 500'd). Compile every admin view and fail if any
 * directive survives in the output.
 */
class BladeCompilesCleanlyTest extends TestCase
{
    public function test_no_admin_view_leaves_a_raw_php_directive_in_its_compiled_output(): void
    {
        $compiler = app('blade.compiler');
        $bad = [];
        foreach (File::allFiles(resource_path('views/admin-views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $compiled = $compiler->compileString(File::get($file->getPathname()));
            // Only look outside quoted strings/HTML comments is overkill; a raw directive at the start of a line is the symptom.
            if (preg_match('/^\s*@(php|endphp)\b(?!\s*\()/m', $compiled) && ! str_contains($compiled, '@@php')) {
                $bad[] = str_replace(resource_path('views/'), '', $file->getPathname());
            }
        }
        $this->assertSame([], $bad, "These views leave a raw @php in their compiled output:\n".implode("\n", $bad));
    }
}
