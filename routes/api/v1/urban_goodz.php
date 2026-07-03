<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'urban-goodz/discovery'], function () {
    Route::post('search-capture', 'Api\V1\UrbanGoodzDiscoveryController@searchCapture');
    Route::get('entities', 'Api\V1\UrbanGoodzDiscoveryController@entities');
    Route::get('entities/{id}', 'Api\V1\UrbanGoodzDiscoveryController@entity');
    Route::post('entities/{id}/action', 'Api\V1\UrbanGoodzDiscoveryController@entityAction');
    Route::get('opportunities', 'Api\V1\UrbanGoodzDiscoveryController@opportunities');
    Route::post('opportunities/{id}/accept', 'Api\V1\UrbanGoodzDiscoveryController@acceptOpportunity');
});

Route::group(['prefix' => 'urban-goodz/earn-money'], function () {
    Route::get('opportunities', 'Api\V1\UrbanGoodzOpportunityController@earnMoneyOpportunities');
    Route::get('opportunities/{record}', 'Api\V1\UrbanGoodzOpportunityController@earnMoneyOpportunity');
    Route::post('opportunities/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptEarnMoneyOpportunity');
});

Route::group(['prefix' => 'urban-goodz/logistics'], function () {
    Route::get('jobs', 'Api\V1\UrbanGoodzOpportunityController@logisticsJobs');
    Route::get('jobs/{record}', 'Api\V1\UrbanGoodzOpportunityController@logisticsJob');
    Route::post('jobs/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptLogisticsJob');
    Route::post('jobs/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateLogisticsJobStatus');
});

Route::group(['prefix' => 'urban-goodz/load-board'], function () {
    Route::get('loads', 'Api\V1\UrbanGoodzOpportunityController@loadBoardLoads');
    Route::get('loads/{record}', 'Api\V1\UrbanGoodzOpportunityController@loadBoardLoad');
    Route::post('loads/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptLoadBoardLoad');
    Route::post('loads/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateLoadBoardLoadStatus');
});

Route::group(['prefix' => 'urban-goodz/medical-courier'], function () {
    Route::get('jobs', 'Api\V1\UrbanGoodzOpportunityController@medicalCourierJobs');
    Route::get('jobs/{record}', 'Api\V1\UrbanGoodzOpportunityController@medicalCourierJob');
    Route::post('jobs/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptMedicalCourierJob');
    Route::post('jobs/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateMedicalCourierJobStatus');
    Route::post('jobs/{record}/custody', 'Api\V1\UrbanGoodzOpportunityController@updateMedicalCourierCustody');
});

Route::group(['prefix' => 'urban-goodz/book-anything'], function () {
    Route::get('records', 'Api\V1\UrbanGoodzOpportunityController@bookAnythingRecords');
    Route::get('records/{record}', 'Api\V1\UrbanGoodzOpportunityController@bookAnythingRecord');
    Route::post('request', 'Api\V1\UrbanGoodzOpportunityController@submitBookAnythingRequest');
});

Route::group(['prefix' => 'urban-goodz/events'], function () {
    Route::get('/', 'Api\V1\UrbanGoodzOpportunityController@events');
    Route::get('{record}', 'Api\V1\UrbanGoodzOpportunityController@event');
    Route::post('{record}/interest', 'Api\V1\UrbanGoodzOpportunityController@eventInterest');
    Route::post('{record}/vendor-opportunity', 'Api\V1\UrbanGoodzOpportunityController@eventVendorOpportunity');
    Route::post('{record}/creator-opportunity', 'Api\V1\UrbanGoodzOpportunityController@eventCreatorOpportunity');
    Route::post('{record}/logistics-support', 'Api\V1\UrbanGoodzOpportunityController@eventLogisticsSupport');
});

Route::group(['prefix' => 'urban-goodz/fashion'], function () {
    Route::get('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@stylistRequests');
    Route::post('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@submitStylistRequest');
    Route::post('stylist-requests/{id}/status', 'Api\V1\UrbanGoodzFashionMeasurementController@updateStylistRequestStatus');
});

Route::group(['prefix' => 'order-anywhere'], function () {
    Route::post('requests', 'Api\V1\OrderAnywhereTesterController@store');
    Route::get('requests/{record}', 'Api\V1\OrderAnywhereTesterController@show');
    Route::post('requests/{record}/authorize-payment', 'Api\V1\OrderAnywhereTesterController@authorizePayment');
    Route::post('requests/{record}/receipt', 'Api\V1\OrderAnywhereTesterController@uploadReceipt');
    Route::get('customer/requests', 'Api\V1\OrderAnywhereTesterController@customerRequests');
    Route::get('admin/requests', 'Api\V1\OrderAnywhereTesterController@adminRequests');
    Route::post('admin/requests/{record}/status', 'Api\V1\OrderAnywhereTesterController@updateStatus');
    Route::post('admin/requests/{record}/notes', 'Api\V1\OrderAnywhereTesterController@addNotes');
    Route::post('admin/requests/{record}/assign-driver', 'Api\V1\OrderAnywhereTesterController@assignDriver');
    Route::post('vendor/requests/{record}/update', 'Api\V1\OrderAnywhereTesterController@vendorUpdate');
    Route::get('driver/available', 'Api\V1\OrderAnywhereTesterController@driverAvailable');
    Route::post('driver/{record}/accept', 'Api\V1\OrderAnywhereTesterController@driverAccept');
    Route::post('driver/{record}/status', 'Api\V1\OrderAnywhereTesterController@driverStatus');
    Route::post('driver/{record}/issue', 'Api\V1\OrderAnywhereTesterController@driverIssue');
});

Route::group(['prefix' => 'urban-goodz/creator-commerce'], function () {
    Route::post('applications', 'Api\V1\CreatorCommerceTesterController@storeApplication');
    Route::get('customer/applications', 'Api\V1\CreatorCommerceTesterController@customerApplications');
    Route::get('admin/applications', 'Api\V1\CreatorCommerceTesterController@adminApplications');
    Route::post('admin/applications/{record}/status', 'Api\V1\CreatorCommerceTesterController@updateApplicationStatus');
    Route::post('promotions', 'Api\V1\CreatorCommerceTesterController@storePromotion');
    Route::get('admin/promotions', 'Api\V1\CreatorCommerceTesterController@promotions');
    Route::post('admin/promotions/{record}/status', 'Api\V1\CreatorCommerceTesterController@updatePromotionStatus');
});
