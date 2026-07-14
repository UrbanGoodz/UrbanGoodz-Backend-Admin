<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VendorApiSecuritySourceTest extends TestCase
{
    public function test_vendor_routes_require_vendor_middleware_and_expose_logout(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/api/v1/api.php');

        $this->assertStringContainsString("'middleware'=>['vendor.api','actch:vendor_app']", $routes);
        $this->assertStringContainsString("Route::post('logout', 'VendorController@logout')", $routes);
    }

    public function test_order_status_updates_enforce_ownership_and_transition_rules(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Vendor/VendorController.php');

        $this->assertStringContainsString("whereHas('store.vendor'", $controller);
        $this->assertStringContainsString('Illegal order status transition.', $controller);
        $this->assertStringContainsString("'pending' => ['confirmed', 'canceled']", $controller);
    }

    public function test_inventory_updates_are_scoped_to_the_authenticated_store(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Vendor/ItemController.php');

        $this->assertStringContainsString("Item::where('store_id', \$storeId)->find", $controller);
        $this->assertStringContainsString("->where('store_id', \$request['vendor']->stores[0]->id)", $controller);
        $this->assertStringNotContainsString('Item::findOrFail($request->id)', $controller);
        $this->assertStringNotContainsString('Item::find($request->id)', $controller);
        $this->assertStringContainsString("'current_stock' => 'required|integer|min:0'", $controller);
        $this->assertStringContainsString("'stock_'.\$fieldSuffix => 'required|integer|min:0'", $controller);
    }

    public function test_fashion_fit_queries_fail_closed_and_redact_unapproved_photos(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/V1/Vendor/UrbanGoodzFashionMeasurementController.php');
        $model = file_get_contents(__DIR__.'/../../app/Models/MeasurementRequest.php');

        $this->assertStringContainsString("abort_unless(\$vendorId, 401", $controller);
        $this->assertStringContainsString("privacy_review_status === 'approved'", $controller);
        $this->assertStringContainsString("\$query->where('vendor_id', \$vendorId ?? 0)", $model);
    }
}
