<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dispatcher Load Sourcing — Admin JSON API
|--------------------------------------------------------------------------
|
| Mounted by RouteServiceProvider under the `api/v1/admin` prefix with the
| ['web', 'admin'] middleware stack, matching routes/admin_ai_operations.php.
| The admin guard is session-backed, so these endpoints authenticate with the
| same admin session cookie the panel already holds; an unauthenticated caller
| is redirected to the admin login by AdminMiddleware rather than being served
| data.
|
| These are the JSON counterparts of the HTML pages in routes/admin.php. The
| browser-facing routes there never emit JSON, and these never emit HTML.
|
*/

Route::group([
    'prefix' => 'dispatcher-sourcing',
    'as' => 'api.v1.admin.dispatcher-sourcing.',
    'middleware' => ['throttle:60,1'],
], function () {
    $controller = 'UrbanGoodz\UrbanGoodzDispatcherLoadSourcingController';

    // Reads
    Route::get('/', "{$controller}@apiDashboard")->name('dashboard');
    Route::get('best-loads', "{$controller}@bestLoads")->name('best-loads');
    Route::get('best-for-driver/{driverId}', "{$controller}@bestForDriver")->name('best-for-driver');
    Route::get('saved-searches', "{$controller}@savedSearches")->name('saved-searches');
    Route::get('driver-preferences/{driverId}', "{$controller}@driverPreferences")->name('driver-preferences');

    // Writes
    Route::post('search', "{$controller}@searchAllSources")->name('search');
    Route::post('saved-searches', "{$controller}@saveSearch")->name('save-search');
    Route::post('saved-searches/{id}/run', "{$controller}@runSavedSearch")->name('run-search');
    Route::delete('saved-searches/{id}', "{$controller}@deleteSavedSearch")->name('delete-search');
    Route::post('assign/{externalLoadId}', "{$controller}@assignLoadToDriver")->name('assign');
    Route::get('open-external/{externalLoadId}', "{$controller}@openExternalLoad")->name('open-external');
    Route::post('confirm-booking/{referralId}', "{$controller}@confirmBooking")->name('confirm-booking');
    Route::post('driver-preferences/{driverId}', "{$controller}@driverPreferences")->name('driver-preferences-update');
});
