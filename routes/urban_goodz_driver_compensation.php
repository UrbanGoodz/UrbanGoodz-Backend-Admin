<?php

use Illuminate\Support\Facades\Route;

Route::prefix('urban-goodz/driver/compensation')->group(function () {
    Route::get('/latest', [\App\Http\Controllers\Api\UrbanGoodz\UrbanGoodzCompensationApiController::class, 'latest']);
    Route::get('/history', [\App\Http\Controllers\Api\UrbanGoodz\UrbanGoodzCompensationApiController::class, 'history']);
    Route::get('/{calculationId}', [\App\Http\Controllers\Api\UrbanGoodz\UrbanGoodzCompensationApiController::class, 'show']);
});
