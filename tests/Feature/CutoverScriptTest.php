<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The cutover script must swap safely, never carry path-poisoned caches, and roll back without nesting. */
class CutoverScriptTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/ug-cutover-'.uniqid();
        mkdir($this->root, 0777, true);
        // A stand-in "php": artisan config:cache writes the CURRENT directory into bootstrap/cache/config.php,
        // which is exactly how Laravel bakes absolute paths. FAIL_CACHE=1 makes it fail.
        file_put_contents($this->root.'/php', <<<'SH'
#!/usr/bin/env bash
cmd="$2"
case "$cmd" in
  config:cache) [ "${FAIL_CACHE:-0}" = "1" ] && exit 1; mkdir -p bootstrap/cache; echo "<?php return ['root' => '$(pwd)'];" > bootstrap/cache/config.php ;;
  route:cache|view:cache) exit 0 ;;
  down) touch storage/framework/down ;;
  up) rm -f storage/framework/down ;;
esac
exit 0
SH);
        chmod($this->root.'/php', 0755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function app(string $name, string $tag, bool $poisoned = false): string
    {
        $d = "{$this->root}/{$name}";
        foreach (['storage/framework/views', 'bootstrap/cache', 'vendor'] as $sub) {
            mkdir("{$d}/{$sub}", 0777, true);
        }
        file_put_contents("{$d}/artisan", '');
        file_put_contents("{$d}/vendor/autoload.php", '');
        file_put_contents("{$d}/.env", 'APP_ENV=production');
        file_put_contents("{$d}/TAG", $tag);
        if ($poisoned) {
            file_put_contents("{$d}/bootstrap/cache/config.php", "<?php return ['root' => '{$d}'];");
            file_put_contents("{$d}/storage/framework/views/stale.php", '<?php // compiled with old paths');
        }

        return $d;
    }

    private function cutover(string $live, string $rel, string $prev, array $env = [], string $url = ''): array
    {
        $envs = '';
        foreach ($env as $k => $v) {
            $envs .= "{$k}=".escapeshellarg($v).' ';
        }
        exec($envs.'bash '.escapeshellarg(base_path('scripts/ops/cutover.sh')).' '.escapeshellarg($live).' '.escapeshellarg($rel).' '.escapeshellarg($prev).' '.escapeshellarg($url).' '.escapeshellarg($this->root.'/php').' 2>&1', $out, $code);

        return [$code, implode("\n", $out)];
    }

    public function test_the_happy_path_swaps_and_builds_caches_in_the_final_location(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new', poisoned: true); $prev = "{$this->root}/prev";

        [$code, $out] = $this->cutover($live, $rel, $prev);

        $this->assertSame(0, $code, $out);
        $this->assertSame('new', file_get_contents("{$live}/TAG"), 'live is the new release');
        $this->assertSame('old', file_get_contents("{$prev}/TAG"), 'the old app is the rollback point');
        $this->assertFileDoesNotExist($rel, 'the release folder is gone (it IS live now)');
        $config = file_get_contents("{$live}/bootstrap/cache/config.php");
        $this->assertStringContainsString($live, $config, 'config cached at the FINAL path');
        $this->assertStringNotContainsString($rel, $config, 'no trace of the build path');
        $this->assertFileDoesNotExist("{$live}/storage/framework/views/stale.php", 'stale compiled views did not travel');
        $this->assertFileDoesNotExist("{$live}/storage/framework/down", 'maintenance mode is off');
    }

    public function test_a_nested_release_is_refused_and_nothing_moves(): void
    {
        $live = $this->app('live', 'old');
        mkdir("{$this->root}/release", 0777, true);
        $nested = $this->app('release/AdminPanel_Update_V39', 'new');

        [$code, $out] = $this->cutover($live, "{$this->root}/release", "{$this->root}/prev");

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('nested', $out);
        $this->assertSame('old', file_get_contents("{$live}/TAG"));
        $this->assertFileDoesNotExist("{$this->root}/prev");
    }

    public function test_it_refuses_to_overwrite_an_existing_rollback_folder(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new'); $prev = $this->app('prev', 'older');

        [$code, $out] = $this->cutover($live, $rel, $prev);

        $this->assertNotSame(0, $code);
        $this->assertSame('old', file_get_contents("{$live}/TAG"));
        $this->assertSame('older', file_get_contents("{$prev}/TAG"), 'the existing rollback folder is untouched');
    }

    public function test_a_failed_cache_build_rolls_back_without_nesting_anything(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new'); $prev = "{$this->root}/prev";

        [$code, $out] = $this->cutover($live, $rel, $prev, ['FAIL_CACHE' => '1']);

        $this->assertSame(3, $code, $out);
        $this->assertSame('old', file_get_contents("{$live}/TAG"), 'the old app is live again');
        $this->assertFileExists("{$live}/artisan", 'and it is a real app root, not nested');
        $failed = glob("{$this->root}/live_failed_*");
        $this->assertCount(1, $failed, 'the failed release is kept under a unique name');
        $this->assertSame('new', file_get_contents("{$failed[0]}/TAG"));
        $this->assertFileDoesNotExist("{$live}/live", 'nothing was nested inside the live folder');
    }

    /** Starts a tiny web server whose every response is produced by $phpBody; returns [process, port]. */
    private function stubServer(string $phpBody): array
    {
        $dir = "{$this->root}/stub".random_int(1000, 9999);
        mkdir($dir);
        file_put_contents("{$dir}/index.php", "<?php {$phpBody}");
        $port = random_int(20000, 29999);
        $srv = proc_open(['php', '-S', "127.0.0.1:{$port}", '-t', $dir], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        usleep(700000);

        return [$srv, $port];
    }

    public function test_a_failed_health_check_rolls_back(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new'); $prev = "{$this->root}/prev";
        [$srv, $port] = $this->stubServer('http_response_code(500);');

        try {
            [$code, $out] = $this->cutover($live, $rel, $prev, [], "http://127.0.0.1:{$port}/");
        } finally {
            proc_terminate($srv);
        }

        $this->assertSame(5, $code, $out);
        $this->assertSame('old', file_get_contents("{$live}/TAG"));
    }

    public function test_a_redirect_to_the_login_page_counts_as_healthy(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new'); $prev = "{$this->root}/prev";
        [$srv, $port] = $this->stubServer("header('Location: /login/admin', true, 302);");

        try {
            [$code, $out] = $this->cutover($live, $rel, $prev, [], "http://127.0.0.1:{$port}/");
        } finally {
            proc_terminate($srv);
        }

        $this->assertSame(0, $code, $out);
        $this->assertSame('new', file_get_contents("{$live}/TAG"));
    }

    public function test_a_redirect_somewhere_else_is_not_healthy(): void
    {
        $live = $this->app('live', 'old'); $rel = $this->app('release', 'new'); $prev = "{$this->root}/prev";
        [$srv, $port] = $this->stubServer("header('Location: /maintenance-broken', true, 302);");

        try {
            [$code, $out] = $this->cutover($live, $rel, $prev, [], "http://127.0.0.1:{$port}/");
        } finally {
            proc_terminate($srv);
        }

        $this->assertSame(5, $code, $out);
    }
}
