<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AiOperationsController;

Route::group([
    'prefix' => 'urban-goodz/ai-operations',
    'as' => 'admin.urban-goodz.ai-operations.',
], function () {
    Route::middleware('module:urban_goodz_ai_settings_view')->group(function () {
        Route::get('/', [AiOperationsController::class, 'index'])->name('index');
        Route::get('feature-controls', [AiOperationsController::class, 'featureControls'])->name('feature-controls');
    });

    Route::middleware('module:urban_goodz_ai_usage_view')->group(function () {
        Route::get('logs', [AiOperationsController::class, 'logs'])->name('logs');
        Route::get('usage', [AiOperationsController::class, 'usage'])->name('usage');
    });

    Route::middleware('module:urban_goodz_ai_settings_manage')->group(function () {
        Route::post('feature-controls', [AiOperationsController::class, 'featureControls'])->name('feature-controls.update');
    });

    Route::middleware('module:urban_goodz_ai_copilot_use')->group(function () {
        Route::get('test', [AiOperationsController::class, 'testEndpoint'])->name('test');
        Route::post('test', [AiOperationsController::class, 'testEndpoint'])->name('test.run');
        Route::get('load-sourcing', [AiOperationsController::class, 'getLoadSourcingStatus'])->name('load-sourcing');
    });

    Route::group([
        'prefix' => 'workforce',
        'as' => 'workforce.',
        'middleware' => 'module:urban_goodz_ai_copilot_use',
    ], function () {
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
        Route::post('settings', [AiOperationsController::class, 'updateSettings'])
            ->middleware('module:urban_goodz_ai_settings_manage')
            ->name('settings.update');
    });
});

Route::get('urban-goodz/ai-chief-of-staff', [AiOperationsController::class, 'chiefOfStaff'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff');

Route::post('urban-goodz/ai-chief-of-staff/chat', [AiOperationsController::class, 'chiefOfStaffChat'])
    ->middleware(['module:urban_goodz_ai_copilot_use', 'throttle:60,1,admin-ai-chief-of-staff-chat'])
    ->name('admin.urban-goodz.ai-chief-of-staff.chat');

// Separately (and more tightly) throttled from chat, since this hits the
// paid ElevenLabs API per call.
Route::post('urban-goodz/ai-chief-of-staff/speak', [AiOperationsController::class, 'chiefOfStaffSpeak'])
    ->middleware(['module:urban_goodz_ai_copilot_use', 'throttle:20,1,admin-ai-chief-of-staff-speak'])
    ->name('admin.urban-goodz.ai-chief-of-staff.speak');
