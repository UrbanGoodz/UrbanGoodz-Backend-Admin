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

    Route::group(['prefix' => 'workforce', 'as' => 'workforce.'], function () {
        Route::get('/', [AiOperationsController::class, 'workforceOverview'])->name('index');
        Route::get('agents', [AiOperationsController::class, 'agents'])->name('agents');
        Route::get('tasks', [AiOperationsController::class, 'tasks'])->name('tasks');
        Route::get('actions', [AiOperationsController::class, 'workforceActions'])->name('actions');
        Route::get('approvals', [AiOperationsController::class, 'approvals'])->name('approvals');
        Route::get('prospects', [AiOperationsController::class, 'prospects'])->name('prospects');
        Route::get('business-needs', [AiOperationsController::class, 'businessNeeds'])->name('business-needs');
        Route::get('human-action-items', [AiOperationsController::class, 'humanActionItems'])->name('human-action-items');
        Route::get('briefs', [AiOperationsController::class, 'briefs'])->name('briefs');
        Route::get('settings', [AiOperationsController::class, 'settings'])->name('settings');
        Route::post('settings', [AiOperationsController::class, 'updateSettings'])->name('settings.update');
    });
});

Route::get('urban-goodz/ai-chief-of-staff', [AiOperationsController::class, 'chiefOfStaff'])->name('admin.urban-goodz.ai-chief-of-staff');
