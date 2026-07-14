<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'urban-goodz', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('app-config', 'Api\V1\UrbanGoodz\UrbanGoodzAppConfigController@index');
});
Route::get('urban-goodz/driver/vehicle-options', 'Api\UrbanGoodzDriverCapabilityController@vehicleOptionsEndpoint')->middleware('throttle:60,1');
Route::group(['prefix' => 'urban-goodz/discovery', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('search-capture', 'Api\V1\UrbanGoodzDiscoveryController@searchCapture');
    Route::get('entities', 'Api\V1\UrbanGoodzDiscoveryController@entities');
    Route::get('entities/{id}', 'Api\V1\UrbanGoodzDiscoveryController@entity');
    Route::post('entities/{id}/action', 'Api\V1\UrbanGoodzDiscoveryController@entityAction');
    Route::get('opportunities', 'Api\V1\UrbanGoodzDiscoveryController@opportunities');
    Route::post('opportunities/{id}/accept', 'Api\V1\UrbanGoodzDiscoveryController@acceptOpportunity');
});

Route::group(['prefix' => 'urban-goodz/earn-money', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('opportunities', 'Api\V1\UrbanGoodzOpportunityController@earnMoneyOpportunities');
    Route::get('opportunities/{record}', 'Api\V1\UrbanGoodzOpportunityController@earnMoneyOpportunity');
    Route::post('opportunities/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptEarnMoneyOpportunity');
});

Route::group(['prefix' => 'urban-goodz/logistics', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('jobs', 'Api\V1\UrbanGoodzOpportunityController@logisticsJobs');
    Route::get('jobs/{record}', 'Api\V1\UrbanGoodzOpportunityController@logisticsJob');
    Route::post('jobs/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptLogisticsJob');
    Route::post('jobs/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateLogisticsJobStatus');
});

Route::group(['prefix' => 'urban-goodz/load-board', 'middleware' => 'auth:api'], function () {
    Route::get('loads', 'Api\V1\UrbanGoodzOpportunityController@loadBoardLoads');
    Route::get('loads/{record}', 'Api\V1\UrbanGoodzOpportunityController@loadBoardLoad');
    Route::post('loads/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptLoadBoardLoad');
    Route::post('loads/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateLoadBoardLoadStatus');
});

Route::group(['prefix' => 'urban-goodz/medical-courier', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('jobs', 'Api\V1\UrbanGoodzOpportunityController@medicalCourierJobs');
    Route::get('jobs/{record}', 'Api\V1\UrbanGoodzOpportunityController@medicalCourierJob');
    Route::post('jobs/{record}/accept', 'Api\V1\UrbanGoodzOpportunityController@acceptMedicalCourierJob');
    Route::post('jobs/{record}/status', 'Api\V1\UrbanGoodzOpportunityController@updateMedicalCourierJobStatus');
    Route::post('jobs/{record}/custody', 'Api\V1\UrbanGoodzOpportunityController@updateMedicalCourierCustody');
});

Route::group(['prefix' => 'urban-goodz/book-anything', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('records', 'Api\V1\UrbanGoodzOpportunityController@bookAnythingRecords');
    Route::get('records/{record}', 'Api\V1\UrbanGoodzOpportunityController@bookAnythingRecord');
    Route::post('request', 'Api\V1\UrbanGoodzOpportunityController@submitBookAnythingRequest');
});

Route::group(['prefix' => 'urban-goodz/events', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('/', 'Api\V1\UrbanGoodzOpportunityController@events');
    Route::get('{record}', 'Api\V1\UrbanGoodzOpportunityController@event');
    Route::post('{record}/interest', 'Api\V1\UrbanGoodzOpportunityController@eventInterest');
    Route::post('{record}/vendor-opportunity', 'Api\V1\UrbanGoodzOpportunityController@eventVendorOpportunity');
    Route::post('{record}/creator-opportunity', 'Api\V1\UrbanGoodzOpportunityController@eventCreatorOpportunity');
    Route::post('{record}/logistics-support', 'Api\V1\UrbanGoodzOpportunityController@eventLogisticsSupport');
});

Route::group(['prefix' => 'urban-goodz/fashion', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@stylistRequests');
    Route::post('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@submitStylistRequest');
    Route::post('stylist-requests/{id}/status', 'Api\V1\UrbanGoodzFashionMeasurementController@updateStylistRequestStatus');
});

Route::post('adyen/webhook', 'Api\V1\AdyenWebhookController@handle');

Route::post('payments/webhooks/{provider}', 'Api\V1\PaymentWebhookController@handle')
    ->where('provider', 'adyen|stripe|staged_test');

