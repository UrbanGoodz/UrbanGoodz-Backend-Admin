<?php

use App\Http\Middleware\ActivationCheckMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminRentalModuleCheckMiddleware;
// Core Laravel web middleware
use App\Http\Middleware\APIGuestMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CurrentModule;
use App\Http\Middleware\DeliveryManWebMiddleware;
use App\Http\Middleware\DmTokenIsValid;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\Localization;
// Custom middleware
use App\Http\Middleware\LocalizationMiddleware;
use App\Http\Middleware\ModuleCheckMiddleware;
use App\Http\Middleware\ModulePermissionMiddleware;
use App\Http\Middleware\ProviderRentalModuleCheckMiddleware;
use App\Http\Middleware\ReactValid;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\Subscription;
use App\Http\Middleware\VendorMiddleware;
use App\Http\Middleware\VendorTokenIsValid;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        // commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Behind a TLS-terminating load balancer / CDN, Laravel only sees the
        // proxy's plain-HTTP hop unless the proxy is trusted. That makes the
        // app generate http:// form actions, redirects and asset URLs while the
        // browser is on https://, so the session cookie is dropped on the
        // redirected request and the next POST fails CSRF with HTTP 419.
        // Set TRUSTED_PROXIES to a comma-separated list of proxy IPs/CIDRs, or
        // "*" when the app is only reachable through the load balancer.
        // Unset keeps the previous behaviour (no proxy trusted).
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
            );
        }

        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            Localization::class,
        ]);

        $middleware->group('api', [
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,

            'admin' => AdminMiddleware::class,
            'tfa' => \App\Http\Middleware\TwoFactorAuthMiddleware::class,
            'vendor' => VendorMiddleware::class,
            'deliveryman' => DeliveryManWebMiddleware::class,
            'vendor.api' => VendorTokenIsValid::class,
            'dm.api' => DmTokenIsValid::class,
            'module' => ModulePermissionMiddleware::class,
            'installation-check' => InstallationMiddleware::class,
            'actch' => ActivationCheckMiddleware::class,
            'localization' => LocalizationMiddleware::class,
            'subscription' => Subscription::class,
            'react' => ReactValid::class,
            'apiGuestCheck' => APIGuestMiddleware::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'module-check' => ModuleCheckMiddleware::class,
            'current-module' => CurrentModule::class,
            'admin-rental-module' => AdminRentalModuleCheckMiddleware::class,
            'provider-rental-module' => ProviderRentalModuleCheckMiddleware::class,
            'business' => \App\Http\Middleware\BusinessMiddleware::class,
            'dispatcher' => \App\Http\Middleware\DispatcherMiddleware::class,
            'dispatch-territory' => \App\Http\Middleware\DispatchTerritoryScope::class,
            'business.portal.role' => \App\Http\Middleware\BusinessPortalRoleMiddleware::class,
            'admin.impersonation' => \App\Http\Middleware\AdminImpersonationMiddleware::class,
            'impersonation.audit' => \App\Http\Middleware\ImpersonationAuditMiddleware::class,
        ]);
    })

    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('ai-copilot:generate', ['--notify'])
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('sync-load-board')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn () => config('urban_goodz_load_board.sync.enabled', true));

        $schedule->command('queue:work', [
            '--queue' => 'payments,notifications,ai,load-sourcing,default',
            '--stop-when-empty',
            '--tries' => 3,
            '--backoff' => 30,
        ])
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        $schedule->command('monique:work', ['--account-type=all'])
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn () => config('urban_goodz_ai.monique_proactive.enabled', true));

        // These were defined in app/Console/Kernel.php, which Laravel 12 does not read, so none of them
        // ever ran. This closure is the only live schedule; tests/Feature/ScheduledCommandsAreRegisteredTest
        // fails if one goes missing again.
        $schedule->command('run-scheduled-sourcing')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn () => config('urban_goodz_load_board.sourcing.enabled', true));

        // Stranded: the responder answer window is measured in seconds, so this runs every minute.
        $schedule->command('stranded:dispatch-tick')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Order Anywhere: offer unassigned jobs to the next nearest driver, expire lapsed offers,
        // alert admins and release the customer's hold when nobody takes a job.
        $schedule->command('order-anywhere:dispatch-pending')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Reissues cards for Order Anywhere requests that are paid, assigned and
        // somehow have no live card, and closes cards whose window lapsed. Every
        // condition is a stuck state, so on a healthy system this does nothing.
        $schedule->command('order-anywhere:recover-card-issuance')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Recurring routes: create the next week of runs every night.
        $schedule->command('routes:generate-recurring', ['--ahead' => 7])
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('ai-concierge:prune-guests')
            ->dailyAt('03:17')
            ->withoutOverlapping()
            ->runInBackground();

        // Driver breadcrumbs are unbounded by nature: prune nightly, keeping each driver's latest point.
        $schedule->command('delivery-history:prune')
            ->dailyAt('03:20')
            ->withoutOverlapping()
            ->runInBackground();
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson() || $request->wantsJson();
        });
    })

    ->create();

// $requestUri = $_SERVER['REQUEST_URI'] ?? '';
// if (!str_starts_with($requestUri, '/image-proxy')) {
//     header('Access-Control-Allow-Origin: *');
// }
// header('Access-Control-Allow-Methods: *');
// header('Access-Control-Allow-Headers: *');
