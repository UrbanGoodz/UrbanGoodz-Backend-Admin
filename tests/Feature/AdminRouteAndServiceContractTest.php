<?php

namespace Tests\Feature;

use App\Services\UrbanGoodzPaymentService;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Structural contract guard.
 *
 * Three production HTTP 500s in a single day shared one shape: a reference that
 * did not resolve at runtime and that no test exercised.
 *
 *  - Order Anywhere detail: Blade dereferenced a null $cardRequest and called
 *    two route names that were never registered.
 *  - Dispatcher Best Loads: the Admin menu targeted a JSON controller method.
 *  - Admin Payments: the Payments view called
 *    `admin.urban-goodz.payments.platform-fee.update`, registered nowhere.
 *
 * These tests fail when a registered route targets a missing controller method,
 * when a Blade view references an unregistered route name, or when a payment
 * workflow calls a UrbanGoodzPaymentService method that does not exist.
 */
class AdminRouteAndServiceContractTest extends TestCase
{
    /**
     * Routes whose controller method does not exist, recorded as of
     * `9df3daa`. Most are 6amtech base-platform routes that predate the Urban
     * Goodz work; a few are the already-documented open AI-contract defects
     * (for example CreatorSpaceAIController::matchBrand, which the production
     * readiness handoff records as P0-A).
     *
     * They are recorded rather than silently skipped so that new breakage fails
     * immediately. Each entry is a latent HTTP 500 and is escalated for triage;
     * fixing one means deleting its line from the fixture.
     */
    private function knownBrokenRoutes(): array
    {
        // Capital "Fixtures" matches the existing repository convention and is
        // required on case-sensitive filesystems.
        $path = dirname(__DIR__).'/Fixtures/known-broken-routes.txt';
        $this->assertFileExists($path, 'Known-broken-route baseline is missing.');

        return array_values(array_filter(array_map('trim', file($path, FILE_IGNORE_NEW_LINES))));
    }

    private function currentBrokenRoutes(): array
    {
        $broken = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);
            if (class_exists($class) && method_exists($class, $method)) {
                continue;
            }

            $broken[] = class_exists($class)
                ? $route->uri().' -> '.class_basename($class).'::'.$method.'()'
                : $route->uri().' -> missing class '.$class;
        }

        sort($broken);

        return array_values(array_unique($broken));
    }

    /**
     * No route may break that was not already recorded as broken.
     */
    public function test_no_new_route_targets_a_missing_controller_method(): void
    {
        $new = array_values(array_diff($this->currentBrokenRoutes(), $this->knownBrokenRoutes()));

        $this->assertSame(
            [],
            $new,
            "Newly broken routes (controller method does not exist):\n".implode("\n", $new)
        );
    }

    /**
     * When a recorded defect is repaired, its baseline line must be removed so
     * the list keeps shrinking instead of rotting.
     */
    public function test_repaired_routes_are_removed_from_the_baseline(): void
    {
        $stale = array_values(array_diff($this->knownBrokenRoutes(), $this->currentBrokenRoutes()));

        $this->assertSame(
            [],
            $stale,
            "These routes now resolve - delete them from tests/Fixtures/known-broken-routes.txt:\n"
                .implode("\n", $stale)
        );
    }

    /**
     * Every named route referenced by an Urban Goodz Admin Blade view must be
     * registered. This is the check that would have caught all three 500s.
     */
    public function test_urban_goodz_admin_views_only_reference_registered_route_names(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views/admin-views/urban-goodz';
        $this->assertDirectoryExists($root);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $broken = [];

        foreach ($iterator as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all("/route\(\s*'([a-zA-Z0-9_.\-]+)'/", $contents, $matches);

            foreach (array_unique($matches[1]) as $name) {
                if (! Route::getRoutes()->getByName($name)) {
                    $relative = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $broken[] = $relative.' -> '.$name;
                }
            }
        }

        $this->assertSame([], $broken, "Blade views referencing unregistered route names:\n".implode("\n", $broken));
    }

    /**
     * The canonical payment service must expose every method its consumers call.
     */
    public function test_payment_service_exposes_the_methods_its_consumers_call(): void
    {
        $required = [
            // Authorization and capture lifecycle.
            'authorizeCustomerPayment',
            'captureCustomerPayment',
            'finalizeCustomerPayment',
            // Split lifecycle - these must never be dropped.
            'calculateSplits',
            'reservePendingSplits',
            'finalizeSplits',
            'settleSplits',
            // Ledger and reconciliation.
            'ledger',
        ];

        $missing = array_values(array_filter(
            $required,
            fn (string $m) => ! method_exists(UrbanGoodzPaymentService::class, $m)
        ));

        $this->assertSame([], $missing, 'UrbanGoodzPaymentService is missing: '.implode(', ', $missing));
    }

    /**
     * Guards the reconciliation itself: the capture path must still refuse to
     * capture a payment that was never authorized. The Git implementation threw
     * directly; the reconciled implementation keeps the same guard but allows an
     * already-captured payment to replay idempotently.
     */
    public function test_capture_still_refuses_an_unauthorized_payment(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Services/UrbanGoodzPaymentService.php'
        );

        $this->assertStringContainsString(
            "payment_status !== 'authorized'",
            $source,
            'The capture authorization guard has been removed.'
        );
        $this->assertStringContainsString(
            'Cannot capture: payment status is',
            $source,
            'The capture guard no longer reports the offending payment status.'
        );
    }

    /**
     * Split reservation must remain wired into the capture path.
     */
    public function test_split_reservation_remains_wired_into_capture(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Services/UrbanGoodzPaymentService.php'
        );

        $definition = substr_count($source, 'function reservePendingSplits');
        $callSites = substr_count($source, '$this->reservePendingSplits(');

        $this->assertSame(1, $definition, 'reservePendingSplits must be defined exactly once.');
        $this->assertGreaterThanOrEqual(
            2,
            $callSites,
            'reservePendingSplits lost call sites; splits would settle without reservation.'
        );
    }

    /**
     * The finalization value objects the canonical service returns must exist.
     */
    public function test_payment_finalization_value_objects_exist(): void
    {
        foreach ([
            \App\Services\Payments\PaymentFinalizationResult::class,
            \App\Services\Payments\PaymentFinalizationConflict::class,
            \App\Models\UrbanGoodzWebhookEvent::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), $class.' is missing.');
        }

        $reflection = new ReflectionClass(UrbanGoodzPaymentService::class);
        $return = $reflection->getMethod('finalizeCustomerPayment')->getReturnType();
        $this->assertNotNull($return, 'finalizeCustomerPayment must declare a return type.');
        $this->assertStringContainsString('PaymentFinalizationResult', (string) $return);
    }
}
