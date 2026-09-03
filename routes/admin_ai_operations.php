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
    ->middleware(['module:urban_goodz_ai_copilot_use', 'throttle:60,1'])
    ->name('admin.urban-goodz.ai-chief-of-staff.chat');

Route::post('urban-goodz/ai-chief-of-staff/speak', [AiOperationsController::class, 'chiefOfStaffSpeak'])
    ->middleware(['module:urban_goodz_ai_copilot_use', 'throttle:20,1'])
    ->name('admin.urban-goodz.ai-chief-of-staff.speak');

Route::get('urban-goodz/ai-chief-of-staff/notifications', [AiOperationsController::class, 'chiefOfStaffNotifications'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.notifications');

Route::post('urban-goodz/ai-chief-of-staff/notifications/{id}/action', [AiOperationsController::class, 'chiefOfStaffNotificationAction'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.notification.action');

Route::get('urban-goodz/ai-chief-of-staff/trial-dashboard', [AiOperationsController::class, 'chiefOfStaffTrialDashboard'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.trial-dashboard');

Route::get('urban-goodz/ai-chief-of-staff/subscription', [AiOperationsController::class, 'chiefOfStaffSubscription'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.subscription');

Route::post('urban-goodz/ai-chief-of-staff/subscription/cancel', [AiOperationsController::class, 'chiefOfStaffSubscriptionCancel'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.subscription.cancel');

Route::post('urban-goodz/ai-chief-of-staff/subscription/reactivate', [AiOperationsController::class, 'chiefOfStaffSubscriptionReactivate'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.subscription.reactivate');

Route::post('urban-goodz/ai-chief-of-staff/subscription/auto-continue', [AiOperationsController::class, 'chiefOfStaffSubscriptionAutoContinue'])
    ->middleware('module:urban_goodz_ai_copilot_use')
    ->name('admin.urban-goodz.ai-chief-of-staff.subscription.auto-continue');

Route::group([
    'prefix' => 'urban-goodz/driver-network',
    'as' => 'admin.urban-goodz.driver-network.',
    'middleware' => 'module:urban_goodz_ai_copilot_use',
], function () {
    Route::get('/', [\App\Http\Controllers\Admin\UrbanGoodzDriverNetworkAdminController::class, 'dashboard'])->name('dashboard');
    Route::post('{id}/approve', [\App\Http\Controllers\Admin\UrbanGoodzDriverNetworkAdminController::class, 'approve'])->name('approve');
    Route::post('{id}/suspend', [\App\Http\Controllers\Admin\UrbanGoodzDriverNetworkAdminController::class, 'suspend'])->name('suspend');
    Route::post('{id}/reactivate', [\App\Http\Controllers\Admin\UrbanGoodzDriverNetworkAdminController::class, 'reactivate'])->name('reactivate');
    Route::get('shortage-analysis', [\App\Http\Controllers\Admin\UrbanGoodzDriverNetworkAdminController::class, 'shortageAnalysis'])->name('shortage-analysis');
});
