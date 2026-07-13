<?php

use Illuminate\Support\Facades\Route;
use Modules\ReelsModule\Http\Controllers\Api\V1\ReelController;
use Modules\ReelsModule\Http\Controllers\Api\V1\Vendor\ReelController as VendorReelController;
use Modules\ReelsModule\Http\Controllers\Api\V1\CreatorCommerceController;
use Modules\ReelsModule\Http\Controllers\Api\V1\Admin\CreatorModerationController;

Route::group(['middleware' => ['localization', 'module-check']], function () {
    Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
        Route::group(['prefix' => 'reels', 'as' => 'reels.', 'middleware' => 'apiGuestCheck'], function () {
            Route::get('list', [ReelController::class, 'index'])->name('list');
            Route::get('details', [ReelController::class, 'show'])->name('show');
            Route::get('stats', [ReelController::class, 'stats'])->name('stats');
            Route::post('visit', [ReelController::class, 'visit'])->name('visit');
        });

        Route::group(['prefix' => 'reels', 'as' => 'reels.', 'middleware' => 'auth:api'], function () {
            Route::post('like', [ReelController::class, 'like'])->name('like');
            Route::post('report', [CreatorCommerceController::class, 'report'])->middleware('throttle:10,1');
            Route::post('attribution', [CreatorCommerceController::class, 'beginAttribution']);
            Route::post('attribution/order', [CreatorCommerceController::class, 'convertOrder']);
        });
        Route::get('creators', [CreatorCommerceController::class, 'profiles']);
    });
});

Route::group(['prefix' => 'vendor', 'namespace' => 'Vendor', 'middleware' => ['vendor.api', 'actch:vendor_app']], function () {
    Route::group(['prefix' => 'reel'], function () {
        Route::get('list', [VendorReelController::class, 'index']);
        Route::post('store', [VendorReelController::class, 'store']);
        Route::get('details', [VendorReelController::class, 'show']);
        Route::put('update', [VendorReelController::class, 'update']);
        Route::delete('delete', [VendorReelController::class, 'destroy']);
        Route::put('status', [VendorReelController::class, 'status']);
        Route::post('{reel}/publish', [CreatorCommerceController::class, 'publish']);
        Route::post('{reel}/unpublish', [CreatorCommerceController::class, 'unpublish']);
    });
    Route::get('creator/profile', [CreatorCommerceController::class, 'vendorProfile']);
    Route::put('creator/profile', [CreatorCommerceController::class, 'updateVendorProfile']);
    Route::get('creator/earnings', [CreatorCommerceController::class, 'earnings']);
});

Route::prefix('admin/creator-commerce')->middleware('auth:admin')->group(function () {
    Route::get('creators', [CreatorModerationController::class, 'creators']);
    Route::put('creators/{profile}/status', [CreatorModerationController::class, 'creatorStatus']);
    Route::get('reels', [CreatorModerationController::class, 'reels']);
    Route::put('reels/{reel}/moderate', [CreatorModerationController::class, 'moderate']);
    Route::get('reports', [CreatorModerationController::class, 'reports']);
    Route::put('reports/{report}', [CreatorModerationController::class, 'resolveReport']);
});
