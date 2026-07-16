<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AiOperationsController;

Route::group([
    'prefix' => 'urban-goodz/ai-operations',
    'as' => 'admin.urban-goodz.ai-operations.',
], function () {
    Route::get('/', [AiOperationsController::class, 'index'])->name('index');
    Route::get('feature-controls', [AiOperationsController::class, 'featureControls'])->name('feature-controls');
    Route::post('feature-controls', [AiOperationsController::class, 'featureControls']);
    Route::get('logs', [AiOperationsController::class, 'logs'])->name('logs');
    Route::get('usage', [AiOperationsController::class, 'usage'])->name('usage');
    Route::get('test', [AiOperationsController::class, 'testEndpoint'])->name('test');
    Route::post('test', [AiOperationsController::class, 'testEndpoint']);
    Route::get('load-sourcing', [AiOperationsController::class, 'getLoadSourcingStatus'])->name('load-sourcing');
});
