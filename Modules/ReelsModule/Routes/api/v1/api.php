<?php

use Illuminate\Support\Facades\Route;
use Modules\ReelsModule\Http\Controllers\Api\V1\Admin\CreatorModerationController;
use Modules\ReelsModule\Http\Controllers\Api\V1\CreatorCommerceController;
use Modules\ReelsModule\Http\Controllers\Api\V1\ReelController;
use Modules\ReelsModule\Http\Controllers\Api\V1\Vendor\ReelController as VendorReelController;

Route::group(['middleware' => ['localization', 'module-check']], function () {
    Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
        Route::group(['prefix' => 'reels', 'as' => 'reels.', 'middleware' => 'apiGuestCheck'], function () {
            Route::get('list', [ReelController::class, 'index'])->name('list');
            Route::get('details', [ReelController::class, 'show'])->name('show');
            Route::get('stats', [ReelController::class, 'stats'])->name('stats');
            Route::post('visit', [ReelController::class, 'visit'])->name('visit');
            Route::get('{reel}/comments', [CreatorCommerceController::class, 'comments'])->name('comments');
        });

        Route::group(['prefix' => 'reels', 'as' => 'reels.', 'middleware' => 'auth:api'], function () {
            Route::post('like', [ReelController::class, 'like'])->name('like');
            Route::post('report', [CreatorCommerceController::class, 'report'])->middleware('throttle:10,1');
            Route::post('attribution', [CreatorCommerceController::class, 'beginAttribution']);
            Route::post('attribution/order', [CreatorCommerceController::class, 'convertOrder']);
            Route::post('{reel}/comments', [CreatorCommerceController::class, 'storeComment'])
                ->middleware('throttle:20,1');
            Route::delete('comments/{comment}', [CreatorCommerceController::class, 'deleteComment']);
        });
        Route::get('creators', [CreatorCommerceController::class, 'profiles']);
        Route::group(['prefix' => 'creator', 'middleware' => 'auth:api'], function () {
            Route::get('opportunities', [CreatorCommerceController::class, 'opportunities']);
            Route::post('opportunities/{campaign}/accept', [CreatorCommerceController::class, 'acceptOpportunity']);
            Route::get('campaigns', [CreatorCommerceController::class, 'assignments']);
            Route::put('campaigns/{assignment}', [CreatorCommerceController::class, 'updateAssignment']);
            Route::get('analytics', [CreatorCommerceController::class, 'analytics']);
        });
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
    Route::get('creator/analytics', [CreatorCommerceController::class, 'vendorAnalytics']);
    Route::get('creator/campaigns', [CreatorCommerceController::class, 'vendorCampaigns']);
    Route::post('creator/campaigns', [CreatorCommerceController::class, 'storeVendorCampaign']);
    Route::put('creator/campaigns/{campaign}', [CreatorCommerceController::class, 'updateVendorCampaign']);
    Route::get('creator/campaigns/{campaign}/assignments', [CreatorCommerceController::class, 'vendorCampaignAssignments']);
    Route::put(
        'creator/campaigns/{campaign}/assignments/{assignment}',
        [CreatorCommerceController::class, 'reviewVendorCampaignAssignment']
    );
});

Route::prefix('admin/creator-commerce')->middleware('auth:admin')->group(function () {
    Route::get('creators', [CreatorModerationController::class, 'creators']);
    Route::put('creators/{profile}/status', [CreatorModerationController::class, 'creatorStatus']);
    Route::get('reels', [CreatorModerationController::class, 'reels']);
    Route::put('reels/{reel}/moderate', [CreatorModerationController::class, 'moderate']);
    Route::get('reports', [CreatorModerationController::class, 'reports']);
    Route::put('reports/{report}', [CreatorModerationController::class, 'resolveReport']);
    Route::get('comments', [CreatorModerationController::class, 'comments']);
    Route::put('comments/{comment}', [CreatorModerationController::class, 'moderateComment']);
});
