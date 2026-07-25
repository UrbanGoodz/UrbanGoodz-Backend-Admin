<?php

/**
 * Builds the driver API contract matrix from the live route collection.
 *
 * Reading the router (rather than grepping routes/*.php) is what makes the
 * middleware column trustworthy: group-inherited middleware only exists after
 * the routes are compiled.
 *
 * Emits CSV to stdout:
 *   feature,method,uri,middleware,controller,action,form_request,tables,status
 *
 * Usage:
 *     APP_ENV=staging php scripts/audit/driver_contract_matrix.php > out.csv
 */

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** Feature label => URI substrings that belong to it. */
$features = [
    'auth-login'             => ['auth/delivery-man/login'],
    'auth-register'          => ['auth/delivery-man/store'],
    'auth-password-reset'    => ['auth/delivery-man/forgot-password', 'auth/delivery-man/verify-token', 'auth/delivery-man/reset-password'],
    'profile'                => ['delivery-man/profile', 'delivery-man/update-profile', 'delivery-man/remove-account'],
    'online-offline'         => ['delivery-man/update-active-status'],
    'location'               => ['delivery-man/record-location-data', 'delivery-man/last-location'],
    'fcm-token'              => ['delivery-man/update-fcm-token'],
    'order-queue'            => ['delivery-man/current-orders', 'delivery-man/latest-orders', 'delivery-man/all-orders'],
    'order-lifecycle'        => ['delivery-man/order-status', 'delivery-man/accept-order', 'delivery-man/update-order-status'],
    'earnings'               => ['delivery-man/earning-report', 'delivery-man/income-statement', 'driver/earnings'],
    'payouts'                => ['delivery-man/get-withdraw-list', 'delivery-man/get-disbursement-report', 'driver/payout-history', 'delivery-man/get-withdraw-method-list'],
    'loyalty'                => ['delivery-man/loyalty-point-list', 'delivery-man/convert-loyalty-points'],
    'reviews'                => ['delivery-man/reviews'],
    'notifications'          => ['driver/dispatch-notifications', 'delivery-man/notifications'],
    'business-jobs'          => ['driver/business-jobs'],
    'active-jobs'            => ['driver/active-jobs'],
    'job-discovery'          => ['driver/job-discovery', 'driver/opportunities'],
    'load-board'             => ['driver/load-board'],
    'routes-planning'        => ['driver/routes'],
    'certifications'         => ['driver/certifications'],
    'capability-profile'     => ['driver/capability-profile', 'driver/capability-summary'],
    'vehicles'               => ['driver/vehicles'],
];

/** Best-effort: which tables does the controller action touch? */
function tablesFor(?string $controller, ?string $method): string
{
    if (! $controller || ! class_exists($controller)) {
        return '';
    }

    try {
        $ref = new ReflectionMethod($controller, $method);
    } catch (Throwable) {
        return '';
    }

    $file = $ref->getFileName();
    if (! $file || ! is_readable($file)) {
        return '';
    }

    $src = implode('', array_slice(
        file($file),
        $ref->getStartLine() - 1,
        $ref->getEndLine() - $ref->getStartLine() + 1
    ));

    $tables = [];
    if (preg_match_all('/DB::table\([\'"]([a-z0-9_]+)[\'"]\)/i', $src, $m)) {
        $tables = array_merge($tables, $m[1]);
    }
    if (preg_match_all('/\b([A-Z][A-Za-z0-9_]+)::(?:where|find|create|query|with|all|first)\b/', $src, $m)) {
        $tables = array_merge($tables, array_map(fn ($c) => $c.' (model)', $m[1]));
    }

    return implode('|', array_values(array_unique($tables)));
}

/** Does the action type-hint a FormRequest, or validate inline? */
function validationFor(?string $controller, ?string $method): string
{
    if (! $controller || ! class_exists($controller)) {
        return 'unknown';
    }

    try {
        $ref = new ReflectionMethod($controller, $method);
    } catch (Throwable) {
        return 'unknown';
    }

    foreach ($ref->getParameters() as $p) {
        $type = $p->getType();
        if ($type instanceof ReflectionNamedType
            && is_subclass_of($type->getName(), Illuminate\Foundation\Http\FormRequest::class)) {
            return 'FormRequest:'.class_basename($type->getName());
        }
    }

    $file = $ref->getFileName();
    if ($file && is_readable($file)) {
        $src = implode('', array_slice(
            file($file),
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
        if (str_contains($src, 'Validator::make')) {
            return 'inline Validator::make';
        }
        if (str_contains($src, '$request->validate')) {
            return 'inline $request->validate';
        }
    }

    return 'NONE';
}

$out = fopen('php://stdout', 'w');
fputcsv($out, ['feature', 'method', 'uri', 'middleware', 'controller', 'action', 'validation', 'tables', 'status']);

$routes = collect(Illuminate\Support\Facades\Route::getRoutes());
$seen = [];

foreach ($features as $feature => $needles) {
    $matched = $routes->filter(function ($r) use ($needles) {
        foreach ($needles as $n) {
            if (str_contains($r->uri(), $n)) {
                return true;
            }
        }

        return false;
    });

    if ($matched->isEmpty()) {
        fputcsv($out, [$feature, '', '', '', '', '', '', '', 'ABSENT']);
        continue;
    }

    foreach ($matched as $r) {
        $action = $r->getAction();
        $ctrl = null;
        $m = null;
        if (isset($action['controller']) && str_contains($action['controller'], '@')) {
            [$ctrl, $m] = explode('@', $action['controller'], 2);
        }

        $key = implode('|', $r->methods()).$r->uri();
        $seen[$key] = true;

        fputcsv($out, [
            $feature,
            implode('|', array_diff($r->methods(), ['HEAD'])),
            '/'.ltrim($r->uri(), '/'),
            implode(' ', array_filter($r->gatherMiddleware(), 'is_string')),
            $ctrl ? class_basename($ctrl) : '(closure)',
            $m ?? '',
            validationFor($ctrl, $m),
            tablesFor($ctrl, $m),
            'PRESENT',
        ]);
    }
}

fclose($out);