Route::group(['prefix' => 'order-anywhere', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('requests', 'Api\V1\OrderAnywhereTesterController@store');
    Route::get('requests/{record}', 'Api\V1\OrderAnywhereTesterController@show');
    Route::post('requests/{record}/authorize-payment', 'Api\V1\OrderAnywhereTesterController@authorizePayment');
    Route::post('requests/{record}/receipt', 'Api\V1\OrderAnywhereTesterController@uploadReceipt');
    Route::get('customer/requests', 'Api\V1\OrderAnywhereTesterController@customerRequests');
    Route::get('admin/requests', 'Api\V1\OrderAnywhereTesterController@adminRequests');
    Route::post('admin/requests/{record}/status', 'Api\V1\OrderAnywhereTesterController@updateStatus');
    Route::post('admin/requests/{record}/notes', 'Api\V1\OrderAnywhereTesterController@addNotes');
    Route::post('admin/requests/{record}/assign-driver', 'Api\V1\OrderAnywhereTesterController@assignDriver');
    Route::post('admin/requests/{record}/payment-link', 'Api\V1\OrderAnywhereTesterController@createPaymentLink');
    Route::post('vendor/requests/{record}/update', 'Api\V1\OrderAnywhereTesterController@vendorUpdate');
    Route::get('driver/available', 'Api\V1\OrderAnywhereTesterController@driverAvailable');
    Route::post('driver/{record}/accept', 'Api\V1\OrderAnywhereTesterController@driverAccept');
    Route::post('driver/{record}/status', 'Api\V1\OrderAnywhereTesterController@driverStatus');
    Route::post('driver/{record}/issue', 'Api\V1\OrderAnywhereTesterController@driverIssue');
});

Route::group(['prefix' => 'urban-goodz/fashion-fit'], function () {
    Route::post('photos/upload', 'Api\V1\UrbanGoodz\FashionFitFileController@uploadPhoto')->middleware('auth:api');
});

Route::group(['prefix' => 'urban-goodz/files', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('upload/{category}', 'Api\V1\UrbanGoodz\UrbanGoodzFileUploadController@upload');
});

Route::group(['prefix' => 'urban-goodz/ai-concierge', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('query', 'Api\V1\UrbanGoodz\UrbanGoodzAIConciergeController@query');
    Route::get('history', 'Api\V1\UrbanGoodz\UrbanGoodzAIConciergeController@history');
});

