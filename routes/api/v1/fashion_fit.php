<?php

use Illuminate\Support\Facades\Route;

Route::prefix('fashion-fit')->middleware(['throttle:api'])->group(function () {
    Route::get('requirements', 'Api\V1\FashionFitCustomerController@requirements');
    Route::get('providers', 'Api\V1\FashionFitCustomerController@providers');
});

Route::prefix('fashion-fit')->middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::get('profiles', 'Api\V1\FashionFitCustomerController@index');
    Route::post('profiles', 'Api\V1\FashionFitCustomerController@store');
    Route::get('profiles/{uuid}', 'Api\V1\FashionFitCustomerController@show');
    Route::put('profiles/{uuid}', 'Api\V1\FashionFitCustomerController@update');
    Route::delete('profiles/{uuid}', 'Api\V1\FashionFitCustomerController@destroy');
    Route::post('profiles/{uuid}/consent', 'Api\V1\FashionFitCustomerController@updateConsent');
    Route::post('profiles/{uuid}/photos', 'Api\V1\FashionFitCustomerController@uploadPhoto')->middleware('throttle:10,1');
    Route::get('profiles/{profileUuid}/photos/{photoUuid}', 'Api\V1\FashionFitCustomerController@downloadPhoto')->middleware('throttle:30,1');
    Route::delete('profiles/{profileUuid}/photos/{photoUuid}', 'Api\V1\FashionFitCustomerController@deletePhoto');
    Route::post('profiles/{uuid}/analyses', 'Api\V1\FashionFitCustomerController@submitAnalysis')->middleware('throttle:3,1');
    Route::get('profiles/{profileUuid}/analyses/{analysisUuid}', 'Api\V1\FashionFitCustomerController@analysis');
    Route::put('profiles/{profileUuid}/measurements/{measurementId}', 'Api\V1\FashionFitCustomerController@correctMeasurement');
    Route::post('profiles/{uuid}/approve', 'Api\V1\FashionFitCustomerController@approve');
    Route::post('profiles/{uuid}/fit-check', 'Api\V1\FashionFitCustomerController@evaluateProductFit');
    Route::get('requests', 'Api\V1\FashionFitCustomerController@requests');
    Route::post('requests', 'Api\V1\FashionFitCustomerController@createRequest');
    Route::get('requests/{uuid}', 'Api\V1\FashionFitCustomerController@requestDetails');
    Route::post('requests/{requestUuid}/estimates/{estimateId}/decision', 'Api\V1\FashionFitCustomerController@decideEstimate');
    Route::post('requests/{uuid}/staged-payment', 'Api\V1\FashionFitCustomerController@stagedPayment')->middleware('throttle:3,1');
    Route::post('requests/{uuid}/revoke', 'Api\V1\FashionFitCustomerController@revoke');

    // Fashion Fit AI
    Route::prefix('ai')->group(function () {
        Route::post('extract-measurements', 'Api\V1\UrbanGoodz\FashionFitAIController@extractMeasurements');
        Route::post('match-size', 'Api\V1\UrbanGoodz\FashionFitAIController@matchSize');
        Route::post('suggest-adjustments', 'Api\V1\UrbanGoodz\FashionFitAIController@suggestAdjustments');
        Route::post('size-profile', 'Api\V1\UrbanGoodz\FashionFitAIController@generateSizeProfile');
        Route::get('providers', 'Api\V1\UrbanGoodz\FashionFitAIController@matchProviders');
        Route::post('quote-request', 'Api\V1\UrbanGoodz\FashionFitAIController@requestQuote');
        Route::get('requests', 'Api\V1\UrbanGoodz\FashionFitAIController@getMeasurementRequests');
        Route::put('measurements', 'Api\V1\UrbanGoodz\FashionFitAIController@updateMeasurements');
    });
});

Route::prefix('vendor/fashion-fit')->middleware(['vendor.api', 'actch:vendor_app', 'throttle:api'])->group(function () {
    Route::get('profile', 'Api\V1\Vendor\FashionFitWorkflowController@profile');
    Route::put('profile', 'Api\V1\Vendor\FashionFitWorkflowController@updateProfile');
    Route::get('requests', 'Api\V1\Vendor\FashionFitWorkflowController@index');
    Route::get('requests/{uuid}', 'Api\V1\Vendor\FashionFitWorkflowController@show');
    Route::get('requests/{requestUuid}/photos/{photoUuid}', 'Api\V1\Vendor\FashionFitWorkflowController@downloadPhoto')->middleware('throttle:30,1');
    Route::post('requests/{uuid}/clarification', 'Api\V1\Vendor\FashionFitWorkflowController@requestClarification');
    Route::post('requests/{uuid}/estimates', 'Api\V1\Vendor\FashionFitWorkflowController@estimate');
    Route::post('requests/{uuid}/status', 'Api\V1\Vendor\FashionFitWorkflowController@updateStatus');
    Route::get('earnings', 'Api\V1\Vendor\FashionFitWorkflowController@earnings');
});

Route::prefix('admin/fashion-fit')->middleware(['auth:admin', 'throttle:api'])->group(function () {
    Route::get('dashboard', 'Api\V1\Admin\FashionFitWorkflowController@dashboard');
    Route::get('requests', 'Api\V1\Admin\FashionFitWorkflowController@requests');
    Route::get('audits', 'Api\V1\Admin\FashionFitWorkflowController@audits');
    Route::post('providers/{vendor}/status', 'Api\V1\Admin\FashionFitWorkflowController@providerStatus');
});
