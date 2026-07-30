<?php

$loader = require __DIR__.'/../vendor/autoload.php';

// Worktrees may reuse a dependency-only vendor directory through a junction.
// Pin project namespaces to this checkout so tests never execute stale source
// from the checkout that physically owns that shared vendor directory.
$classMap = $loader->getClassMap();
foreach (array_keys($classMap) as $class) {
    if (str_starts_with($class, 'App\\') || str_starts_with($class, 'Tests\\')) {
        unset($classMap[$class]);
    }
}
(new ReflectionProperty($loader, 'classMap'))->setValue($loader, $classMap);
$loader->setPsr4('App\\', [dirname(__DIR__).'/app']);
$loader->setPsr4('Tests\\', [__DIR__]);

return $loader;
