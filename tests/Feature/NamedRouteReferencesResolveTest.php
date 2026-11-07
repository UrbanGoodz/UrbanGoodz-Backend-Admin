<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every literal route('name') / redirect()->route('name') used by a view or
 * controller must exist. A missing name throws RouteNotFoundException, which
 * shows up as a dead sidebar link, a broken button or an HTTP 500.
 *
 * Only fully literal names are checked (route('a.b.c'), single or double
 * quotes). Dynamic names are ignored. Known-dead references are listed in
 * tests/Fixtures/known-missing-route-names.txt ("name | file") so the list can
 * only shrink.
 */
class NamedRouteReferencesResolveTest extends TestCase
{
    public function test_every_literal_route_name_is_defined(): void
    {
        $defined = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->getName()) {
                $defined[$route->getName()] = true;
            }
        }
        $this->assertGreaterThan(1000, count($defined));

        $known = array_filter(array_map('trim', file(
            base_path('tests/Fixtures/known-missing-route-names.txt'),
            FILE_IGNORE_NEW_LINES
        ) ?: []));

        $finder = (new Finder)->files()->in([
            resource_path('views'), app_path(), base_path('Modules'),
        ])->name('*.php')->notPath('node_modules')->notPath('vendor');

        $missing = [];
        foreach ($finder as $file) {
            $path = ltrim(str_replace(base_path(), '', $file->getPathname()), '/');
            $src = (string) file_get_contents($file->getPathname());

            // Ignore commented-out code: //, # lines, /* */ blocks and {{-- --}}.
            $src = preg_replace(['#/\*.*?\*/#s', '/\{\{--.*?--\}\}/s', '#^\s*(//|\#).*$#m'], '', $src);

            // Bare route(), to_route(), redirect()->route(), Redirect::route().
            // `$request->route('id')` reads a route *parameter*, so it is not matched.
            preg_match_all(
                '/(?:redirect\(\)->|Redirect::|(?<![\w>:$]))(?:to_)?route\(\s*([\'"])([A-Za-z0-9_.\-]+)\1\s*[,)]/',
                $src,
                $all
            );
            foreach (array_unique($all[2]) as $name) {
                if (! isset($defined[$name])) {
                    $missing[$name.' | '.$path] = true;
                }
            }
        }

        $missing = array_keys($missing);
        sort($missing);

        $new = array_values(array_diff($missing, $known));
        $this->assertSame([], $new, "Route names referenced but not defined:\n".implode("\n", $new));

        $fixed = array_values(array_diff($known, $missing));
        $this->assertSame([], $fixed, "These references now resolve - remove them from tests/Fixtures/known-missing-route-names.txt:\n".implode("\n", $fixed));
    }
}
