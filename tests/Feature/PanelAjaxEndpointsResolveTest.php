<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The panels build some AJAX targets as hardcoded strings - "{{url('/')}}/admin/..."
 * rather than {{ route(...) }}. A route helper throws at render time when it is
 * wrong; a hardcoded path just 404s in the browser console, where nobody sees it.
 * That is how /subscribeToTopic went un-noticed: the layouts POSTed to a route
 * that was never registered and every panel page load answered 405.
 *
 * These are the hardcoded targets in the panel views, pinned to the route table.
 */
class PanelAjaxEndpointsResolveTest extends TestCase
{
    /** target => [method, calling view] */
    public static function hardcodedPanelEndpoints(): array
    {
        return [
            // Layout-level: these run on EVERY admin or vendor page load.
            'subscribeToTopic'                                   => ['POST', 'layouts/{admin,vendor}/app'],
            'admin/message/view/1/1'                             => ['GET', 'layouts/admin/app'],
            'admin/store/message/1/1'                            => ['GET', 'layouts/admin/app'],
            'admin/users/delivery-man/message/1/1'               => ['GET', 'layouts/admin/app'],
            'vendor-panel/message/view/1/1'                      => ['GET', 'layouts/vendor/app'],

            // Screen-level.
            'admin/business-settings/module/show/1'               => ['GET', 'product/edit, campaign/item/edit+index, product/index'],
            'admin/business-settings/module/type'                 => ['GET', 'module/create'],
            'admin/business-settings/zone/zone-filter/1'          => ['GET', 'order/distaptch_list'],
            'admin/users/delivery-man/get-deliverymen'            => ['GET', 'account/index+edit, deliveryman-earning-provide, disbursement-report'],
            'admin/store/get-account-data/1'                      => ['GET', 'account/index'],
            'admin/item/get-items'                                => ['GET', 'banner/edit'],
            'admin/pos/invoice/1'                                 => ['GET', 'pos/index'],
            'admin/order/filter/reset'                            => ['GET', 'order/distaptch_list'],
            'admin/order/list/all'                                => ['GET', 'layouts/admin/app'],
            'admin/parcel/orders/all'                             => ['GET', 'layouts/admin/app'],
            'vendor-panel/item/get-variations'                    => ['GET', 'product/stock_limit_list'],
            'vendor-panel/order/list/all'                         => ['GET', 'layouts/vendor/app'],
        ];
    }

    public function test_every_hardcoded_panel_target_resolves_for_the_method_it_is_called_with(): void
    {
        $broken = [];

        foreach (self::hardcodedPanelEndpoints() as $uri => [$method, $callers]) {
            if (!$this->routeExists($uri, $method)) {
                $broken[] = "{$method} /{$uri}  (called from {$callers})";
            }
        }

        $this->assertSame([], $broken, "These panel views call URLs with no matching route:\n" . implode("\n", $broken));
    }

    /** A URI that exists only for GET but is called with POST answers 405, not 404. */
    private function routeExists(string $uri, string $method): bool
    {
        $wanted = explode('/', trim($uri, '/'));

        foreach (Route::getRoutes() as $route) {
            $segments = explode('/', trim($route->uri(), '/'));

            if (count($segments) !== count($wanted) || !in_array($method, $route->methods(), true)) {
                continue;
            }

            foreach ($segments as $i => $segment) {
                if (!str_starts_with($segment, '{') && $segment !== $wanted[$i]) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
