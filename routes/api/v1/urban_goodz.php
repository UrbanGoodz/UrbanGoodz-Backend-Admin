<?php

use Illuminate\Support\Facades\Route;
use Modules\ReelsModule\Http\Controllers\Api\V1\CreatorCommerceController as ReelsCreatorCommerceController;

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

Route::group(['prefix' => 'urban-goodz/marketplace-data', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('businesses', 'Api\V1\UrbanGoodzMarketplaceDataController@businesses');
    Route::get('businesses/{id}', 'Api\V1\UrbanGoodzMarketplaceDataController@business');
    Route::get('shopper/catalogs', 'Api\V1\UrbanGoodzMarketplaceDataController@shopperCatalogs');
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

Route::group(['prefix' => 'urban-goodz/creator-commerce', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('featured-reels', 'Api\V1\CreatorCommerceController@featuredReels');
    Route::get('reels', 'Api\V1\CreatorCommerceController@featuredReels');
    Route::get('customer/applications', 'Api\V1\CreatorCommerceController@customerApplications');
    Route::post('applications', 'Api\V1\CreatorCommerceController@storeApplication');
    Route::get('promotions', 'Api\V1\CreatorCommerceController@promotions');
    Route::post('promotions', 'Api\V1\CreatorCommerceController@storePromotion');
});

Route::group(['prefix' => 'urban-goodz/reels', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('action', [ReelsCreatorCommerceController::class, 'legacyAction']);
    Route::post('conversion', [ReelsCreatorCommerceController::class, 'legacyConversion']);
    Route::get('opportunities', [ReelsCreatorCommerceController::class, 'opportunities']);
    Route::post('opportunities/{campaign}/accept', [ReelsCreatorCommerceController::class, 'acceptOpportunity']);
    Route::get('analytics', [ReelsCreatorCommerceController::class, 'analytics']);
});

// Creator Space AI
Route::group(['prefix' => 'urban-goodz/creator/ai', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('reel-script', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@generateReelScript');
    Route::post('product-tags', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@generateProductTags');
    Route::post('caption', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@generateCaption');
    Route::post('performance', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@analyzePerformance');
    Route::get('brand-matches', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@matchBrand');
    Route::post('reel-analytics', 'Api\V1\UrbanGoodz\CreatorSpaceAIController@generateReelAnalytics');
});

Route::group(['prefix' => 'urban-goodz/fashion', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@stylistRequests');
    Route::post('stylist-requests', 'Api\V1\UrbanGoodzFashionMeasurementController@submitStylistRequest');
    Route::post('stylist-requests/{id}/status', 'Api\V1\UrbanGoodzFashionMeasurementController@updateStylistRequestStatus');
});

Route::post('adyen/webhook', 'Api\V1\AdyenWebhookController@handle');

Route::post('payments/webhooks/{provider}', 'Api\V1\PaymentWebhookController@handle')
    ->where('provider', 'adyen|stripe|staged_test');

Route::post('order-anywhere/cards/stripe/webhook', 'Api\V1\StripeIssuingWebhookController@handle')
    ->middleware('throttle:120,1');

Route::group(['prefix' => 'order-anywhere', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('requests', 'Api\V1\OrderAnywhereController@store');
    Route::post('requests/estimate', 'Api\V1\OrderAnywhereController@estimate');
    Route::get('requests/{record}', 'Api\V1\OrderAnywhereController@show');
    Route::post('requests/{record}/authorize-payment', 'Api\V1\OrderAnywhereController@authorizePayment');
    Route::post('requests/{record}/receipt', 'Api\V1\OrderAnywhereController@uploadReceipt');
    Route::get('customer/requests', 'Api\V1\OrderAnywhereController@customerRequests');
    Route::post('orders/{orderId}/dispatch/trigger-nearest', 'Api\V1\UrbanGoodz\OrderAiDispatchController@triggerNearestDriver');
    Route::get('orders/{orderId}/dispatch/status', 'Api\V1\UrbanGoodz\OrderAiDispatchController@dispatchStatus');
});

Route::group(['prefix' => 'order-anywhere/admin', 'middleware' => ['auth:admin', 'throttle:60,1']], function () {
    Route::get('requests', 'Api\V1\OrderAnywhereController@adminRequests');
    Route::post('requests/{record}/status', 'Api\V1\OrderAnywhereController@updateStatus');
    Route::post('requests/{record}/notes', 'Api\V1\OrderAnywhereController@addNotes');
    Route::post('requests/{record}/assign-driver', 'Api\V1\OrderAnywhereController@assignDriver');
    Route::post('requests/{record}/payment-link', 'Api\V1\OrderAnywhereController@createPaymentLink');
    Route::get('pending-orders', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@pendingOrders');
    Route::post('orders/{orderId}/dispatch/trigger-nearest', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@triggerNearestDriver');
    Route::post('orders/{orderId}/dispatch/assign', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@assignDriver');
    Route::post('dispatches/{dispatchId}/cancel', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@cancelDispatch');
    Route::get('dispatches', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@getAllDispatches');
    Route::get('dispatches/{dispatchId}', 'Api\V1\UrbanGoodz\OrderAiDispatchAdminController@getDispatchDetail');
});

Route::group(['prefix' => 'order-anywhere/vendor', 'middleware' => ['vendor.api', 'throttle:60,1']], function () {
    Route::post('requests/{record}/update', 'Api\V1\OrderAnywhereController@vendorUpdate');
    Route::get('orders', 'Api\V1\UrbanGoodz\OrderAiDispatchVendorController@orders');
    Route::get('orders/{orderId}', 'Api\V1\UrbanGoodz\OrderAiDispatchVendorController@orderDetail');
    Route::get('dispatches', 'Api\V1\UrbanGoodz\OrderAiDispatchVendorController@dispatches');
});

Route::group(['prefix' => 'order-anywhere/driver', 'middleware' => ['dm.api', 'throttle:60,1']], function () {
    Route::get('available', 'Api\V1\OrderAnywhereController@driverAvailable');
    Route::post('{record}/accept', 'Api\V1\OrderAnywhereController@driverAccept');
    Route::post('{record}/status', 'Api\V1\OrderAnywhereController@driverStatus');
    Route::post('{record}/issue', 'Api\V1\OrderAnywhereController@driverIssue');
});

Route::group(['prefix' => 'urban-goodz/fashion-fit'], function () {
    Route::post('photos/upload', 'Api\V1\UrbanGoodz\FashionFitFileController@uploadPhoto')->middleware('auth:api');
});

Route::group(['prefix' => 'urban-goodz/files', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('upload/{category}', 'Api\V1\UrbanGoodz\UrbanGoodzFileUploadController@upload');
});

Route::group(['prefix' => 'urban-goodz/ai-concierge', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('query', 'Api\V1\UrbanGoodz\UrbanGoodzAIConciergeController@query');
    Route::post('chat', 'Api\V1\UrbanGoodz\UrbanGoodzAIConciergeController@query');
    Route::get('history', 'Api\V1\UrbanGoodz\UrbanGoodzAIConciergeController@history');
});

Route::group(['prefix' => 'urban-goodz/notifications/ai', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('generate', 'Api\V1\UrbanGoodz\NotificationAIController@generateNotification');
    Route::post('batch', 'Api\V1\UrbanGoodz\NotificationAIController@generateBatch');
    Route::get('history', 'Api\V1\UrbanGoodz\NotificationAIController@history');
    Route::post('mark-read', 'Api\V1\UrbanGoodz\NotificationAIController@markAsRead');
    Route::post('preview', 'Api\V1\UrbanGoodz\NotificationAIController@previewTemplate');
});

// Driver API - dedicated routes, package scanning, earnings, payouts
Route::group(['prefix' => 'urban-goodz/driver', 'middleware' => 'dm.api'], function () {
    Route::get('routes', 'Api\UrbanGoodzDriverApiController@assignedRoutes');
    Route::get('routes/{routeId}', 'Api\UrbanGoodzDriverApiController@routeDetail');
    Route::post('routes/{routeId}/sequence', 'Api\UrbanGoodzDriverApiController@resequenceRoute');

    // Driver sets where they want to finish, then the run is sorted for them.
    // Drivers are paid per package rather than per mile, so the finish point
    // is theirs to choose -- the extra mileage is their own trade.
    // Uses the same solver as the admin Optimize button, which persists the
    // order; the older driver-side optimiser did not.
    Route::get('routes-awaiting-finish', 'Api\UrbanGoodzDriverRouteFinishController@index');
    // Scanned stack -> runnable route. Comes back unsorted on purpose;
    // choosing the finish below is what sequences it.
    Route::post('routes/from-batch', 'Api\UrbanGoodzDriverRouteFinishController@buildFromBatch');
    Route::post('routes/{route}/finish', 'Api\UrbanGoodzDriverRouteFinishController@finish');
    Route::post('routes/{routeId}/started', 'Api\UrbanGoodzDriverApiController@routeStarted');
    Route::post('routes/{routeId}/completed', 'Api\UrbanGoodzDriverApiController@routeCompleted');
    Route::post('routes/{routeId}/scan-pickup', 'Api\UrbanGoodzDriverApiController@scanPickup');
    Route::post('routes/{routeId}/scan-dropoff', 'Api\UrbanGoodzDriverApiController@scanDropoff');
    Route::post('routes/{routeId}/scan-exception', 'Api\UrbanGoodzDriverApiController@scanException');
    Route::post('routes/{routeId}/package-events', 'Api\UrbanGoodzDriverPackageScanController@store');
    Route::post('routes/{routeId}/package-group-events', 'Api\UrbanGoodzDriverPackageScanController@storeGroup');
    Route::get('routes/{routeId}/package-events', 'Api\UrbanGoodzDriverPackageScanController@history');
    Route::post('package-events/sync', 'Api\UrbanGoodzDriverPackageScanController@sync');
    Route::post('routes/{routeId}/age-verify', 'Api\UrbanGoodzDriverApiController@submitAgeVerification');
    Route::post('routes/{routeId}/age-refuse', 'Api\UrbanGoodzDriverApiController@submitAgeRefusal');
    Route::get('routes/{routeId}/age-status', 'Api\UrbanGoodzDriverApiController@checkAgeStatus');
    Route::get('earnings', 'Api\UrbanGoodzDriverApiController@earnings');
    // What each cash-out option costs, before committing to one. Weekly is
    // always free; same-day carries a configurable fee.
    Route::get('payout-options', 'Api\UrbanGoodzDriverApiController@payoutOptions');
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
    Route::post('business-jobs/{jobId}/arrived-pickup', 'Api\UrbanGoodzDriverBusinessCourierController@markArrivedPickup');
    Route::post('business-jobs/{jobId}/pickup', 'Api\UrbanGoodzDriverBusinessCourierController@markPickup');
    Route::post('business-jobs/{jobId}/delivery', 'Api\UrbanGoodzDriverBusinessCourierController@markDelivery');
    Route::post('business-jobs/{jobId}/fail-delivery', 'Api\UrbanGoodzDriverBusinessCourierController@failDelivery');
    Route::post('business-jobs/{jobId}/proof-pickup', 'Api\UrbanGoodzDriverBusinessCourierController@submitPickupProof');
    Route::post('business-jobs/{jobId}/proof-delivery', 'Api\UrbanGoodzDriverBusinessCourierController@submitDeliveryProof');
    Route::post('business-jobs/{jobId}/exception', 'Api\UrbanGoodzDriverBusinessCourierController@reportException');

    // Driver load board browsing and bidding
    Route::get('load-board', 'Api\UrbanGoodzDriverApiController@loadBoardAvailable');
    Route::get('load-board/{loadId}', 'Api\UrbanGoodzDriverApiController@loadBoardDetail');
    Route::post('load-board/{loadId}/bid', 'Api\UrbanGoodzDriverApiController@loadBoardPlaceBid');
    Route::get('my-bids', 'Api\UrbanGoodzDriverApiController@loadBoardMyBids');
    Route::post('my-bids/{bidId}/withdraw', 'Api\UrbanGoodzDriverApiController@loadBoardWithdrawBid');

    // Active jobs overview
    Route::get('active-jobs', 'Api\UrbanGoodzDriverApiController@activeJobs');

    // Load sourcing — AI recommendations
    Route::get('load-sourcing/recommendations', 'Api\UrbanGoodzDriverApiController@loadSourcingRecommendations');
    Route::get('load-sourcing/recommendations/{recommendationId}', 'Api\UrbanGoodzDriverApiController@loadSourcingDetail');
    Route::post('load-sourcing/recommendations/{recommendationId}/save', 'Api\UrbanGoodzDriverApiController@loadSourcingSave');
    Route::post('load-sourcing/recommendations/{recommendationId}/hide', 'Api\UrbanGoodzDriverApiController@loadSourcingHide');
    Route::post('load-sourcing/recommendations/{recommendationId}/interest', 'Api\UrbanGoodzDriverApiController@loadSourcingExpressInterest');
    Route::post('load-sourcing/recommendations/{recommendationId}/handoff', 'Api\UrbanGoodzDriverApiController@loadSourcingHandoff');
    Route::post('load-sourcing/confirm-booking/{referralId}', 'Api\UrbanGoodzDriverApiController@loadSourcingConfirmBooking');
    Route::put('load-sourcing/preferences', 'Api\UrbanGoodzDriverApiController@loadSourcingUpdatePreferences');
    Route::get('load-sourcing/available-external', 'Api\UrbanGoodzDriverApiController@loadSourcingAvailableExternal');
    Route::post('load-sourcing/share-external', 'Api\UrbanGoodzDriverApiController@loadSourcingShareExternal');

    // Driver Order Anywhere purchase card
    Route::get('order-anywhere/{requestId}/purchase-card', 'Api\V1\UrbanGoodzDriverPurchaseCardController@getCard');
    Route::post('order-anywhere/{requestId}/purchase-card/authorize', 'Api\V1\UrbanGoodzDriverPurchaseCardController@authorizePurchase');
    Route::post('order-anywhere/{requestId}/purchase-card/complete', 'Api\V1\UrbanGoodzDriverPurchaseCardController@completePurchase');
    Route::post('order-anywhere/{requestId}/purchase-card/receipt', 'Api\V1\UrbanGoodzDriverPurchaseCardController@uploadReceipt');
    Route::post('order-anywhere/{requestId}/purchase-card/failure', 'Api\V1\UrbanGoodzDriverPurchaseCardController@reportFailure');
    Route::post('order-anywhere/{requestId}/purchase-card/secure-reveal', 'Api\V1\UrbanGoodzDriverPurchaseCardController@secureReveal')
        ->middleware('throttle:10,1');

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

    // Driver AI dispatches (AiDispatch lifecycle)
    Route::get('ai-dispatches', 'Api\UrbanGoodzDriverDispatchController@index');
    Route::get('ai-dispatches/{dispatch}', 'Api\UrbanGoodzDriverDispatchController@show');
    Route::post('ai-dispatches/{dispatch}/accept', 'Api\UrbanGoodzDriverDispatchController@accept');
    Route::post('ai-dispatches/{dispatch}/decline', 'Api\UrbanGoodzDriverDispatchController@decline');
    Route::get('ai-dispatches/{dispatch}/route-guidance', 'Api\UrbanGoodzDriverDispatchController@routeGuidance');
    Route::post('ai-dispatches/{dispatch}/exceptions', 'Api\UrbanGoodzDriverDispatchController@reportException');
    Route::post('ai-dispatches/{dispatch}/deliver', 'Api\UrbanGoodzDriverDispatchController@markDelivered');
    Route::get('ai-performance-summary', 'Api\UrbanGoodzDriverDispatchController@performanceSummary');
    });

    // Driver AI
    Route::group(['prefix' => 'ai', 'middleware' => 'dm.api'], function () {
        Route::get('route-optimization', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@optimizeRoute');
        Route::get('earnings-comparison', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@earningsComparison');
        Route::get('load-recommendations', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@loadRecommendations');
        Route::post('verify-pickup', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@verifyPickup');
        Route::post('verify-delivery', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@verifyDelivery');
        Route::post('exception', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@handleException');
        Route::get('warnings', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@getWarnings');
        Route::get('earnings-per-hour', 'Api\V1\UrbanGoodz\UrbanGoodzDriverAIController@earningsPerHour');
    });

    // Dispatcher AI
    Route::group(['prefix' => 'urban-goodz/dispatcher/ai', 'middleware' => ['auth:admin', 'throttle:60,1']], function () {
        Route::post('load-ranking', 'Api\V1\Dispatcher\DispatcherAIController@rankLoads');
        Route::post('driver-match', 'Api\V1\Dispatcher\DispatcherAIController@matchDriver');
        Route::post('rate-estimate', 'Api\V1\Dispatcher\DispatcherAIController@estimateRate');
        Route::post('duplicate-check', 'Api\V1\Dispatcher\DispatcherAIController@checkDuplicates');
        Route::get('ops-summary', 'Api\V1\Dispatcher\DispatcherAIController@opsSummary');
        Route::post('parse-load', 'Api\V1\Dispatcher\DispatcherAIController@parseLoad');
        Route::post('parse-email', 'Api\V1\Dispatcher\DispatcherAIController@parseEmail');
        Route::post('parse-batch', 'Api\V1\Dispatcher\DispatcherAIController@parseBatch');
        Route::get('source-status', 'Api\V1\Dispatcher\DispatcherAIController@sourceStatus');
        Route::post('sync-source', 'Api\V1\Dispatcher\DispatcherAIController@syncSource');
    });

    // Rental AI
    Route::group(['prefix' => 'urban-goodz/rentals/ai', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
        Route::post('search', 'Api\V1\UrbanGoodz\RentalAIController@searchAssets');
        Route::post('match', 'Api\V1\UrbanGoodz\RentalAIController@matchAssets');
        Route::post('availability', 'Api\V1\UrbanGoodz\RentalAIController@checkAvailability');
        Route::post('quote', 'Api\V1\UrbanGoodz\RentalAIController@getQuote');
        Route::post('extension', 'Api\V1\UrbanGoodz\RentalAIController@extendRental');
        Route::post('late-return', 'Api\V1\UrbanGoodz\RentalAIController@handleLateReturn');
        Route::post('damage-report', 'Api\V1\UrbanGoodz\RentalAIController@reportDamage');
        Route::post('return-inspection', 'Api\V1\UrbanGoodz\RentalAIController@inspectReturn');
    });

    // Support AI
    Route::group(['prefix' => 'urban-goodz/support/ai', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
        Route::post('classify', 'Api\V1\UrbanGoodz\SupportAIController@classifyIssue');
        Route::post('auto-resolve', 'Api\V1\UrbanGoodz\SupportAIController@attemptAutoResolution');
        Route::post('escalate', 'Api\V1\UrbanGoodz\SupportAIController@escalateToHuman');
        Route::get('knowledge-base', 'Api\V1\UrbanGoodz\SupportAIController@searchKnowledgeBase');
        Route::post('feedback', 'Api\V1\UrbanGoodz\SupportAIController@submitFeedback');
    });

    // Fraud Detection AI
    Route::group(['prefix' => 'urban-goodz/fraud/ai', 'middleware' => ['auth:admin', 'throttle:60,1']], function () {
        Route::post('scan-transaction', 'Api\V1\UrbanGoodz\FraudDetectionController@scanTransaction');
        Route::post('scan-account', 'Api\V1\UrbanGoodz\FraudDetectionController@scanAccount');
        Route::get('flags', 'Api\V1\UrbanGoodz\FraudDetectionController@getFlags');
        Route::post('review', 'Api\V1\UrbanGoodz\FraudDetectionController@reviewFlag');
        Route::get('risk-score/{entity_type}/{entity_id}', 'Api\V1\UrbanGoodz\FraudDetectionController@getRiskScore');
        Route::get('dashboard', 'Api\V1\UrbanGoodz\FraudDetectionController@getDashboard');
    });

    // ETA Prediction AI
    Route::group(['prefix' => 'urban-goodz/eta/ai', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
        Route::post('predict', 'Api\V1\UrbanGoodz\ETAPredictionController@predictETA');
        Route::post('batch-predict', 'Api\V1\UrbanGoodz\ETAPredictionController@batchPredict');
        Route::get('driver/{driver_id}', 'Api\V1\UrbanGoodz\ETAPredictionController@getDriverETA');
        Route::get('order/{order_id}', 'Api\V1\UrbanGoodz\ETAPredictionController@getOrderETA');
    });

    // Dynamic Pricing AI
    Route::group(['prefix' => 'urban-goodz/pricing/ai', 'middleware' => ['vendor.api', 'actch:vendor_app', 'throttle:60,1']], function () {
        Route::post('recommend', 'Api\V1\UrbanGoodz\DynamicPricingController@recommendPrices');
        Route::post('simulate', 'Api\V1\UrbanGoodz\DynamicPricingController@simulatePriceChange');
        Route::get('history', 'Api\V1\UrbanGoodz\DynamicPricingController@getPriceHistory');
        Route::post('rollback', 'Api\V1\UrbanGoodz\DynamicPricingController@rollbackPrice');
});

    // Cross-App AI. Each role is protected by its own authentication guard.
    // Business and dispatcher contracts remain on routes/business.php until a
    // dedicated token guard exists; never expose them through customer Passport.
    Route::group(['prefix' => 'urban-goodz/cross-app/ai', 'middleware' => ['throttle:120,1']], function () {
        Route::group(['prefix' => 'customer', 'middleware' => ['auth:api']], function () {
            Route::post('query', 'Api\V1\UrbanGoodz\CrossAppAIController@customerQuery');
            Route::get('history', 'Api\V1\UrbanGoodz\CrossAppAIController@customerHistory');
            Route::post('fashion-fit/measurements', 'Api\V1\UrbanGoodz\CrossAppAIController@fashionFitMeasurements');
            Route::post('order-anywhere', 'Api\V1\UrbanGoodz\CrossAppAIController@orderAnywhere');
            Route::post('smart-reorder', 'Api\V1\UrbanGoodz\CrossAppAIController@smartReorder');
            Route::post('delivery-eta', 'Api\V1\UrbanGoodz\CrossAppAIController@deliveryETA');
        });

        Route::group(['prefix' => 'vendor', 'middleware' => ['vendor.api', 'actch:vendor_app']], function () {
            Route::get('daily-brief', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorDailyBrief');
            Route::post('order-summary', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorOrderSummary');
            Route::get('alerts', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorAlerts');
            Route::get('performance', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorPerformance');
            Route::get('pricing', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorPricing');
            Route::get('promotions', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorPromotions');
            Route::post('prep-time', 'Api\V1\UrbanGoodz\CrossAppAIController@vendorPrepTime');
        });

        Route::group(['prefix' => 'driver', 'middleware' => ['dm.api']], function () {
            Route::get('daily-summary', 'Api\V1\UrbanGoodz\CrossAppAIController@driverDailySummary');
            Route::post('route-optimization', 'Api\V1\UrbanGoodz\CrossAppAIController@driverRouteOptimization');
            Route::post('verify-package', 'Api\V1\UrbanGoodz\CrossAppAIController@driverVerifyPackage');
            Route::post('verify-delivery', 'Api\V1\UrbanGoodz\CrossAppAIController@driverVerifyDelivery');
        });

        // Digital Human Platform
        Route::post('digital-human/state', 'Api\V1\UrbanGoodz\DigitalHumanController@getState');
        Route::post('digital-human/visemes', 'Api\V1\UrbanGoodz\DigitalHumanController@getVisemes');

        // Real, paid external TTS calls -- authenticated and separately
        // throttled so an open text field can't run up the ElevenLabs bill.
        Route::group(['middleware' => ['auth:api', 'throttle:20,1']], function () {
            Route::post('digital-human/speak', 'Api\V1\UrbanGoodz\DigitalHumanController@speak');
        });
    });

// Urban Goodz Stranded -- "Never Stay Stranded Again."
// Roadside assistance is a service category here, not the product name.
//
// The catalogue is intentionally public: the Services card must be able to
// show available help before asking someone stranded on a shoulder to log in.
Route::get('urban-goodz/stranded/services', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@services')->middleware('throttle:60,1');

// Website lead capture. The marketing site is a static build with no server
// runtime, so signups post here directly. Public by design (pre-signup),
// throttled, and honeypot-guarded in the controller.
Route::post('urban-goodz/waitlist', 'Api\V1\UrbanGoodz\UrbanGoodzWaitlistController@store')
    ->middleware('throttle:20,1');

// Safety terms and the identity-privacy explanation are readable before sign
// up: people are entitled to know what is asked of them, and what happens to
// their licence, before they hand it over.
Route::get('urban-goodz/stranded/safety/documents', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedSafetyController@documents')->middleware('throttle:60,1');

Route::group(['prefix' => 'urban-goodz/stranded', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    // Identity verification and consent. Both the person asking for help and
    // the person providing it must clear this before taking part.
    Route::get('safety/status', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedSafetyController@status');
    Route::post('safety/accept', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedSafetyController@accept');
    Route::post('safety/verification', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedSafetyController@submitVerification');

    // Responder side. Presence is the heartbeat that makes "nearby" mean
    // anything; accepting shortlists, it does not win the job.
    Route::post('responder/presence', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@presence');
    Route::post('responder/profile', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@updateProfile');
    Route::post('responder/safety-acknowledge', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@acknowledgeSafety');
    Route::get('responder/offers', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@offers');
    Route::post('responder/offers/{offer}/accept', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@accept');
    Route::post('responder/offers/{offer}/decline', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@decline');
    // The one rescue this responder is currently assigned to, with full
    // identifying detail -- withheld entirely until they are selected.
    Route::get('responder/assignment', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedResponderController@activeAssignment');

    Route::post('requests', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@store');
    // Sends the request to nearby responders. Once a payment provider exists
    // this belongs on the payment webhook rather than the client.
    Route::post('requests/{record}/broadcast', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@broadcast');
    Route::get('requests/{record}', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@show');
    // The responders willing to help. Accepting shortlists a responder; the
    // customer's selection is what actually assigns the job.
    Route::get('requests/{record}/offers', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@offers');
    Route::post('requests/{record}/offers/{offer}/select', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@selectOffer');
    // Lifecycle: en_route, nearby, delayed, arrived, started, completed,
    // confirmed. Each transition notifies whoever is waiting on it, and
    // `confirmed` is what releases escrow.
    Route::post('requests/{record}/status', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@updateStatus');
    // Messaging between the two parties. Opens when a responder is selected,
    // closes when the job ends. A message can carry a precise coordinate or a
    // photo, which is what actually solves "I cannot find you".
    Route::get('requests/{record}/messages', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedMessageController@index');
    Route::post('requests/{record}/messages', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedMessageController@store');

    Route::post('requests/{record}/cancel', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@cancel');

    // Safety reporting is available at any point in the request, not only
    // after completion -- either side can flag a concern mid-assist.
    Route::post('requests/{record}/report', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@report');
    // Each side rates the other once, after the assist ends.
    Route::post('requests/{record}/rate', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@rate');
});

// Vendor cash-out. Balance is read from store_wallets using the platform's
// own formula, so a vendor sees the same number here as everywhere else.
Route::group(['prefix' => 'urban-goodz/vendor', 'middleware' => ['vendor.api', 'throttle:60,1']], function () {
    Route::get('payout-options', 'Api\V1\UrbanGoodz\UrbanGoodzVendorPayoutController@options');
    Route::post('payout-request', 'Api\V1\UrbanGoodz\UrbanGoodzVendorPayoutController@request_');
});

// Stranded: pay the help request fee (which triggers the broadcast), and
// live-track the responder who is on the way.
Route::group(['prefix' => 'urban-goodz/stranded', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('requests/{record}/pay-fee', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedController@payFee');
    Route::get('requests/{record}/track', 'Api\V1\UrbanGoodz\UrbanGoodzStrandedTrackingController@track');
});

// Community: groups are real delivery zones plus Nationwide/Worldwide, not a
// stored table. Reading is public so people can browse before joining;
// posting requires an account.
Route::group(['prefix' => 'urban-goodz/community', 'middleware' => ['throttle:60,1']], function () {
    Route::get('groups', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@groups');
    Route::get('posts', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@posts');
    Route::get('posts/{post}', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@showPost');
    Route::get('marketplace', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@marketplaceItems');
});

Route::group(['prefix' => 'urban-goodz/community', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('posts', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@storePost');
    Route::post('posts/{post}/comments', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@storeComment');
    Route::post('marketplace', 'Api\V1\UrbanGoodz\UrbanGoodzCommunityController@storeMarketplaceItem');
});

// Creator Space (authenticated creator self-service)
Route::group(['prefix' => 'urban-goodz/creator-space', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::post('register', 'Api\V1\CreatorSpaceController@register');
    Route::get('profile', 'Api\V1\CreatorSpaceController@profile');
    Route::put('profile', 'Api\V1\CreatorSpaceController@updateProfile');
    Route::post('profile/avatar', 'Api\V1\CreatorSpaceController@uploadAvatar');
    Route::post('profile/banner', 'Api\V1\CreatorSpaceController@uploadBanner');
    Route::get('verification', 'Api\V1\CreatorSpaceController@verificationStatus');
    Route::post('verification/submit', 'Api\V1\CreatorSpaceController@submitVerification');
    Route::get('reels', 'Api\V1\CreatorSpaceController@myReels');
    Route::post('reels', 'Api\V1\CreatorSpaceController@uploadReel');
    Route::put('reels/{id}', 'Api\V1\CreatorSpaceController@updateReel');
    Route::delete('reels/{id}', 'Api\V1\CreatorSpaceController@deleteReel');
    Route::post('reels/{id}/tags', 'Api\V1\CreatorSpaceController@addReelTags');
    Route::delete('reels/{id}/tags/{tagId}', 'Api\V1\CreatorSpaceController@removeReelTag');
    Route::get('storefront', 'Api\V1\CreatorSpaceController@storefront');
    Route::get('campaigns', 'Api\V1\CreatorSpaceController@browseCampaigns');
    Route::post('campaigns/{id}/apply', 'Api\V1\CreatorSpaceController@applyCampaign');
    Route::get('campaigns/my', 'Api\V1\CreatorSpaceController@myCampaigns');
    Route::post('campaigns/{id}/deliverable', 'Api\V1\CreatorSpaceController@submitDeliverable');
    Route::get('earnings', 'Api\V1\CreatorSpaceController@earnings');
    Route::get('analytics', 'Api\V1\CreatorSpaceController@analytics');
    Route::get('payout-status', 'Api\V1\CreatorSpaceController@payoutStatus');
});

// Creator Discovery (shopper-facing)
Route::group(['prefix' => 'urban-goodz/creators', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('/', 'Api\V1\CreatorDiscoveryController@index');
    Route::get('{handle}', 'Api\V1\CreatorDiscoveryController@show');
    Route::get('{handle}/reels', 'Api\V1\CreatorDiscoveryController@creatorReels');
    Route::get('{handle}/storefront', 'Api\V1\CreatorDiscoveryController@creatorStorefront');
    Route::post('{handle}/follow', 'Api\V1\CreatorDiscoveryController@follow');
    Route::delete('{handle}/follow', 'Api\V1\CreatorDiscoveryController@unfollow');
    Route::post('{handle}/report', 'Api\V1\CreatorDiscoveryController@reportCreator');
    Route::post('{handle}/block', 'Api\V1\CreatorDiscoveryController@blockCreator');
});

// Reel Social (engagement)
Route::group(['prefix' => 'urban-goodz/reels', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('{id}/comments', 'Api\V1\ReelSocialController@comments');
    Route::post('{id}/comments', 'Api\V1\ReelSocialController@postComment');
    Route::post('{id}/comments/{commentId}/reply', 'Api\V1\ReelSocialController@postReply');
    Route::delete('comments/{commentId}', 'Api\V1\ReelSocialController@deleteComment');
    Route::post('{id}/save', 'Api\V1\ReelSocialController@saveReel');
    Route::delete('{id}/save', 'Api\V1\ReelSocialController@unsaveReel');
    Route::post('{id}/share', 'Api\V1\ReelSocialController@shareReel');
    Route::post('{id}/report', 'Api\V1\ReelSocialController@reportReel');
    Route::get('{id}/tags', 'Api\V1\ReelSocialController@reelTags');
});

// Events Marketplace (expanded - replaces old events group)
Route::group(['prefix' => 'urban-goodz/events-marketplace', 'middleware' => ['auth:api', 'throttle:60,1']], function () {
    Route::get('/', 'Api\V1\EventMarketplaceController@index');
    Route::get('categories', 'Api\V1\EventMarketplaceController@categories');
    Route::get('saved', 'Api\V1\EventMarketplaceController@savedEvents');
    Route::post('/', 'Api\V1\EventMarketplaceController@store');
    Route::get('{id}', 'Api\V1\EventMarketplaceController@show');
    Route::put('{id}', 'Api\V1\EventMarketplaceController@update');
    Route::delete('{id}', 'Api\V1\EventMarketplaceController@cancel');
    Route::post('{id}/save', 'Api\V1\EventMarketplaceController@saveEvent');
    Route::delete('{id}/save', 'Api\V1\EventMarketplaceController@unsaveEvent');
    Route::post('{id}/share', 'Api\V1\EventMarketplaceController@shareEvent');
    Route::post('{id}/remind', 'Api\V1\EventMarketplaceController@setReminder');
    Route::delete('{id}/remind', 'Api\V1\EventMarketplaceController@removeReminder');
    Route::post('{id}/interest', 'Api\V1\EventMarketplaceController@expressInterest');
    Route::post('{id}/report', 'Api\V1\EventMarketplaceController@reportEvent');
});
