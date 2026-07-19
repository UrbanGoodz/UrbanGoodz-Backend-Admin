<?php
// Simple deployment helper for cPanel environment without SSH access.
// Usage: https://admin.urbangoodzdelivery.com/deploy_helper.php?token=UrbanGoodzDeploy2026!

$secretToken = 'UrbanGoodzDeploy2026!';
if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Forbidden: Invalid Token');
}

echo "<h3>Starting cPanel Deployment Tasks</h3>";

try {
    define('LARAVEL_START', microtime(true));
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // 1. Status
    echo "<h4>Migration Status:</h4>";
    $status = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('migrate:status', [], $status);
    echo "<pre>" . $status->fetch() . "</pre>";
    
    // 2. Migrate
    echo "<h4>Running Migrations...</h4>";
    $output = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('migrate', ['--force' => true], $output);
    echo "<pre>" . $output->fetch() . "</pre>";
    
    // 3. Seed
    echo "<h4>Running Seeder...</h4>";
    $seedOutput = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('db:seed', ['--class' => 'UrbanGoodzAiWorkforceSeeder', '--force' => true], $seedOutput);
    echo "<pre>" . $seedOutput->fetch() . "</pre>";
    
    // 4. Clear optimize
    echo "<h4>Clearing Optimization Cache...</h4>";
    $clearOutput = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('optimize:clear', [], $clearOutput);
    echo "<pre>" . $clearOutput->fetch() . "</pre>";
    
    // 5. Config Cache
    echo "<h4>Caching Config...</h4>";
    $configOutput = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('config:cache', [], $configOutput);
    echo "<pre>" . $configOutput->fetch() . "</pre>";
    
    // 6. View Cache
    echo "<h4>Caching Views...</h4>";
    $viewOutput = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call('view:cache', [], $viewOutput);
    echo "<pre>" . $viewOutput->fetch() . "</pre>";
    
    echo "<h3 style='color:green;'>Deployment Completed Successfully!</h3>";
} catch (\Throwable $e) {
    echo "<h3 style='color:red;'>Deployment Failed: " . $e->getMessage() . "</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
