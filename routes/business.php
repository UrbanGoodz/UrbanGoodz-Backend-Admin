<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessAuthController;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessPortalController;
use App\Http\Controllers\Admin\UrbanGoodz\DispatcherPortalController;

Route::group(['prefix' => 'business', 'as' => 'business.'], function () {

    Route::get('login', [BusinessAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [BusinessAuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [BusinessAuthController::class, 'logout'])->name('logout');

    Route::middleware(['business'])->group(function () {
        Route::get('dashboard', [BusinessPortalController::class, 'dashboard'])->name('dashboard');

        Route::get('routes', [BusinessPortalController::class, 'routes'])->name('routes.index');
        Route::get('routes/create', [BusinessPortalController::class, 'routeCreate'])->name('routes.create');
        Route::post('routes', [BusinessPortalController::class, 'routeStore'])->name('routes.store');
        Route::get('routes/{id}', [BusinessPortalController::class, 'routeShow'])->name('routes.show');
        Route::get('routes/{id}/edit', [BusinessPortalController::class, 'routeEdit'])->name('routes.edit');
        Route::post('routes/{id}/update', [BusinessPortalController::class, 'routeUpdate'])->name('routes.update');
        Route::post('routes/{id}/delete', [BusinessPortalController::class, 'routeDestroy'])->name('routes.destroy');
        Route::get('routes/{id}/packages', [BusinessPortalController::class, 'routePackages'])->name('routes.packages');
        Route::get('routes/{id}/packages/create', [BusinessPortalController::class, 'routePackageCreate'])->name('routes.packages.create');
        Route::post('routes/{id}/packages', [BusinessPortalController::class, 'routePackageStore'])->name('routes.packages.store');
        Route::get('routes/{id}/packages/upload', [BusinessPortalController::class, 'routePackageUpload'])->name('routes.packages.upload');
        Route::post('routes/{id}/packages/upload', [BusinessPortalController::class, 'routePackageBulkStore'])->name('routes.packages.bulk-store');
        Route::post('routes/{id}/optimize', [BusinessPortalController::class, 'routeOptimize'])->name('routes.optimize');

        Route::get('packages/scan', [BusinessPortalController::class, 'scanPackages'])->name('packages.scan');
        Route::post('packages/scan', [BusinessPortalController::class, 'scanStore'])->name('packages.scan.store');
        Route::get('packages/pool', [BusinessPortalController::class, 'packagePool'])->name('packages.pool');
        Route::post('packages/{id}/assign', [BusinessPortalController::class, 'assignPackageToRoute'])->name('packages.assign');

        Route::get('locations', [BusinessPortalController::class, 'locations'])->name('locations.index');
        Route::get('locations/create', [BusinessPortalController::class, 'locationCreate'])->name('locations.create');
        Route::post('locations', [BusinessPortalController::class, 'locationStore'])->name('locations.store');
        Route::get('locations/{id}/edit', [BusinessPortalController::class, 'locationEdit'])->name('locations.edit');
        Route::post('locations/{id}/update', [BusinessPortalController::class, 'locationUpdate'])->name('locations.update');
        Route::post('locations/{id}/deactivate', [BusinessPortalController::class, 'locationDeactivate'])->name('locations.deactivate');

        Route::get('users', [BusinessPortalController::class, 'users'])->name('users.index');
        Route::get('users/create', [BusinessPortalController::class, 'userCreate'])->name('users.create');
        Route::post('users', [BusinessPortalController::class, 'userStore'])->name('users.store');
        Route::get('users/{id}/edit', [BusinessPortalController::class, 'userEdit'])->name('users.edit');
        Route::post('users/{id}/update', [BusinessPortalController::class, 'userUpdate'])->name('users.update');
        Route::post('users/{id}/deactivate', [BusinessPortalController::class, 'userDeactivate'])->name('users.deactivate');

        Route::get('documents', [BusinessPortalController::class, 'documents'])->name('documents.index');
        Route::get('documents/create', [BusinessPortalController::class, 'documentCreate'])->name('documents.create');
        Route::post('documents', [BusinessPortalController::class, 'documentStore'])->name('documents.store');
        Route::get('documents/{id}/download', [BusinessPortalController::class, 'documentDownload'])->name('documents.download');
        Route::post('documents/{id}/delete', [BusinessPortalController::class, 'documentDelete'])->name('documents.delete');

        Route::get('profile', [BusinessPortalController::class, 'profile'])->name('profile');
        Route::post('profile', [BusinessPortalController::class, 'profileUpdate'])->name('profile.update');
        Route::post('profile/password', [BusinessPortalController::class, 'passwordChange'])->name('profile.password');

        Route::get('invoices', [BusinessPortalController::class, 'invoices'])->name('invoices.index');
        Route::get('invoices/{id}', [BusinessPortalController::class, 'invoiceShow'])->name('invoices.show');

        Route::get('manifests', [BusinessPortalController::class, 'manifests'])->name('manifests.index');
        Route::get('manifests/create', [BusinessPortalController::class, 'manifestCreate'])->name('manifests.create');
        Route::post('manifests', [BusinessPortalController::class, 'manifestStore'])->name('manifests.store');
        Route::get('manifests/{id}', [BusinessPortalController::class, 'manifestShow'])->name('manifests.show');
        Route::get('manifests/{id}/scan', [BusinessPortalController::class, 'manifestScan'])->name('manifests.scan');
        Route::get('manifests/{id}/packages', [BusinessPortalController::class, 'manifestPackages'])->name('manifests.packages');
    });

    Route::middleware(['business', 'dispatcher', 'dispatch-territory'])->group(function () {
        Route::prefix('dispatcher')->name('dispatcher.')->group(function () {
            Route::get('/dashboard', [DispatcherPortalController::class, 'dashboard'])->name('dashboard');

            Route::get('/loads', [DispatcherPortalController::class, 'loads'])->name('loads');
            Route::get('/loads/{id}', [DispatcherPortalController::class, 'showLoad'])->name('loads.show');
            Route::post('/loads/{id}/assign-driver', [DispatcherPortalController::class, 'assignDriver'])->name('loads.assign-driver');
            Route::patch('/loads/{id}/status', [DispatcherPortalController::class, 'updateLoadStatus'])->name('loads.status');

            Route::get('/drivers', [DispatcherPortalController::class, 'drivers'])->name('drivers');

            Route::get('/commissions', [DispatcherPortalController::class, 'commissions'])->name('commissions');

            Route::get('/territory', [DispatcherPortalController::class, 'territory'])->name('territory');
            Route::post('/territory', [DispatcherPortalController::class, 'updateTerritory'])->name('territory.update');

            Route::get('/users', [DispatcherPortalController::class, 'users'])->name('users');
            Route::get('/users/create', [DispatcherPortalController::class, 'createUser'])->name('users.create');
            Route::post('/users', [DispatcherPortalController::class, 'storeUser'])->name('users.store');
            Route::get('/users/{id}/edit', [DispatcherPortalController::class, 'editUser'])->name('users.edit');
            Route::post('/users/{id}/update', [DispatcherPortalController::class, 'updateUser'])->name('users.update');
            Route::post('/users/{id}/deactivate', [DispatcherPortalController::class, 'deactivateUser'])->name('users.deactivate');
        });
    });
});
