<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BusinessDispatcherRouteContractTest extends TestCase
{
    public function test_dispatcher_views_only_reference_registered_business_route_names(): void
    {
        $requiredRoutes = [
            'business.dispatcher.dashboard',
            'business.dispatcher.sourcing',
            'business.dispatcher.sourcing.search',
            'business.dispatcher.sourcing.saved-searches.store',
            'business.dispatcher.sourcing.saved-searches.run',
            'business.dispatcher.sourcing.saved-searches.delete',
            'business.dispatcher.loads',
            'business.dispatcher.loads.show',
            'business.dispatcher.loads.assign-driver',
            'business.dispatcher.loads.status',
            'business.dispatcher.drivers',
            'business.dispatcher.routes',
            'business.dispatcher.routes.show',
            'business.dispatcher.commissions',
            'business.dispatcher.territory',
            'business.dispatcher.territory.update',
            'business.dispatcher.users',
            'business.dispatcher.users.create',
            'business.dispatcher.users.store',
            'business.dispatcher.users.edit',
            'business.dispatcher.users.update',
            'business.dispatcher.users.deactivate',
        ];

        foreach ($requiredRoutes as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing dispatcher route [{$routeName}].");
        }

        $viewRoot = resource_path('views/business');
        $viewFiles = array_merge(
            glob($viewRoot . '/layouts/*.blade.php') ?: [],
            glob($viewRoot . '/dispatcher/*/*.blade.php') ?: [],
            glob($viewRoot . '/dispatcher/*.blade.php') ?: []
        );

        foreach ($viewFiles as $viewFile) {
            $contents = file_get_contents($viewFile);
            $this->assertStringNotContainsString("route('dispatcher.", $contents, $viewFile);
            $this->assertStringNotContainsString("routeIs('dispatcher.", $contents, $viewFile);
        }
    }
}
