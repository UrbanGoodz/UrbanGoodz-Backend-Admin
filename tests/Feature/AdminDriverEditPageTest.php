<?php

namespace Tests\Feature;

use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Http\Controllers\Admin\DeliveryMan\DeliveryManController;
use App\Http\Controllers\Api\UrbanGoodzDriverCapabilityController;
use App\Models\Admin;
use App\Models\DeliveryMan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class AdminDriverEditPageTest extends TestCase
{
    public function test_driver_edit_route_keeps_admin_and_deliveryman_permission_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.users.delivery-man.edit');

        $this->assertNotNull($route);
        $this->assertSame('admin/users/delivery-man/edit/{id}', $route->uri());
        $this->assertSame(
            'App\\Http\\Controllers\\Admin\\DeliveryMan\\DeliveryManController@getUpdateView',
            $route->getActionName()
        );
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertContains('module:deliveryman', $route->gatherMiddleware());
    }

    public function test_controller_supplies_identity_and_capability_options_to_the_edit_view(): void
    {
        $admin = new Admin();
        $admin->id = 1;
        $this->actingAs($admin, 'admin');

        $deliveryMan = new DeliveryMan([
            'id' => 20,
            'f_name' => 'Driver',
            'l_name' => 'Twenty',
            'identity_image' => json_encode(['front.jpg', 'back.jpg']),
        ]);

        $repo = Mockery::mock(DeliveryManRepositoryInterface::class);
        $repo->shouldReceive('getFirstWithoutGlobalScopeWhere')
            ->once()
            ->with(['id' => 20])
            ->andReturn($deliveryMan);
        $this->app->instance(DeliveryManRepositoryInterface::class, $repo);

        $view = $this->app->make(DeliveryManController::class)->getUpdateView(20);
        $data = $view->getData();
        $expected = UrbanGoodzDriverCapabilityController::vehicleOptions();

        $this->assertSame('admin-views.delivery-man.edit', $view->name());
        $this->assertSame(['front.jpg', 'back.jpg'], $data['identityImages']);
        $this->assertSame($expected['vehicle_types'], $data['vehicleTypes']);
        $this->assertSame($expected['trailer_types'], $data['trailerTypes']);
        $this->assertSame($expected['hitch_types'], $data['hitchTypes']);
        $this->assertSame($expected['cdl_classes'], $data['cdlClasses']);
        $this->assertSame($expected['cdl_statuses'], $data['cdlStatuses']);
    }

    public function test_driver_edit_blade_compiles_without_raw_php_or_undefined_option_blocks(): void
    {
        $source = file_get_contents(resource_path('views/admin-views/delivery-man/edit.blade.php'));
        $compiled = Blade::compileString($source);

        $this->assertStringContainsString('$identityImages', $source);
        $this->assertStringContainsString('$vehicleTypes', $source);
        $this->assertStringContainsString('$trailerTypes', $source);
        $this->assertStringContainsString('$hitchTypes', $source);
        $this->assertStringContainsString('$cdlClasses', $source);
        $this->assertStringContainsString('$cdlStatuses', $source);
        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('@endphp', $source);
        $this->assertStringNotContainsString('@php', $compiled);
        $this->assertStringNotContainsString('@endphp', $compiled);
        $this->assertStringNotContainsString('{{', $compiled);
        $this->assertStringNotContainsString('@foreach', $compiled);
        $this->assertStringNotContainsString('@endif', $compiled);
    }

    public function test_invalid_or_missing_identity_images_are_normalized_to_an_empty_array(): void
    {
        foreach ([null, 'not-json'] as $identityImage) {
            $deliveryMan = new DeliveryMan(['id' => 20, 'identity_image' => $identityImage]);
            $repo = Mockery::mock(DeliveryManRepositoryInterface::class);
            $repo->shouldReceive('getFirstWithoutGlobalScopeWhere')->once()->andReturn($deliveryMan);
            $this->app->instance(DeliveryManRepositoryInterface::class, $repo);

            $view = $this->app->make(DeliveryManController::class)->getUpdateView(20);

            $this->assertSame([], $view->getData()['identityImages']);
        }
    }
}
