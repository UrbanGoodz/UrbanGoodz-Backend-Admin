<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessAuthController;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessPortalController;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessForgotPasswordController;
use App\Http\Controllers\Admin\UrbanGoodz\BusinessResetPasswordController;
use App\Http\Controllers\Admin\UrbanGoodz\DispatcherPortalController;
use App\Http\Controllers\Api\V1\Business\BusinessAIController;

Route::group(['prefix' => 'business', 'as' => 'business.'], function () {

    Route::get('login', [BusinessAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [BusinessAuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [BusinessAuthController::class, 'logout'])->name('logout');

    Route::get('forgot-password', [BusinessForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [BusinessForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('reset-password/{token}', [BusinessResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [BusinessResetPasswordController::class, 'reset'])->name('password.update');

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

        // Load Board
        Route::get('load-board', [BusinessPortalController::class, 'loadBoardIndex'])->name('load-board.index');
        Route::get('load-board/create', [BusinessPortalController::class, 'loadBoardCreate'])->name('load-board.create');
        Route::post('load-board', [BusinessPortalController::class, 'loadBoardStore'])->name('load-board.store');
        Route::get('load-board/{id}', [BusinessPortalController::class, 'loadBoardShow'])->name('load-board.show');
        Route::post('load-board/{id}/cancel', [BusinessPortalController::class, 'loadBoardCancel'])->name('load-board.cancel');

        // Business AI
        Route::prefix('ai')->name('ai.')->group(function () {
            Route::post('manifest/import', [BusinessAIController::class, 'importManifest'])->name('manifest.import');
            Route::post('manifest/validate', [BusinessAIController::class, 'validateManifest'])->name('manifest.validate');
            Route::post('manifest/duplicate-check', [BusinessAIController::class, 'checkDuplicates'])->name('manifest.duplicates');
            Route::post('packages/group', [BusinessAIController::class, 'groupPackages'])->name('packages.group');
            Route::post('route/create', [BusinessAIController::class, 'createRoute'])->name('route.create');
            Route::post('route/optimize', [BusinessAIController::class, 'optimizeRoute'])->name('route.optimize');
            Route::post('route/dedicated', [BusinessAIController::class, 'recommendDedicatedRoute'])->name('route.dedicated');
            Route::post('driver/match', [BusinessAIController::class, 'matchDriver'])->name('driver.match');
            Route::post('route/predict', [BusinessAIController::class, 'predictCompletion'])->name('route.predict');
            Route::post('route/risk', [BusinessAIController::class, 'exceptionRisk'])->name('route.risk');
            Route::get('performance', [BusinessAIController::class, 'routePerformance'])->name('performance');
            Route::get('cost-anomaly', [BusinessAIController::class, 'costAnomalyAlert'])->name('cost-anomaly');
            Route::post('invoice-support', [BusinessAIController::class, 'generateInvoiceSupport'])->name('invoice-support');
            Route::post('delivery-proof', [BusinessAIController::class, 'deliveryProofPackage'])->name('delivery-proof');
        });

        Route::get('ai-assistant', [BusinessPortalController::class, 'aiAssistant'])->name('ai-assistant');
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
