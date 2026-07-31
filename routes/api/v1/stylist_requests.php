<?php

use App\Http\Controllers\Api\V1\StylistRequestCustomerController;
use App\Http\Controllers\Api\V1\Vendor\StylistRequestController as VendorStylistRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer/stylist-requests')->middleware('auth:api')->group(function () {
    Route::get('/', [StylistRequestCustomerController::class, 'index']);
    Route::post('/', [StylistRequestCustomerController::class, 'store']);
    Route::get('{stylistRequest}', [StylistRequestCustomerController::class, 'show']);
    Route::post('{stylistRequest}/publish', [StylistRequestCustomerController::class, 'publish']);
    Route::post('{stylistRequest}/invite', [StylistRequestCustomerController::class, 'invite']);
    Route::get('{stylistRequest}/bids', [StylistRequestCustomerController::class, 'bids']);
    Route::post('{stylistRequest}/bids/{bid}/select', [StylistRequestCustomerController::class, 'selectBid']);
    Route::get('{stylistRequest}/messages', [StylistRequestCustomerController::class, 'messages']);
    Route::post('{stylistRequest}/messages', [StylistRequestCustomerController::class, 'sendMessage']);
    Route::post('{stylistRequest}/access', [StylistRequestCustomerController::class, 'grantAccess']);
    Route::post('{stylistRequest}/access/photos', [StylistRequestCustomerController::class, 'setPhotoAccess']);
    Route::delete('{stylistRequest}/access', [StylistRequestCustomerController::class, 'revokeAccess']);
});

Route::prefix('vendor/stylist-requests')->middleware(['vendor.api', 'actch:vendor_app'])->group(function () {
    Route::get('matching', [VendorStylistRequestController::class, 'matching']);
    Route::get('{stylistRequest}', [VendorStylistRequestController::class, 'show']);
    Route::post('{stylistRequest}/questions', [VendorStylistRequestController::class, 'ask']);
    Route::get('{stylistRequest}/messages', [VendorStylistRequestController::class, 'messages']);
    Route::post('{stylistRequest}/bids', [VendorStylistRequestController::class, 'bid']);
    Route::post('bids/{bid}/withdraw', [VendorStylistRequestController::class, 'withdrawBid']);
    Route::get('{stylistRequest}/measurements', [VendorStylistRequestController::class, 'measurements']);
    Route::get('{stylistRequest}/photos', [VendorStylistRequestController::class, 'photos']);
    Route::post('milestones/{milestone}', [VendorStylistRequestController::class, 'updateMilestone']);
});
