<?php

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    foreach ([
        'App\\' => dirname(__DIR__) . '/app/',
        'Tests\\' => __DIR__ . '/',
    ] as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $file = $basePath . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);
