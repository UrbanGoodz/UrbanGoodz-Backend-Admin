<?php
use App\Http\Controllers\Api\V1\Admin\ServiceBookingController as AdminServiceBookingController;
use App\Http\Controllers\Api\V1\ServiceBookingCustomerController;
use App\Http\Controllers\Api\V1\UrbanGoodz\BookServicesAIController;
use App\Http\Controllers\Api\V1\Vendor\ServiceBookingController as VendorServiceBookingController;
use Illuminate\Support\Facades\Route;
Route::prefix('customer/service-bookings')->group(function(){
    Route::get('categories',[ServiceBookingCustomerController::class,'categories']);
    Route::get('providers',[ServiceBookingCustomerController::class,'providers']); Route::get('providers/{provider}',[ServiceBookingCustomerController::class,'provider']);
    Route::get('providers/{provider}/services/{service}/slots',[ServiceBookingCustomerController::class,'slots']);
    Route::middleware('auth:api')->group(function(){Route::get('/',[ServiceBookingCustomerController::class,'index']);Route::post('/',[ServiceBookingCustomerController::class,'store']);Route::get('{booking}',[ServiceBookingCustomerController::class,'show']);Route::post('{booking}/accept-quote',[ServiceBookingCustomerController::class,'acceptQuote']);Route::post('{booking}/payment',[ServiceBookingCustomerController::class,'pay'])->middleware('throttle:10,1');Route::post('{booking}/confirm',[ServiceBookingCustomerController::class,'confirm']);Route::post('{booking}/cancel',[ServiceBookingCustomerController::class,'cancel']);Route::post('{booking}/reschedule',[ServiceBookingCustomerController::class,'reschedule']);Route::post('{booking}/review',[ServiceBookingCustomerController::class,'review']);
        Route::get('disputes/mine',[ServiceBookingCustomerController::class,'disputes']);
        Route::post('{booking}/refund-request',[ServiceBookingCustomerController::class,'requestRefund']);});

    // Book Services AI
    Route::prefix('ai')->middleware('auth:api')->group(function () {
        Route::get('providers', [BookServicesAIController::class, 'getProviders']);
        Route::post('match', [BookServicesAIController::class, 'matchProviders']);
        Route::post('compare-quotes', [BookServicesAIController::class, 'compareQuotes']);
        Route::get('reminders', [BookServicesAIController::class, 'getReminders']);
        Route::post('verify', [BookServicesAIController::class, 'verifyCompletion']);
        Route::post('replacement', [BookServicesAIController::class, 'findReplacement']);
    });
});
Route::prefix('vendor/service-bookings')->middleware(['vendor.api','actch:vendor_app'])->group(function(){
    Route::get('profile',[VendorServiceBookingController::class,'profile']);Route::put('profile',[VendorServiceBookingController::class,'updateProfile']);Route::post('onboarding/submit',[VendorServiceBookingController::class,'submitOnboarding']);Route::get('service-areas',[VendorServiceBookingController::class,'serviceAreas']);Route::put('service-areas',[VendorServiceBookingController::class,'replaceServiceAreas']);Route::get('services',[VendorServiceBookingController::class,'services']);Route::post('services',[VendorServiceBookingController::class,'storeService']);Route::put('services/{service}',[VendorServiceBookingController::class,'updateService']);Route::delete('services/{service}',[VendorServiceBookingController::class,'deleteService']);Route::put('availability',[VendorServiceBookingController::class,'availability']);Route::get('bookings',[VendorServiceBookingController::class,'bookings']);Route::get('bookings/{booking}',[VendorServiceBookingController::class,'booking']);Route::post('bookings/{booking}/quote',[VendorServiceBookingController::class,'quote']);Route::post('bookings/{booking}/status',[VendorServiceBookingController::class,'transition']);Route::get('earnings',[VendorServiceBookingController::class,'earnings']);
    Route::get('portfolio',[VendorServiceBookingController::class,'portfolio']);
    Route::post('portfolio',[VendorServiceBookingController::class,'storePortfolioItem']);
    Route::put('portfolio/{item}',[VendorServiceBookingController::class,'updatePortfolioItem']);
    Route::delete('portfolio/{item}',[VendorServiceBookingController::class,'deletePortfolioItem']);

    // Book Services AI - Vendor
    Route::prefix('ai')->group(function () {
        Route::post('prep-time', [BookServicesAIController::class, 'estimatePrepTime']);
        Route::get('alerts', [BookServicesAIController::class, 'generateAlerts']);
        Route::get('performance', [BookServicesAIController::class, 'analyzePerformance']);
        Route::get('promotions', [BookServicesAIController::class, 'suggestPromotions']);
        Route::get('daily-brief', [BookServicesAIController::class, 'generateDailyBrief']);
    });
});
Route::prefix('admin/service-bookings')->middleware('auth:admin')->group(function(){Route::get('dashboard',[AdminServiceBookingController::class,'dashboard']);Route::get('providers',[AdminServiceBookingController::class,'providers']);Route::put('providers/{provider}/status',[AdminServiceBookingController::class,'providerStatus']);Route::get('bookings',[AdminServiceBookingController::class,'bookings']);Route::get('bookings/{booking}',[AdminServiceBookingController::class,'booking']);Route::post('bookings/{booking}/refund',[AdminServiceBookingController::class,'refund']);Route::get('earnings',[AdminServiceBookingController::class,'earnings']);Route::get('audit',[AdminServiceBookingController::class,'audit']);
    Route::get('categories',[AdminServiceBookingController::class,'categories']);
    Route::put('providers/{provider}/commission',[AdminServiceBookingController::class,'providerCommission']);
    Route::get('disputes',[AdminServiceBookingController::class,'disputes']);
    Route::post('disputes/{dispute}/resolve',[AdminServiceBookingController::class,'resolveDispute']);
    Route::post('earnings/{earning}/adjust',[AdminServiceBookingController::class,'adjustEarning']);
    Route::post('earnings/{earning}/settle',[AdminServiceBookingController::class,'settleEarning']);
    Route::post('earnings/settle-batch',[AdminServiceBookingController::class,'settleBatch']);
    Route::get('admin-audit',[AdminServiceBookingController::class,'adminAudit']);});