// Driver API - dedicated routes, package scanning, earnings, payouts
Route::group(['prefix' => 'urban-goodz/driver', 'middleware' => 'dm.api'], function () {
    Route::get('routes', 'Api\UrbanGoodzDriverApiController@assignedRoutes');
    Route::get('routes/{routeId}', 'Api\UrbanGoodzDriverApiController@routeDetail');
    Route::post('routes/{routeId}/started', 'Api\UrbanGoodzDriverApiController@routeStarted');
    Route::post('routes/{routeId}/completed', 'Api\UrbanGoodzDriverApiController@routeCompleted');
    Route::post('routes/{routeId}/scan-pickup', 'Api\UrbanGoodzDriverApiController@scanPickup');
    Route::post('routes/{routeId}/scan-dropoff', 'Api\UrbanGoodzDriverApiController@scanDropoff');
    Route::post('routes/{routeId}/scan-exception', 'Api\UrbanGoodzDriverApiController@scanException');
    Route::post('routes/{routeId}/age-verify', 'Api\UrbanGoodzDriverApiController@submitAgeVerification');
    Route::post('routes/{routeId}/age-refuse', 'Api\UrbanGoodzDriverApiController@submitAgeRefusal');
    Route::get('routes/{routeId}/age-status', 'Api\UrbanGoodzDriverApiController@checkAgeStatus');
    Route::get('earnings', 'Api\UrbanGoodzDriverApiController@earnings');
    Route::post('payout-request', 'Api\UrbanGoodzDriverApiController@requestPayout');
    Route::get('payout-history', 'Api\UrbanGoodzDriverApiController@payoutHistory');
    // Driver capability and vehicle profile
    Route::get('capability-profile', 'Api\UrbanGoodzDriverCapabilityController@profile');
    Route::get('capability-summary', 'Api\UrbanGoodzDriverCapabilityController@summary');
    Route::post('capability-profile/vehicle', 'Api\UrbanGoodzDriverCapabilityController@updateVehicle');
    Route::post('capability-profile/trailer', 'Api\UrbanGoodzDriverCapabilityController@updateTrailer');
    Route::post('capability-profile/commercial', 'Api\UrbanGoodzDriverCapabilityController@updateCommercial');
    Route::post('capability-profile/cargo', 'Api\UrbanGoodzDriverCapabilityController@updateCargo');
    Route::post('capability-profile/zones', 'Api\UrbanGoodzDriverCapabilityController@updateZones');
    Route::post('capability-profile/work-types', 'Api\UrbanGoodzDriverCapabilityController@updateWorkTypes');
    Route::post('capability-profile/tags', 'Api\UrbanGoodzDriverCapabilityController@updateTags');
    Route::post('capability-profile/availability', 'Api\UrbanGoodzDriverCapabilityController@updateAvailability');

    // Driver job discovery (read-only discovery of available work)
    Route::get('job-discovery', 'Api\UrbanGoodzDriverJobDiscoveryController@index');
    Route::get('job-discovery/summary', 'Api\UrbanGoodzDriverJobDiscoveryController@summary');
    Route::get('job-discovery/{type}/{id}', 'Api\UrbanGoodzDriverJobDiscoveryController@detail');

    // Driver dispatch notifications (read-only inbox over existing notification system)
    Route::get('dispatch-notifications', 'Api\UrbanGoodzDriverDispatchNotificationController@index');
    Route::get('dispatch-notifications/unread-count', 'Api\UrbanGoodzDriverDispatchNotificationController@unreadCount');
    Route::post('dispatch-notifications/read-all', 'Api\UrbanGoodzDriverDispatchNotificationController@readAll');
    Route::post('dispatch-notifications/{notificationId}/read', 'Api\UrbanGoodzDriverDispatchNotificationController@markRead');
    Route::post('dispatch-notifications/{notificationId}/dismiss', 'Api\UrbanGoodzDriverDispatchNotificationController@dismiss');

    // Business courier jobs for driver
    Route::get('business-jobs', 'Api\UrbanGoodzDriverBusinessCourierController@assignedJobs');
    Route::get('business-jobs/{jobId}', 'Api\UrbanGoodzDriverBusinessCourierController@jobDetail');
    Route::post('business-jobs/{jobId}/accept', 'Api\UrbanGoodzDriverBusinessCourierController@acceptJob');
    Route::post('business-jobs/{jobId}/start', 'Api\UrbanGoodzDriverBusinessCourierController@startJob');
    Route::post('business-jobs/{jobId}/pickup', 'Api\UrbanGoodzDriverBusinessCourierController@markPickup');
    Route::post('business-jobs/{jobId}/delivery', 'Api\UrbanGoodzDriverBusinessCourierController@markDelivery');
    Route::post('business-jobs/{jobId}/proof-pickup', 'Api\UrbanGoodzDriverBusinessCourierController@submitPickupProof');
    Route::post('business-jobs/{jobId}/proof-delivery', 'Api\UrbanGoodzDriverBusinessCourierController@submitDeliveryProof');
    Route::post('business-jobs/{jobId}/exception', 'Api\UrbanGoodzDriverBusinessCourierController@reportException');

    // Driver Order Anywhere purchase card
    Route::get('order-anywhere/{requestId}/purchase-card', 'Api\V1\UrbanGoodzDriverPurchaseCardController@getCard');
    Route::post('order-anywhere/{requestId}/purchase-card/authorize', 'Api\V1\UrbanGoodzDriverPurchaseCardController@authorizePurchase');
    Route::post('order-anywhere/{requestId}/purchase-card/complete', 'Api\V1\UrbanGoodzDriverPurchaseCardController@completePurchase');

    // Driver active jobs (unified across all sources)
    Route::get('active-jobs', 'Api\UrbanGoodzDriverActiveJobsController@index');
    Route::get('active-jobs/{jobId}', 'Api\UrbanGoodzDriverActiveJobsController@detail');
    Route::post('active-jobs/{jobId}/start', 'Api\UrbanGoodzDriverActiveJobsController@startJob');
    Route::post('active-jobs/{jobId}/complete', 'Api\UrbanGoodzDriverActiveJobsController@completeJob');
    Route::post('active-jobs/{jobId}/cancel', 'Api\UrbanGoodzDriverActiveJobsController@cancelJob');
    Route::post('active-jobs/{jobId}/status', 'Api\UrbanGoodzDriverActiveJobsController@updateStatus');

    // Driver load board
    Route::get('load-board', 'Api\UrbanGoodzDriverActiveJobsController@loadBoardAvailable');
    Route::post('load-board/{loadId}/bid', 'Api\UrbanGoodzDriverActiveJobsController@loadBoardBid');
    Route::post('load-board/{loadId}/accept', 'Api\UrbanGoodzDriverActiveJobsController@acceptJob');

    // Driver opportunities
    Route::get('opportunities', 'Api\UrbanGoodzDriverActiveJobsController@opportunities');
    Route::post('opportunities/{opportunityId}/claim', 'Api\UrbanGoodzDriverActiveJobsController@claimOpportunity');

    // Driver vehicles and certifications
    Route::get('vehicles', 'Api\UrbanGoodzDriverActiveJobsController@vehicles');
    Route::get('certifications', 'Api\UrbanGoodzDriverActiveJobsController@certifications');
    Route::post('certifications/{certId}/upload', 'Api\UrbanGoodzDriverActiveJobsController@uploadCertDocument');
    Route::post('certifications/{certId}/renew', 'Api\UrbanGoodzDriverActiveJobsController@renewCertification');
});
